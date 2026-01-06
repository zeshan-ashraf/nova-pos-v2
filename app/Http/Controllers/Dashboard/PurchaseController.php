<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Support\ActiveShop;
use App\Services\SupplierCreditService;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index()
    {
        $row = (int) request('row', 10);

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
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        // Filter suppliers by shop
        $suppliersQuery = Supplier::query();
        if ($authUser->shop_id) {
            $suppliersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $suppliersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        // Filter products by shop for dropdown
        $productsQuery = Product::where(function($query) {
                $query->where('status', 'valid')
                      ->orWhere('status', 'active');
            });

        if ($authUser->shop_id) {
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $productsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('purchases.create', [
            'suppliers' => $suppliersQuery->orderBy('shopname')->get(),
            'products' => $productsQuery->orderBy('product_name')->get(),
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
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        
        // Build base query with status filtering
        $productsQuery = Product::where(function($query) {
            $query->where('status', 'valid')
                  ->orWhere('status', 'active');
        });
        
        // Apply shop filtering
        if ($authUser->shop_id) {
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $productsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }
        
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
    public function store(Request $request, SupplierCreditService $creditService)
    {
        $rules = [
            'supplier_id' => 'required|numeric',
            'purchase_date' => 'required|date',
            'payment_status' => 'required|string|in:cash,bank,cheque,credit',
            'pay' => 'numeric|nullable|min:0',
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
        $payAmount = $validatedData['pay'] ?? 0;

        // Validate that supplier belongs to the same shop as logged-in user
        $authUser = auth()->user();
        $supplier = Supplier::findOrFail($validatedData['supplier_id']);
        
        if ($authUser->shop_id) {
            // For users with shop_id, supplier must belong to the same shop
            if ($supplier->shop_id !== $authUser->shop_id) {
                return back()->withErrors(['supplier_id' => 'The selected supplier does not belong to your shop.'])
                    ->withInput();
            }
        } else {
            // For SuperAdmin, supplier should have a shop_id (not null)
            if (!$supplier->shop_id) {
                return back()->withErrors(['supplier_id' => 'The selected supplier is not assigned to any shop.'])
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
            'shop_id' => $authUser->shop_id ?: $supplier->shop_id,
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

        try {
            DB::transaction(function () use (&$purchase_id, $purchaseData, $validatedData, $supplier, $creditService, $authUser, $due, $payAmount) {
                // 1. Create purchase
                $purchase = Purchase::create($purchaseData);
                $purchase_id = $purchase->id;

                // 2. Create purchase details and increase stock
                foreach ($validatedData['products'] as $product) {
                    if (empty($product['product_id'])) {
                        continue;
                    }

                    $productModel = Product::findOrFail($product['product_id']);

                    // Validate shop access for product
                    if ($authUser->shop_id && $productModel->shop_id !== $authUser->shop_id) {
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

                    // Increase stock
                    Product::where('id', $product['product_id'])
                        ->update(['product_store' => DB::raw('product_store + ' . $product['quantity'])]);
                }

                // 3. Create payment log if payment was made
                if ($payAmount > 0) {
                    PurchasePaymentLog::create([
                        'purchase_id' => $purchase_id,
                        'amount_paid' => $payAmount,
                        'type' => 'payment',
                    ]);
                }

                // 4. Adjust supplier credit
                if ($due > 0) {
                    // Increase supplier credit by pending amount
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
        $row = (int) request('row', 10);

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

        return view('purchases.pending', [
            'purchases' => $purchasesQuery->paginate($row)->appends(request()->query())
        ]);
    }

    /**
     * Display complete purchases.
     */
    public function complete()
    {
        $row = (int) request('row', 10);

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

        return view('purchases.complete', [
            'purchases' => $purchasesQuery->paginate($row)->appends(request()->query())
        ]);
    }

    /**
     * Delete (soft delete) a purchase and reverse all related operations.
     */
    public function destroy(int $purchase_id)
    {
        $purchase = Purchase::with(['supplier', 'purchaseDetails.product', 'paymentLogs'])
            ->findOrFail($purchase_id);
        $this->ensureShopAccess($purchase);

        $creditService = new SupplierCreditService();
        $supplier = $purchase->supplier;

        try {
            DB::transaction(function () use ($purchase, $supplier, $creditService) {
                // 1. Reverse stock for all purchase details
                foreach ($purchase->purchaseDetails as $purchaseDetail) {
                    $product = $purchaseDetail->product;
                    
                    if (!$product) {
                        throw new \Exception("Product with ID {$purchaseDetail->product_id} not found. Cannot reverse stock.");
                    }

                    // Decrease stock (reverse the increase from purchase)
                    Product::where('id', $purchaseDetail->product_id)
                        ->update(['product_store' => DB::raw('product_store - ' . $purchaseDetail->quantity)]);
                }

                // 2. Reverse supplier credit for pending amount (due)
                if ($supplier && $purchase->due > 0) {
                    $creditService->removePending($supplier, $purchase->due);
                }

                // 3. Reverse supplier credit for all payments made
                if ($supplier) {
                    foreach ($purchase->paymentLogs as $paymentLog) {
                        if ($paymentLog->type === 'payment' && $paymentLog->amount_paid > 0) {
                            $creditService->reversePayment($supplier, $paymentLog->amount_paid);
                        }
                    }
                }

                // 4. Soft delete payment logs
                foreach ($purchase->paymentLogs as $paymentLog) {
                    $paymentLog->delete();
                }

                // 5. Soft delete purchase details
                foreach ($purchase->purchaseDetails as $purchaseDetail) {
                    $purchaseDetail->delete();
                }

                // 6. Soft delete purchase
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
