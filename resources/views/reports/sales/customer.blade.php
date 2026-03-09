@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Customer Sales Report</h4>
                </div>
                <div class="d-print-none">
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                        <i class="ri-arrow-left-line mr-1"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="col-lg-12 mb-3 d-print-none">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.sales.customer') }}" method="GET" id="filterForm">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                                    <option value="today" {{ $dateRange['date_filter'] == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $dateRange['date_filter'] == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $dateRange['date_filter'] == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $dateRange['date_filter'] == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $dateRange['date_filter'] == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $dateRange['date_filter'] == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $dateRange['date_filter'] == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $dateRange['date_filter'] == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $dateRange['date_filter'] == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="start_date_group" style="display: {{ $dateRange['date_filter'] == 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $dateRange['start_date'] }}">
                            </div>
                            <div class="col-md-3" id="end_date_group" style="display: {{ $dateRange['date_filter'] == 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $dateRange['end_date'] }}">
                            </div>
                            @if(auth()->user()->shop_id == null)
                            <div class="col-md-3">
                                <label for="shop_id" class="form-label">Shop</label>
                                <select class="form-control" name="shop_id" id="shop_id">
                                    <option value="all" {{ $shopFilter['selected_shop_id'] == 'all' ? 'selected' : '' }}>All Shops</option>
                                    @foreach($shopFilter['shops'] as $shop)
                                    <option value="{{ $shop->id }}" {{ $shopFilter['selected_shop_id'] == $shop->id ? 'selected' : '' }}>
                                        {{ $shop->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-3">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select class="form-control" name="customer_id" id="customer_id">
                                    <option value="" {{ !$selectedCustomerId ? 'selected' : '' }}>All Customers</option>
                                    @if($selectedCustomerId && $selectedCustomer)
                                    <option value="{{ $selectedCustomer->id }}" selected>
                                        {{ $selectedCustomer->shopname ?? $selectedCustomer->name }}
                                        @if($selectedCustomer->phone) - {{ $selectedCustomer->phone }} @endif
                                    </option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line mr-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Cards Row 1 -->
        <div class="col-lg-12 mb-3">
            <div class="row sales-kpi-row-1">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi summary-kpi-card card-primary h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-primary-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-user-3-line text-primary" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Customers</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalCustomers, 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi summary-kpi-card card-success h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-success-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-money-dollar-circle-line text-success" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Revenue</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalRevenue, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi summary-kpi-card card-info h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-info-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-wallet-3-line text-info" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Paid</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalPaid, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi summary-kpi-card card-danger h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-danger-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-bill-line text-danger" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Due</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalDue, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Sales Table -->
        <div class="col-lg-12 sales-orders-table-wrap">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Customer Sales Summary</h5>
                    <div>
                        <form action="{{ route('reports.sales.customer') }}" method="GET" class="d-inline">
                            <input type="hidden" name="date_filter" value="{{ $dateRange['date_filter'] }}">
                            <input type="hidden" name="start_date" value="{{ $dateRange['start_date'] }}">
                            <input type="hidden" name="end_date" value="{{ $dateRange['end_date'] }}">
                            @if(auth()->user()->shop_id == null)
                            <input type="hidden" name="shop_id" value="{{ $shopFilter['selected_shop_id'] }}">
                            @endif
                            @if($selectedCustomerId)
                            <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                            @endif
                            <select name="row" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                <option value="10" {{ $row == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ $row == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $row == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $row == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="customerSalesTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Customer Name</th>
                                    <th>Shop Name</th>
                                    <th>Total Orders</th>
                                    <th>Total Sales</th>
                                    <th>Total Paid</th>
                                    <th>Total Due</th>
                                    <th>Credit Limit</th>
                                    <th>Due Amount</th>
                                    <th>Credit Utilization</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customerSales as $customerSale)
                                @php
                                    $customer = $customerSale->customer;
                                    $creditUtilization = $customer && $customer->credit_limit > 0 
                                        ? ($customer->credit_amount / $customer->credit_limit) * 100 
                                        : 0;
                                @endphp
                                <tr>
                                    <td>{{ (($customerSales->currentPage() * $customerSales->perPage()) - $customerSales->perPage()) + $loop->iteration }}</td>
                                    <td>{{ $customer->name ?? 'N/A' }}</td>
                                    <td>{{ $customer->shopname ?? 'N/A' }}</td>
                                    <td>{{ $customerSale->order_count }}</td>
                                    <td>{{ number_format($customerSale->total_sales, 2) }}</td>
                                    <td>{{ number_format($customerSale->total_paid + (($customerPaymentSums ?? [])[$customerSale->customer_id] ?? 0), 2) }}</td>
                                    <td>{{ number_format($customerSale->total_due, 2) }}</td>
                                    <td>{{ number_format($customer->credit_limit ?? 0, 2) }}</td>
                                    <td>{{ number_format($customer->credit_amount ?? 0, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $creditUtilization > 80 ? 'badge-danger' : ($creditUtilization > 50 ? 'badge-warning' : 'badge-success') }}">
                                            {{ number_format($creditUtilization, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No customer sales data found for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $customerSales->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales (Invoices) grid: all orders or selected customer's orders -->
        <div class="col-lg-12 mt-3 sales-orders-table-wrap">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sales (Invoices)</h5>
                    <div>
                        <form action="{{ route('reports.sales.customer') }}" method="GET" class="d-inline">
                            @foreach(request()->except(['orders_page']) as $key => $val)
                                @if(is_array($val))
                                    @foreach($val as $v)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                                @endif
                            @endforeach
                            <select name="row" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                <option value="10" {{ $row == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ $row == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $row == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $row == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="customerOrdersTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    @if(!$selectedCustomerId)
                                    <th>Customer</th>
                                    @endif
                                    <th>Invoice No</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Payment Status</th>
                                    <th>Order Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customerOrders as $order)
                                <tr>
                                    <td>{{ (($customerOrders->currentPage() * $customerOrders->perPage()) - $customerOrders->perPage()) + $loop->iteration }}</td>
                                    @if(!$selectedCustomerId)
                                    <td>{{ optional($order->customer)->name ?? optional($order->customer)->shopname ?? 'N/A' }}</td>
                                    @endif
                                    <td>{{ $order->invoice_no }}</td>
                                    <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('Y-m-d') : '—' }}</td>
                                    <td>{{ number_format($order->total, 2) }}</td>
                                    <td>{{ number_format($order->pay, 2) }}</td>
                                    <td>{{ number_format($order->due, 2) }}</td>
                                    <td>{{ $order->payment_status ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ ($order->order_status ?? '') == 'complete' ? 'badge-success' : 'badge-danger' }}">
                                            {{ $order->order_status ?? '—' }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ $selectedCustomerId ? 8 : 9 }}" class="text-center">No orders found for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($customerOrders->hasPages())
                    <div class="mt-3">
                        {{ $customerOrders->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Payments grid: all customer payments or selected customer's payments -->
        <div class="col-lg-12 mt-3 sales-orders-table-wrap">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Payments</h5>
                    <div>
                        <form action="{{ route('reports.sales.customer') }}" method="GET" class="d-inline">
                            @foreach(request()->except(['payments_page']) as $key => $val)
                                @if(is_array($val))
                                    @foreach($val as $v)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                                @endif
                            @endforeach
                            <select name="row" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                <option value="10" {{ $row == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ $row == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $row == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $row == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="customerPaymentsTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    @if(!$selectedCustomerId)
                                    <th>Customer</th>
                                    @endif
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customerPayments as $payment)
                                <tr>
                                    <td>{{ (($customerPayments->currentPage() * $customerPayments->perPage()) - $customerPayments->perPage()) + $loop->iteration }}</td>
                                    @if(!$selectedCustomerId)
                                    <td>{{ optional($payment->customer)->name ?? optional($payment->customer)->shopname ?? 'N/A' }}</td>
                                    @endif
                                    <td>{{ $payment->transaction_date ? $payment->transaction_date->format('Y-m-d') : '—' }}</td>
                                    <td>{{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ $payment->description ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ $selectedCustomerId ? 4 : 5 }}" class="text-center">No payments found for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($customerPayments->hasPages())
                    <div class="mt-3">
                        {{ $customerPayments->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('specificpagestyles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    @media print {
        @page { margin: 8mm; size: auto; }
        .iq-sidebar, .iq-top-navbar, .iq-footer,
        .report-filter-card, .d-print-none { display: none !important; }
        .wrapper { display: block !important; }
        .content-page { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; }
        body, .wrapper { padding: 0 !important; margin: 0 !important; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .container-fluid { width: 100% !important; max-width: none !important; padding-left: 8px !important; padding-right: 8px !important; }
        .sales-kpi-row-1 { display: flex !important; flex-wrap: nowrap !important; break-inside: avoid; }
        .sales-kpi-row-1 .col-md-3 { flex: 0 0 25% !important; max-width: 25% !important; padding: 0 6px !important; }
        .sales-report-kpi, .sales-report-kpi.card { border: 1px solid #dee2e6 !important; box-shadow: none !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sales-report-kpi .card-body, .sales-report-kpi .iq-icon-box-2 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        .card-header.bg-primary { background: #0d6efd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card-header .form-control, .card-header form { display: none !important; }
        .card-body > .mt-3 { display: none !important; }
        .sales-orders-table-wrap { width: 100vw !important; max-width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; padding-left: 4px !important; padding-right: 4px !important; box-sizing: border-box !important; }
        .sales-orders-table-wrap .card { width: 100% !important; max-width: none !important; }
        .sales-orders-table-wrap .card-body { padding: 4px !important; width: 100% !important; }
        .sales-orders-table-wrap .table-responsive { width: 100% !important; max-width: none !important; overflow: visible !important; }
        #customerSalesTable { width: 100% !important; min-width: 100% !important; max-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; }
        #customerSalesTable th, #customerSalesTable td { border: 1px solid #dee2e6 !important; box-sizing: border-box !important; }
        #customerSalesTable th:nth-child(1), #customerSalesTable td:nth-child(1) { width: 4% !important; }
        #customerSalesTable th:nth-child(2), #customerSalesTable td:nth-child(2) { width: 12% !important; }
        #customerSalesTable th:nth-child(3), #customerSalesTable td:nth-child(3) { width: 12% !important; }
        #customerSalesTable th:nth-child(4), #customerSalesTable td:nth-child(4) { width: 8% !important; }
        #customerSalesTable th:nth-child(5), #customerSalesTable td:nth-child(5) { width: 12% !important; }
        #customerSalesTable th:nth-child(6), #customerSalesTable td:nth-child(6) { width: 10% !important; }
        #customerSalesTable th:nth-child(7), #customerSalesTable td:nth-child(7) { width: 10% !important; }
        #customerSalesTable th:nth-child(8), #customerSalesTable td:nth-child(8) { width: 10% !important; }
        #customerSalesTable th:nth-child(9), #customerSalesTable td:nth-child(9) { width: 10% !important; }
        #customerSalesTable th:nth-child(10), #customerSalesTable td:nth-child(10) { width: 12% !important; }
        .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endsection

@section('specificpagescripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 for customer search
    $('#customer_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Type to search customer...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("api.reports.customers.search") }}',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    q: params.term || '', // search term
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                if (!data || !data.results) {
                    console.error('Invalid response from server:', data);
                    return { results: [], pagination: { more: false } };
                }
                return {
                    results: data.results.map(function(item) {
                        return {
                            id: item.id,
                            text: item.text,
                            name: item.name,
                            shopname: item.shopname,
                            phone: item.phone
                        };
                    }),
                    pagination: data.pagination || { more: false }
                };
            },
            cache: true,
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
            }
        }
    }).on('select2:open', function() {
        // Auto-focus on search input when dropdown opens
        setTimeout(function() {
            const $searchInput = $('.select2-container--open .select2-search__field');
            if ($searchInput.length > 0) {
                $searchInput[0].focus();
            }
        }, 100);
    });

    // Auto-submit form when customer selection changes (including clear)
    $('#customer_id').on('change', function() {
        $('#filterForm').submit();
    });
});

function toggleCustomDates() {
    const dateFilter = document.getElementById('date_filter').value;
    const startDateGroup = document.getElementById('start_date_group');
    const endDateGroup = document.getElementById('end_date_group');
    
    if (dateFilter === 'custom') {
        startDateGroup.style.display = 'block';
        endDateGroup.style.display = 'block';
    } else {
        startDateGroup.style.display = 'none';
        endDateGroup.style.display = 'none';
    }
}
</script>
@endsection
