<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Support\ActiveShop;
use App\Services\Ledger\PurchaseLedgerService;
use App\Services\Stock\StockService;
use App\Services\SupplierCreditService;

class PurchaseController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Display a listing of purchases.
     */
    public function index()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $search = request('search');
        $purchasesQuery = Purchase::with(['supplier', 'shop.parent'])
            ->sortable()
            ->when($search, function ($query, $search) {
                return $query->where('purchase_no', 'like', '%' . $search . '%')
                             ->orWhereHas('supplier', function($query) use ($search) {
                                 $query->where('name', 'like', '%' . $search . '%')
                                       ->orWhere('shopname', 'like', '%' . $search . '%');
                             })
                             ->orWhere('purchase_date', 'like', '%' . $search . '%')
                             ->orWhere('pay', 'like', '%' . $search . '%')
                             ->orWhere('payment_status', 'like', '%' . $search . '%');
            });

        // Apply shop filtering
        if ($authUser->shop_id) {
            $purchasesQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $purchasesQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        // Apply default ordering by id DESC if no sort is specified
        if (!request()->has('sort')) {
            $purchasesQuery->orderBy('id', 'desc');
        }

        return view('purchases.index', [
            'purchases' => $purchasesQuery->paginate($row)->appends(request()->query())
        ]);
    }

    /**
     * Show the form for creating a new purchase.
     */
    public function create()
    {
        $authUser = auth()->user();
        $targetShopId = $authUser->shop_id;

        $suppliersQuery = Supplier::query();
        $productsQuery = Product::where(function ($query) {
            $query->where('status', 'valid')
                ->orWhere('status', 'active')
                ->orWhere('status', 'ordered');
        });

        $shopBanks = [];
        if ($targetShopId) {
            $shopBanks = DB::table('bank_shop')
                ->where('bank_shop.shop_id', $targetShopId)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->select('bank_shop.id', 'banks.name')
                ->orderBy('banks.name')
                ->get();
        }

        return view('purchases.create', [
            'suppliers' => $suppliersQuery->orderBy('shopname')->get(),
            'products' => $productsQuery->orderBy('product_name')->get(),
            'shopBanks' => $shopBanks,
        ]);
    }

    /**
     * Search products for autocomplete in purchase (filtered by shop).
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $authUser = auth()->user();
        
        // Build base query with status filtering (include 'ordered' for purchase create); shop scope applied by model trait
        $productsQuery = Product::where(function ($query) {
            $query->where('status', 'valid')
                ->orWhere('status', 'active')
                ->orWhere('status', 'ordered');
        });

        // Apply search filter (only if search term is provided)
        if (!empty($search)) {
            $productsQuery->where(function($query) use ($search) {
                $query->where('product_name', 'like', '%' . $search . '%')
                      ->orWhere('product_code', 'like', '%' . $search . '%');
            });
        }

        // Get total count for pagination
        $totalCount = (clone $productsQuery)->count();
        
        // Apply pagination (50 items per page)
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $products = $productsQuery->orderBy('product_code', 'asc')
            ->orderBy('product_name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($product) {
                $productCode = $product->product_code ?? '';
                $displayText = $productCode ? $productCode . ' - ' . $product->product_name : $product->product_name;
                
                return [
                    'id' => $product->id,
                    'text' => $displayText,
                    'name' => $product->product_name,
                    'price' => $product->buying_price ?? 0,
                    'stock' => $product->product_store ?? 0,
                    'code' => $productCode,
                ];
            });

        return response()->json([
            'results' => $products,
            'pagination' => [
                'more' => ($page * $perPage) < $totalCount
            ]
        ]);
    }

    /**
     * Store a newly created purchase.
     */
    public function store(Request $request, SupplierCreditService $creditService, PurchaseLedgerService $purchaseLedgerService)
    {
        $rules = [
            'supplier_id' => 'required|numeric',
            'purchase_date' => 'required|date',
            'payment_status' => 'required|string|in:cash,bank,cheque,credit',
            'pay' => 'numeric|nullable|min:0',
            'shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
            'vat' => 'numeric|nullable|min:0',
            'invoice_discount' => 'numeric|nullable|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|numeric',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.total' => 'required|numeric|min:0',
            'products.*.item_discount' => 'nullable|numeric|min:0',
        ];

        $validatedData = $request->validate($rules);
        $payAmount = (float) ($validatedData['pay'] ?? 0);
        $paymentStatus = $validatedData['payment_status'] ?? '';

        // Validate that supplier belongs to the same shop as logged-in user
        $authUser = auth()->user();
        $supplier = Supplier::findOrFail($validatedData['supplier_id']);

        if ($supplier->shop_id !== $authUser->shop_id) {
            return back()->withErrors(['supplier_id' => 'The selected supplier does not belong to your shop.'])
                ->withInput();
        }

        if ($payAmount > 0 && in_array(strtolower($paymentStatus), ['bank', 'cheque'], true)) {
            $shopBankId = $validatedData['shop_bank_id'] ?? null;
            if (empty($shopBankId)) {
                return back()->withErrors(['shop_bank_id' => 'Please select a bank when payment method is Bank or Cheque.'])
                    ->withInput();
            }
            if ($authUser->shop_id && !DB::table('bank_shop')->where('id', $shopBankId)->where('shop_id', $authUser->shop_id)->exists()) {
                return back()->withErrors(['shop_bank_id' => 'The selected bank is not valid for your shop.'])
                    ->withInput();
            }
        }

        // Generate purchase number
        $purchase_no = IdGenerator::generate([
            'table' => 'purchases',
            'field' => 'purchase_no',
            'length' => 10,
            'prefix' => 'PUR-'
        ]);

        // Calculate totals
        $subtotal = 0;
        $totalProducts = 0;
        foreach ($validatedData['products'] as $product) {
            if (!empty($product['product_id'])) {
                $subtotal += $product['total'];
                $totalProducts++;
            }
        }

        $vat = $validatedData['vat'] ?? 0;
        $invoiceDiscount = $validatedData['invoice_discount'] ?? 0;
        $total = max(0, $subtotal + $vat - $invoiceDiscount);
        $due = $total - $payAmount;

        // Prepare purchase data
        $purchaseData = [
            'supplier_id' => $supplier->id,
            'shop_id' => $authUser->shop_id,
            'purchase_date' => Carbon::parse($validatedData['purchase_date'])->format('Y-m-d'),
            'purchase_status' => 'pending',
            'total_products' => $totalProducts,
            'sub_total' => $subtotal,
            'invoice_discount' => $invoiceDiscount,
            'vat' => $vat,
            'purchase_no' => $purchase_no,
            'total' => $total,
            'payment_status' => $validatedData['payment_status'],
            'pay' => $payAmount,
            'due' => $due,
            'comment' => $request->input('comment'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $purchase_id = null;

        $purchaseDate = Carbon::parse($validatedData['purchase_date'])->format('Y-m-d');
        $shopBankId = ($validatedData['shop_bank_id'] ?? null) ? (string) $validatedData['shop_bank_id'] : null;

        try {
            DB::transaction(function () use (&$purchase_id, $purchaseData, $validatedData, $supplier, $creditService, $purchaseLedgerService, $authUser, $due, $payAmount, $purchaseDate, $shopBankId) {
                // 1. Create purchase
                $purchase = Purchase::create($purchaseData);
                $purchase_id = $purchase->id;

                // Double-entry: (1) purchase debit (total), (2) supplier credit (due), (3) cash/bank credit (pay) if any
                $purchaseLedgerService->recordPurchaseDebit($purchase);
                $purchaseLedgerService->recordPurchaseSupplierCredit($purchase);

                // 2. Create purchase details and increase stock (stock_logs with purchase_date for COGS)
                foreach ($validatedData['products'] as $product) {
                    if (empty($product['product_id'])) {
                        continue;
                    }

                    $productModel = Product::findOrFail($product['product_id']);

                    // Validate shop access for product - must belong to user's shop
                    if ($productModel->shop_id !== $authUser->shop_id) {
                        throw new \Exception("Product {$productModel->product_name} does not belong to your shop.");
                    }

                    // Create purchase detail
                    $purchaseDetailData = [
                        'purchase_id' => $purchase_id,
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'unitcost' => $product['unit_price'],
                        'item_discount' => $product['item_discount'] ?? 0,
                        'total' => $product['total'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    PurchaseDetail::insert($purchaseDetailData);

                    // Increase stock via ledger (StockService; price = purchase_unit_cost for COGS)
                    $this->stockService->purchaseStock(
                        $productModel,
                        (int) $product['quantity'],
                        (float) ($product['unit_price'] ?? 0),
                        (int) $supplier->id,
                        $purchase_id,
                        $purchaseDate
                    );

                    // Once buying price is set via purchase, mark product as active (was ordered)
                    if ($productModel->status === 'ordered') {
                        $productModel->update(['status' => 'active']);
                    }
                }

                // 3. Create payment log if payment was made (shop_bank_id for bank/cheque)
                if ($payAmount > 0) {
                    PurchasePaymentLog::create([
                        'purchase_id' => $purchase_id,
                        'amount_paid' => $payAmount,
                        'type' => 'payment',
                        'shop_bank_id' => in_array(strtolower($validatedData['payment_status'] ?? ''), ['bank', 'cheque'], true) ? $shopBankId : null,
                    ]);
                    // Ledger: cash outflow (debit) for paid amount; credit-only has no ledger entry until payment later
                    $purchaseLedgerService->recordPurchasePayment($purchase, $payAmount, $shopBankId);
                }

                // 4. Adjust supplier credit
                if ($due > 0) {
                    $creditService->addPending($supplier, $due);
                }
            });

            $warning = null;
            if ($creditService->exceedsLimit($supplier, $due)) {
                $warning = 'Credit limit exceeded for this supplier. Purchase saved on credit.';
            }

            return Redirect::route('purchases.index')->with([
                'success' => 'Purchase has been created successfully!',
                'warning' => $warning,
            ]);
        } catch (\Exception $e) {
            // If purchase was created, delete it
            if ($purchase_id) {
                Purchase::where('id', $purchase_id)->delete();
            }
            return back()->withErrors(['error' => 'Failed to create purchase: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified purchase.
     */
    public function show(int $purchase_id)
    {
        $purchase = Purchase::with(['supplier', 'shop.banks'])->findOrFail($purchase_id);
        $this->ensureShopAccess($purchase);
        
        $purchaseDetails = PurchaseDetail::with('product')
                        ->where('purchase_id', $purchase_id)
                        ->orderBy('id', 'DESC')
                        ->get();

        return view('purchases.show', [
            'purchase' => $purchase,
            'purchaseDetails' => $purchaseDetails,
        ]);
    }

    /**
     * Update purchase status to complete.
     */
    public function updateStatus(Request $request)
    {
        $purchase = Purchase::findOrFail($request->id);
        $this->ensureShopAccess($purchase);

        $purchase->update(['purchase_status' => 'complete']);

        return Redirect::route('purchases.pending')->with('success', 'Purchase has been completed!');
    }

    /**
     * Display pending purchases.
     */
    public function pending()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $purchasesQuery = Purchase::with(['supplier', 'shop.parent'])
            ->where('purchase_status', 'pending')
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            $purchasesQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $purchasesQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        // Apply default ordering by id DESC if no sort is specified
        if (!request()->has('sort')) {
            $purchasesQuery->orderBy('id', 'desc');
        }

        return view('purchases.pending', [
            'purchases' => $purchasesQuery->paginate($row)->appends(request()->query())
        ]);
    }

    /**
     * Display complete purchases.
     */
    public function complete()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $purchasesQuery = Purchase::with(['supplier', 'shop.parent'])
            ->where('purchase_status', 'complete')
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            $purchasesQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $purchasesQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        // Apply default ordering by id DESC if no sort is specified
        if (!request()->has('sort')) {
            $purchasesQuery->orderBy('id', 'desc');
        }

        return view('purchases.complete', [
            'purchases' => $purchasesQuery->paginate($row)->appends(request()->query())
        ]);
    }

    /**
     * Delete (soft delete) a purchase and reverse stock. No hard deletes; no reversal ledger entries.
     * Related records (purchase_details, account_transactions, purchase_payment_logs, stock_logs) are soft deleted.
     * Balances auto-adjust because account_transactions use SoftDeletes (excluded from sums).
     */
    public function destroy(int $purchase_id)
    {
        $purchase = Purchase::with(['supplier', 'purchaseDetails.product', 'paymentLogs'])
            ->findOrFail($purchase_id);
        $this->ensureShopAccess($purchase);

        if ($purchase->is_system_generated ?? false) {
            return Redirect::back()->with('error', 'System generated purchase cannot be deleted.');
        }

        try {
            DB::transaction(function () use ($purchase) {
                // 1. Lock invoice (with relations for stock reversal and payment log ids)
                $purchase = Purchase::with(['purchaseDetails.product', 'paymentLogs'])
                    ->lockForUpdate()
                    ->findOrFail($purchase->id);
                if ($purchase->trashed()) {
                    throw new \RuntimeException('This purchase invoice has already been deleted.');
                }

                // 2. Reverse stock: decrease product_store by purchased quantity (purchase invoice)
                foreach ($purchase->purchaseDetails as $purchaseDetail) {
                    $product = Product::withoutGlobalScope('shop')
                        ->where('id', $purchaseDetail->product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product && $purchaseDetail->quantity > 0) {
                        $current = (int) $product->product_store;
                        if ($current < $purchaseDetail->quantity) {
                            throw new \RuntimeException(
                                'Cannot delete purchase: product "' . ($product->product_name ?? $product->id) . '" would have negative stock.'
                            );
                        }
                        $product->decrement('product_store', $purchaseDetail->quantity);
                    }
                }

                // 3. Soft delete related: purchase_details
                PurchaseDetail::where('purchase_id', $purchase->id)->delete();

                // 4. Soft delete account_transactions related to this purchase (purchase + purchase_payment entries)
                $paymentLogIds = $purchase->paymentLogs()->pluck('id')->toArray();
                AccountTransaction::query()
                    ->where(function ($q) use ($purchase, $paymentLogIds) {
                        $q->where('source_type', AccountTransaction::SOURCE_PURCHASE)
                            ->where('source_id', $purchase->id);
                        if (count($paymentLogIds) > 0) {
                            $q->orWhere('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
                                ->whereIn('source_id', $paymentLogIds);
                        }
                    })
                    ->delete();

                // 5. Soft delete purchase_payment_logs
                PurchasePaymentLog::where('purchase_id', $purchase->id)->delete();

                // 6. Soft delete stock_logs for this purchase
                StockLog::query()
                    ->where('source_type', 'purchase')
                    ->where('source_id', (string) $purchase->id)
                    ->delete();

                // 7. Soft delete purchase
                $purchase->delete();
            });

            return Redirect::route('purchases.index')->with('success', 'Purchase has been deleted successfully! Stock and payments have been reversed.');
        } catch (\Exception $e) {
            return Redirect::route('purchases.index')->with('error', 'Failed to delete purchase: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the current user has access to the purchase based on shop.
     */
    protected function ensureShopAccess(Purchase $purchase): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            if ($purchase->shop_id && !$visibleShopIds->contains($purchase->shop_id)) {
                abort(403, 'You do not have access to this purchase.');
            }
        }
        // Super admin can access all purchases
    }
}
