<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\AccountTransaction;
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
        // Total Paid = payment receipts within date filter (cash/bank debits from sales + customer payments)
        $totalPaidQuery = AccountTransaction::query()
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('direction', AccountTransaction::DIRECTION_DEBIT)
            ->whereIn('source_type', [AccountTransaction::SOURCE_SALE, AccountTransaction::SOURCE_CUSTOMER_PAYMENT])
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($totalPaidQuery, $shopFilter['shop_ids']);
        $totalPaid = (float) $totalPaidQuery->sum('amount');
        $totalDue = max(0, $totalRevenue - $totalPaid);
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

        // Paginated orders (chronological: oldest first)
        $orders = $ordersQuery->orderBy('order_date')->orderBy('id')->paginate($row)->appends($request->query());

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

        // Orders grouped by date (for total, due, count)
        $ordersByDate = (clone $ordersQuery)
            ->select(
                DB::raw('DATE(order_date) as date'),
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(due) as due'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Payments by date (cash/bank debits from sales + customer payments within date filter)
        $paymentsByDateQuery = AccountTransaction::query()
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('direction', AccountTransaction::DIRECTION_DEBIT)
            ->whereIn('source_type', [AccountTransaction::SOURCE_SALE, AccountTransaction::SOURCE_CUSTOMER_PAYMENT])
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($paymentsByDateQuery, $shopFilter['shop_ids']);
        $paymentsByDate = $paymentsByDateQuery
            ->select(DB::raw('DATE(transaction_date) as date'), DB::raw('SUM(amount) as paid'))
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('paid', 'date');

        // Merge: daily breakdown with paid from payment transactions; due = total - paid per day
        $allDates = $ordersByDate->keys()->merge($paymentsByDate->keys())->unique()->sort()->values();
        $dailyBreakdown = $allDates->map(function ($date) use ($ordersByDate, $paymentsByDate) {
            $orderRow = $ordersByDate->get($date);
            $total = $orderRow ? (float) $orderRow->total : 0;
            $paid = (float) ($paymentsByDate[$date] ?? 0);
            return (object) [
                'date' => $date,
                'total' => $total,
                'paid' => $paid,
                'due' => max(0, $total - $paid),
                'count' => $orderRow ? (int) $orderRow->count : 0,
            ];
        });

        // Summary: totalDue = totalRevenue - totalPaid
        $totalRevenue = (clone $ordersQuery)->sum('total');
        $totalPaidQuery = AccountTransaction::query()
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('direction', AccountTransaction::DIRECTION_DEBIT)
            ->whereIn('source_type', [AccountTransaction::SOURCE_SALE, AccountTransaction::SOURCE_CUSTOMER_PAYMENT])
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($totalPaidQuery, $shopFilter['shop_ids']);
        $totalPaid = (float) $totalPaidQuery->sum('amount');
        $totalDue = max(0, $totalRevenue - $totalPaid);
        $totalOrders = (clone $ordersQuery)->count();

        // Paginated orders (chronological: oldest first)
        $orders = $ordersQuery->orderBy('order_date')->orderBy('id')->paginate($row)->appends($request->query());

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
        // Total Paid:
        // - When no customer is selected: payment receipts within date filter (cash/bank debits from sales + customer payments)
        // - When a customer is selected: customer-account credits for that customer (sale + customer_payment) in date range
        if ($selectedCustomerId) {
            $totalPaidQuery = AccountTransaction::query()
                ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
                ->where('account_ref_id', $selectedCustomerId)
                ->where('direction', AccountTransaction::DIRECTION_CREDIT)
                ->whereIn('source_type', [AccountTransaction::SOURCE_SALE, AccountTransaction::SOURCE_CUSTOMER_PAYMENT])
                ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
            $this->applyShopFilter($totalPaidQuery, $shopFilter['shop_ids']);
        } else {
            $totalPaidQuery = AccountTransaction::query()
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->where('direction', AccountTransaction::DIRECTION_DEBIT)
                ->whereIn('source_type', [AccountTransaction::SOURCE_SALE, AccountTransaction::SOURCE_CUSTOMER_PAYMENT])
                ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
            $this->applyShopFilter($totalPaidQuery, $shopFilter['shop_ids']);
        }
        $totalPaid = (float) $totalPaidQuery->sum('amount');
        $totalDue = max(0, $totalRevenue - $totalPaid);

        // Get selected customer for display
        $selectedCustomer = $selectedCustomerId ? \App\Models\Customer::find($selectedCustomerId) : null;

        // Orders grid: one customer selected => that customer's orders; otherwise all orders in date range
        $customerOrdersQuery = Order::with(['customer', 'shop.parent'])
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        if ($selectedCustomerId) {
            $customerOrdersQuery->where('customer_id', $selectedCustomerId);
        }
        $this->applyShopFilter($customerOrdersQuery, $shopFilter['shop_ids']);
        $customerOrders = $customerOrdersQuery->orderByDesc('order_date')->orderByDesc('id')
            ->paginate($row, ['*'], 'orders_page')->appends($request->except('orders_page'));

        // Payments grid: one customer selected => that customer's payments; otherwise all customer payments in date range
        $customerPaymentsQuery = AccountTransaction::query()
            ->with('customer')
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('direction', AccountTransaction::DIRECTION_CREDIT)
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        if ($selectedCustomerId) {
            $customerPaymentsQuery->where('account_ref_id', $selectedCustomerId);
        }
        $this->applyShopFilter($customerPaymentsQuery, $shopFilter['shop_ids']);
        $customerPayments = $customerPaymentsQuery->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate($row, ['*'], 'payments_page')->appends($request->except('payments_page'));

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
            'customerOrders',
            'customerPayments',
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

        // Product sales summary (cost: order_details.cost_per_unit, fallback to product.buying_price when null/0)
        $productSalesQuery = OrderDetails::with(['product', 'order.shop.parent'])
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereIn('order_id', $orderIds)
            ->select(
                'order_details.product_id',
                DB::raw('SUM(order_details.quantity) as total_quantity'),
                DB::raw('SUM(order_details.total) as total_revenue'),
                DB::raw('SUM(order_details.quantity * COALESCE(COALESCE(NULLIF(order_details.cost_per_unit, 0), products.buying_price), 0)) as total_cost'),
                DB::raw('COUNT(DISTINCT order_details.order_id) as order_count')
            )
            ->groupBy('order_details.product_id');

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

        // Total cost (same fallback: cost_per_unit or product.buying_price) for total profit
        $totalCostQuery = OrderDetails::query()
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereIn('order_id', $orderIds);
        if ($selectedProductId) {
            $totalCostQuery->where('order_details.product_id', $selectedProductId);
        }
        $totalCost = (float) $totalCostQuery->sum(
            DB::raw('order_details.quantity * COALESCE(COALESCE(NULLIF(order_details.cost_per_unit, 0), products.buying_price), 0)')
        );
        $totalProfit = $totalRevenue - $totalCost;

        // Get total count for pagination (without ORDER BY to avoid SQL errors)
        $totalCount = (clone $productSalesQuery)->get()->count();
        
        // Get current page
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $row;
        
        // Apply ordering and get results manually
        $productSalesItems = $productSalesQuery
            ->orderByRaw('SUM(order_details.total) DESC')
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
            'totalProfit',
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
