<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\PurchaseDetail;
use App\Support\ActiveShop;
use App\Support\ProductUnitValidator;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private ProductUnitValidator $units
    ) {
        $this->middleware('permission:purchase_returns.view')->only(['index', 'show']);
        $this->middleware('permission:purchase_returns.create')->only(['create', 'store']);
    }

    public function index()
    {
        $row = (int) request('row', 50);
        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $query = PurchaseReturn::with(['purchase', 'shop'])
            ->when($authUser->shop_id, function ($q) use ($visibleShopIds) {
                $q->whereIn('shop_id', $visibleShopIds);
            })
            ->when(request('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->orderByDesc('created_at');

        return view('purchase-returns.index', [
            'returns' => $query->paginate($row)->appends(request()->query()),
        ]);
    }

    public function create(Request $request)
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $purchases = Purchase::with(['supplier'])
            ->whereIn('shop_id', $visibleShopIds)
            ->whereNotNull('source_sale_id') // transfer purchases only
            ->orderByDesc('purchase_date')
            ->limit(100)
            ->get();

        return view('purchase-returns.create', [
            'purchases' => $purchases,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'purchase_id' => 'required|numeric|exists:purchases,id',
            'return_date' => 'required|date',
            'items' => 'required|array|min:1',
            // Only checked rows should be validated as return items.
            // Unchecked rows remain in payload with quantity=0 and must be ignored.
            'items.*.product_id' => 'nullable|numeric|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.total' => 'nullable|numeric|min:0',
        ];

        $validated = $request->validate($rules);

        // Keep only selected rows (positive quantity + product id).
        $selectedItems = collect($validated['items'] ?? [])
            ->filter(function ($item) {
                if (empty($item['product_id']) || !isset($item['quantity']) || $item['quantity'] === '' || $item['quantity'] === null) {
                    return false;
                }
                $normalized = $this->units->normalizeQuantity($item['quantity']);
                if ($normalized === null) {
                    return true;
                }

                return $this->units->compare($normalized, '0') > 0;
            })
            ->values()
            ->all();

        if (empty($selectedItems)) {
            return back()
                ->withErrors(['items' => 'Please select at least one item to return.'])
                ->withInput();
        }

        $purchase = Purchase::with(['shop', 'purchaseDetails'])
            ->where('id', $validated['purchase_id'])
            ->firstOrFail();

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        if ($authUser->shop_id && !$visibleShopIds->contains($purchase->shop_id)) {
            abort(403, 'You do not have access to this purchase.');
        }

        try {
            $this->validateReturnQuantities($purchase, $selectedItems);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $returnNo = IdGenerator::generate([
            'table' => 'purchase_returns',
            'field' => 'return_no',
            'length' => 12,
            'prefix' => 'PRT-',
        ]);

        $subTotal = 0;
        $totalProducts = 0;
        foreach ($selectedItems as $item) {
            $subTotal += $item['total'];
            $totalProducts++;
        }

        $purchaseReturn = PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'shop_id' => $purchase->shop_id,
            'return_no' => $returnNo,
            'return_date' => Carbon::parse($validated['return_date'])->format('Y-m-d H:i:s'),
            'total_products' => $totalProducts,
            'sub_total' => $subTotal,
            'total' => $subTotal,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        foreach ($selectedItems as $item) {
            $purchaseDetail = $this->matchingPurchaseDetail($purchase, (int) $item['product_id']);
            $unit = $purchaseDetail
                ? $purchaseDetail->snapshotUnit()
                : (Product::withoutGlobalScope('shop')->find((int) $item['product_id'])?->unit ?: Product::UNIT_PIECE);
            $qty = $this->units->formatQuantity($item['quantity']);

            PurchaseReturnDetail::create([
                'purchase_return_id' => $purchaseReturn->id,
                'product_id' => $item['product_id'],
                'quantity' => $qty,
                'unit' => $unit,
                'price' => $item['price'],
                'total' => $item['total'],
            ]);
        }

        return redirect()
            ->route('purchase-returns.index')
            ->with('success', 'Purchase return request created. Awaiting mother shop approval.');
    }

    public function show(int $id)
    {
        $return = PurchaseReturn::with(['purchase', 'purchase.order', 'details.product.parent', 'shop'])
            ->findOrFail($id);

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        if ($authUser->shop_id && !$visibleShopIds->contains($return->shop_id)) {
            abort(403, 'You do not have access to this purchase return.');
        }

        return view('purchase-returns.show', [
            'purchaseReturn' => $return,
        ]);
    }

    public function getPurchaseDetails(int $purchaseId)
    {
        $purchase = Purchase::with(['purchaseDetails.product.parent', 'shop', 'order'])
            ->findOrFail($purchaseId);

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        if ($authUser->shop_id && !$visibleShopIds->contains($purchase->shop_id)) {
            abort(403, 'You do not have access to this purchase.');
        }

        $details = $purchase->purchaseDetails->map(function (PurchaseDetail $detail) use ($purchase) {
            $unit = $detail->snapshotUnit();
            $purchased = $this->units->formatQuantity($detail->quantity ?? '0');
            $returned = $this->alreadyReturnedQuantity($purchase, (int) $detail->product_id);
            $available = $this->units->compare($purchased, $returned) <= 0
                ? '0.000'
                : $this->units->subtract($purchased, $returned);

            return [
                'id' => $detail->id,
                'product_id' => $detail->product_id,
                'product_name' => $detail->product->resolved_name ?? 'N/A',
                'product_code' => $detail->product->resolved_code ?? 'N/A',
                'quantity' => $purchased,
                'returned_quantity' => $returned,
                'available_to_return' => $available,
                'unit' => $unit,
                'quantity_step' => $unit === Product::UNIT_KG ? '0.001' : '1',
                'quantity_min' => $unit === Product::UNIT_KG ? '0.001' : '1',
                'quantity_with_unit' => $detail->quantityWithUnit(),
                'unit_price' => (float) $detail->unitcost,
                'total' => (float) $detail->total,
            ];
        })->values();

        return response()->json([
            'purchase' => [
                'id' => $purchase->id,
                'purchase_no' => $purchase->purchase_no,
                'purchase_date' => $purchase->purchase_date,
                'total' => $purchase->total,
                'source_sale_id' => $purchase->source_sale_id,
                'source_sale_invoice' => $purchase->order?->invoice_no,
            ],
            'details' => $details,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function validateReturnQuantities(Purchase $purchase, array $items): void
    {
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $purchaseDetail = $this->matchingPurchaseDetail($purchase, $productId);
            if (!$purchaseDetail) {
                throw ValidationException::withMessages([
                    'items' => ["Invalid purchase line selected for return (product {$productId})."],
                ]);
            }

            $unit = $purchaseDetail->snapshotUnit();
            try {
                $this->units->validateQuantity($item['quantity'] ?? 0, $unit, false);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'items' => [$e->getMessage()],
                ]);
            }

            $requested = $this->units->formatQuantity($item['quantity']);
            $purchased = $this->units->formatQuantity($purchaseDetail->quantity ?? '0');
            $alreadyReturned = $this->alreadyReturnedQuantity($purchase, $productId);
            $returnable = $this->units->compare($purchased, $alreadyReturned) <= 0
                ? '0.000'
                : $this->units->subtract($purchased, $alreadyReturned);

            if ($this->units->compare($requested, $returnable) > 0) {
                throw ValidationException::withMessages([
                    'items' => [sprintf(
                        'Cannot return more than purchased for product line %d. Requested: %s, available to return: %s.',
                        $purchaseDetail->id,
                        $requested,
                        $returnable
                    )],
                ]);
            }
        }
    }

    private function matchingPurchaseDetail(Purchase $purchase, int $productId): ?PurchaseDetail
    {
        return $purchase->purchaseDetails->firstWhere('product_id', $productId);
    }

    private function alreadyReturnedQuantity(Purchase $purchase, int $productId): string
    {
        $sum = PurchaseReturnDetail::query()
            ->where('product_id', $productId)
            ->whereHas('purchaseReturn', function ($query) use ($purchase) {
                $query->where('purchase_id', $purchase->id)
                    ->whereIn('status', ['pending', 'approved']);
            })
            ->sum('quantity');

        return $this->units->formatQuantity($sum ?: '0');
    }
}
