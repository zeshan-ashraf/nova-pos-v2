<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Support\ActiveShop;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalesReportController extends Controller
{
    use ReportTrait;

    /**
     * Sales Summary Report
     */
    public function summary(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Build query
        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        $this->applyShopFilter($ordersQuery, $shopFilter['shop_ids']);

        // Get summary data
        $totalOrders = (clone $ordersQuery)->count();
        $totalRevenue = (clone $ordersQuery)->sum('total');
        $totalPaid = (clone $ordersQuery)->sum('pay');
        $totalDue = (clone $ordersQuery)->sum('due');
        $totalVat = (clone $ordersQuery)->sum('vat');
        $totalDiscount = (clone $ordersQuery)->sum('invoice_discount');
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // By payment method
        $byPaymentMethod = (clone $ordersQuery)
            ->select('payment_status', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_status')
            ->get();

        // By order status
        $byOrderStatus = (clone $ordersQuery)
            ->select('order_status', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('order_status')
            ->get();

        // Top customers
        $topCustomers = (clone $ordersQuery)
            ->select('customer_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->with('customer')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Paginated orders
        $orders = $ordersQuery->orderByDesc('id')->paginate($row)->appends($request->query());

        return view('reports.sales.summary', compact(
            'dateRange',
            'shopFilter',
            'totalOrders',
            'totalRevenue',
            'totalPaid',
            'totalDue',
            'totalVat',
            'totalDiscount',
            'avgOrderValue',
            'byPaymentMethod',
            'byOrderStatus',
            'topCustomers',
            'orders',
            'row'
        ));
    }

    /**
     * Daily Sales Report
     */
    public function daily(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Build query
        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        $this->applyShopFilter($ordersQuery, $shopFilter['shop_ids']);

        // Daily breakdown
        $dailyBreakdown = (clone $ordersQuery)
            ->select(
                DB::raw('DATE(order_date) as date'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(pay) as paid'),
                DB::raw('SUM(due) as due'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date', 'desc')
            ->get();

        // Summary
        $totalRevenue = (clone $ordersQuery)->sum('total');
        $totalPaid = (clone $ordersQuery)->sum('pay');
        $totalDue = (clone $ordersQuery)->sum('due');
        $totalOrders = (clone $ordersQuery)->count();

        // Paginated orders
        $orders = $ordersQuery->orderByDesc('id')->paginate($row)->appends($request->query());

        return view('reports.sales.daily', compact(
            'dateRange',
            'shopFilter',
            'dailyBreakdown',
            'totalRevenue',
            'totalPaid',
            'totalDue',
            'totalOrders',
            'orders',
            'row'
        ));
    }

    /**
     * Customer Sales Report
     */
    public function customer(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Build query
        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        $this->applyShopFilter($ordersQuery, $shopFilter['shop_ids']);

        // Customer summary
        $customerSalesQuery = (clone $ordersQuery)
            ->select(
                'customer_id',
                DB::raw('SUM(total) as total_sales'),
                DB::raw('SUM(pay) as total_paid'),
                DB::raw('SUM(due) as total_due'),
                DB::raw('COUNT(*) as order_count')
            )
            ->with('customer')
            ->groupBy('customer_id')
            ->orderByDesc('total_sales');

        // Apply customer filter if provided
        $selectedCustomerId = $request->input('customer_id');
        if ($selectedCustomerId) {
            $customerSalesQuery->where('customer_id', $selectedCustomerId);
        }

        $customerSales = $customerSalesQuery->paginate($row)->appends($request->query());

        // Summary - recalculate based on filtered data
        $summaryQuery = clone $ordersQuery;
        if ($selectedCustomerId) {
            $summaryQuery->where('customer_id', $selectedCustomerId);
        }
        
        $totalCustomers = $selectedCustomerId ? 1 : (clone $ordersQuery)->distinct('customer_id')->count('customer_id');
        $totalRevenue = $summaryQuery->sum('total');
        $totalPaid = (clone $summaryQuery)->sum('pay');
        $totalDue = (clone $summaryQuery)->sum('due');

        // Get selected customer for display
        $selectedCustomer = null;
        if ($selectedCustomerId) {
            $selectedCustomer = \App\Models\Customer::find($selectedCustomerId);
        }

        return view('reports.sales.customer', compact(
            'dateRange',
            'shopFilter',
            'customerSales',
            'totalCustomers',
            'totalRevenue',
            'totalPaid',
            'totalDue',
            'selectedCustomerId',
            'selectedCustomer',
            'row'
        ));
    }

    /**
     * Product Sales Report
     */
    public function product(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Get order IDs in date range and shop filter
        $orderIds = Order::whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($orderIds, $shopFilter['shop_ids']);
        $orderIds = $orderIds->pluck('id');

        // Product sales summary
        $productSalesQuery = OrderDetails::with(['product', 'order.shop.parent'])
            ->whereIn('order_id', $orderIds)
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_id) as order_count')
            )
            ->groupBy('product_id');

        // Apply product filter if provided
        $selectedProductId = $request->input('product_id');
        if ($selectedProductId) {
            $productSalesQuery->where('product_id', $selectedProductId);
        }

        // Calculate summary before pagination
        $totalProductsQuery = clone $productSalesQuery;
        $totalProducts = $selectedProductId ? 1 : $totalProductsQuery->get()->count();
        
        // Recalculate totals based on filtered product
        $summaryQuery = OrderDetails::whereIn('order_id', $orderIds);
        if ($selectedProductId) {
            $summaryQuery->where('product_id', $selectedProductId);
        }
        $totalQuantity = $summaryQuery->sum('quantity');
        $totalRevenue = (clone $summaryQuery)->sum('total');

        // Get total count for pagination (without ORDER BY to avoid SQL errors)
        $totalCount = (clone $productSalesQuery)->get()->count();
        
        // Get current page
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $row;
        
        // Apply ordering and get results manually
        $productSalesItems = $productSalesQuery
            ->orderByRaw('SUM(total) DESC')
            ->offset($offset)
            ->limit($row)
            ->get();
        
        // Create manual paginator
        $productSales = new \Illuminate\Pagination\LengthAwarePaginator(
            $productSalesItems,
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

        return view('reports.sales.product', compact(
            'dateRange',
            'shopFilter',
            'productSales',
            'totalProducts',
            'totalQuantity',
            'totalRevenue',
            'selectedProductId',
            'selectedProduct',
            'row'
        ));
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

    /**
     * Search customers for autocomplete (AJAX).
     */
    public function searchCustomers(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        
        // Build base query
        $customersQuery = Customer::query();
        
        // Apply shop filtering
        if ($authUser->shop_id) {
            $customersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $customersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }
        
        // Apply search filter (only if search term is provided)
        if (!empty($search)) {
            $customersQuery->where(function($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('shopname', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        // Get total count for pagination
        $totalCount = (clone $customersQuery)->count();
        
        // Apply pagination (50 items per page)
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $customers = $customersQuery->orderBy('shopname', 'asc')
            ->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($customer) {
                $displayText = $customer->shopname ? $customer->shopname . ' (' . $customer->name . ')' : $customer->name;
                if ($customer->phone) {
                    $displayText .= ' - ' . $customer->phone;
                }
                
                return [
                    'id' => $customer->id,
                    'text' => $displayText,
                    'name' => $customer->name,
                    'shopname' => $customer->shopname,
                    'phone' => $customer->phone,
                ];
            });

        return response()->json([
            'results' => $customers,
            'pagination' => [
                'more' => ($page * $perPage) < $totalCount
            ]
        ]);
    }

    /**
     * Export Sales Summary to PDF
     */
    public function summaryPdf(Request $request)
    {
        // Similar logic to summary() but return PDF
        // Implementation similar to customer ledger PDF
        // This is a placeholder - full implementation would mirror summary() method
    }

    /**
     * Export Sales Summary to Excel
     */
    public function summaryExcel(Request $request)
    {
        // Implementation for Excel export
    }

    /**
     * Export Sales Summary to CSV
     */
    public function summaryCsv(Request $request)
    {
        // Implementation for CSV export
    }
}
