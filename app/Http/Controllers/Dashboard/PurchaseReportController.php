<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\ActiveShop;

class PurchaseReportController extends Controller
{
    use ReportTrait;

    public function summary(Request $request)
    {
        // Implementation similar to SalesReportController::summary()
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        $purchasesQuery = Purchase::with(['supplier', 'shop.parent'])
            ->whereBetween('purchase_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        $this->applyShopFilter($purchasesQuery, $shopFilter['shop_ids']);

        // Summary calculations
        $totalPurchases = (clone $purchasesQuery)->count();
        $totalAmount = (clone $purchasesQuery)->sum('total');
        $totalPaid = (clone $purchasesQuery)->sum('pay');
        $totalDue = (clone $purchasesQuery)->sum('due');
        $totalVat = (clone $purchasesQuery)->sum('vat');
        $totalDiscount = (clone $purchasesQuery)->sum('invoice_discount');

        $purchases = $purchasesQuery->orderByDesc('id')->paginate($row)->appends($request->query());

        return view('reports.purchases.summary', compact(
            'dateRange', 'shopFilter', 'totalPurchases', 'totalAmount', 
            'totalPaid', 'totalDue', 'totalVat', 'totalDiscount', 'purchases', 'row'
        ));
    }

    public function supplier(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Base query
        $purchasesQuery = Purchase::with(['supplier', 'shop.parent'])
            ->whereBetween('purchase_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        $this->applyShopFilter($purchasesQuery, $shopFilter['shop_ids']);

        // Group by supplier
        $supplierPurchasesQuery = (clone $purchasesQuery)
            ->select(
                'supplier_id',
                DB::raw('SUM(total) as total_amount'),
                DB::raw('SUM(pay) as total_paid'),
                DB::raw('SUM(due) as total_due'),
                DB::raw('COUNT(*) as purchase_count')
            )
            ->with('supplier')
            ->groupBy('supplier_id')
            ->orderByDesc('total_amount');

        // Filter by supplier
        $selectedSupplierId = $request->input('supplier_id');
        if ($selectedSupplierId) {
            $supplierPurchasesQuery->where('supplier_id', $selectedSupplierId);
        }

        $supplierPurchases = $supplierPurchasesQuery->paginate($row)->appends($request->query());

        // Summary recalculated for selected supplier
        $summaryQuery = clone $purchasesQuery;
        if ($selectedSupplierId) {
            $summaryQuery->where('supplier_id', $selectedSupplierId);
        }

        $totalSuppliers = $selectedSupplierId ? 1 : (clone $purchasesQuery)->distinct('supplier_id')->count('supplier_id');
        $totalAmount = $summaryQuery->sum('total');
        $totalPaid = (clone $summaryQuery)->sum('pay');
        $totalDue = (clone $summaryQuery)->sum('due');

        $selectedSupplier = null;
        if ($selectedSupplierId) {
            $selectedSupplier = Supplier::find($selectedSupplierId);
        }

        return view('reports.purchases.supplier', compact(
            'dateRange',
            'shopFilter',
            'supplierPurchases',
            'totalSuppliers',
            'totalAmount',
            'totalPaid',
            'totalDue',
            'selectedSupplierId',
            'selectedSupplier',
            'row'
        ));
    }

    public function product(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Get purchase IDs in date range and shop filter
        $purchaseIds = Purchase::whereBetween('purchase_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($purchaseIds, $shopFilter['shop_ids']);
        $purchaseIds = $purchaseIds->pluck('id');

        // Product purchase summary
        $productPurchasesQuery = PurchaseDetail::with(['product', 'purchase.shop.parent'])
            ->whereIn('purchase_id', $purchaseIds)
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('COUNT(DISTINCT purchase_id) as purchase_count')
            )
            ->groupBy('product_id');

        // Apply product filter if provided
        $selectedProductId = $request->input('product_id');
        if ($selectedProductId) {
            $productPurchasesQuery->where('product_id', $selectedProductId);
        }

        // Calculate summary before pagination
        $totalProductsQuery = clone $productPurchasesQuery;
        $totalProducts = $selectedProductId ? 1 : $totalProductsQuery->get()->count();
        
        // Recalculate totals based on filtered product
        $summaryQuery = PurchaseDetail::whereIn('purchase_id', $purchaseIds);
        if ($selectedProductId) {
            $summaryQuery->where('product_id', $selectedProductId);
        }
        $totalQuantity = $summaryQuery->sum('quantity');
        $totalAmount = (clone $summaryQuery)->sum('total');

        // Get total count for pagination (without ORDER BY to avoid SQL errors)
        $totalCount = (clone $productPurchasesQuery)->get()->count();
        
        // Get current page
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $row;
        
        // Apply ordering and get results manually
        $productPurchasesItems = $productPurchasesQuery
            ->orderByRaw('SUM(total) DESC')
            ->offset($offset)
            ->limit($row)
            ->get();
        
        // Create manual paginator
        $productPurchases = new LengthAwarePaginator(
            $productPurchasesItems,
            $totalCount,
            $row,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Get selected product for display
        $selectedProduct = null;
        if ($selectedProductId) {
            $selectedProduct = Product::find($selectedProductId);
        }

        return view('reports.purchases.product', compact(
            'dateRange',
            'shopFilter',
            'productPurchases',
            'totalProducts',
            'totalQuantity',
            'totalAmount',
            'selectedProductId',
            'selectedProduct',
            'row'
        ));
    }

    /**
     * Search suppliers for autocomplete (AJAX).
     */
    public function searchSuppliers(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $suppliersQuery = Supplier::query();

        // Apply shop filtering
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

        // Search filter
        if (!empty($search)) {
            $suppliersQuery->where(function($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('shopname', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $totalCount = (clone $suppliersQuery)->count();
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $suppliers = $suppliersQuery->orderBy('shopname', 'asc')
            ->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($supplier) {
                $displayText = $supplier->shopname ? $supplier->shopname . ' (' . $supplier->name . ')' : $supplier->name;
                if ($supplier->phone) {
                    $displayText .= ' - ' . $supplier->phone;
                }

                return [
                    'id' => $supplier->id,
                    'text' => $displayText,
                    'name' => $supplier->name,
                    'shopname' => $supplier->shopname,
                    'phone' => $supplier->phone,
                ];
            });

        return response()->json([
            'results' => $suppliers,
            'pagination' => [
                'more' => ($page * $perPage) < $totalCount
            ]
        ]);
    }

    /**
     * Search products for autocomplete (AJAX).
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        
        // Build base query
        $productsQuery = Product::query();
        
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
                $displayText = $product->product_name;
                if ($product->product_code) {
                    $displayText .= ' (' . $product->product_code . ')';
                }
                
                return [
                    'id' => $product->id,
                    'text' => $displayText,
                    'name' => $product->product_name,
                    'code' => $product->product_code,
                ];
            });

        return response()->json([
            'results' => $products,
            'pagination' => [
                'more' => ($page * $perPage) < $totalCount
            ]
        ]);
    }
}
