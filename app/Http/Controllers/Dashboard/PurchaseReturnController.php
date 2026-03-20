<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\PurchaseDetail;
use App\Support\ActiveShop;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PurchaseReturnController extends Controller
{
    public function __construct()
    {
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
                return !empty($item['product_id']) && (float) ($item['quantity'] ?? 0) > 0;
            })
            ->values()
            ->all();

        if (empty($selectedItems)) {
            return back()
                ->withErrors(['items' => 'Please select at least one item to return.'])
                ->withInput();
        }

        $purchase = Purchase::with('shop')
            ->where('id', $validated['purchase_id'])
            ->firstOrFail();

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        if ($authUser->shop_id && !$visibleShopIds->contains($purchase->shop_id)) {
            abort(403, 'You do not have access to this purchase.');
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
            if ($item['quantity'] > 0) {
                $subTotal += $item['total'];
                $totalProducts++;
            }
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
            if ($item['quantity'] <= 0) {
                continue;
            }

            PurchaseReturnDetail::create([
                'purchase_return_id' => $purchaseReturn->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
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

        $details = $purchase->purchaseDetails->map(function (PurchaseDetail $detail) {
            return [
                'id' => $detail->id,
                'product_id' => $detail->product_id,
                'product_name' => $detail->product->resolved_name ?? 'N/A',
                'product_code' => $detail->product->resolved_code ?? 'N/A',
                'quantity' => (int) $detail->quantity,
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
}

