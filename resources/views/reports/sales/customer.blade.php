@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Customer Sales Report</h4>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                        <i class="ri-arrow-left-line mr-1"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="col-lg-12 mb-3">
            <div class="card">
                <div class="card-body">
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
                                <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                <select class="form-control" name="customer_id" id="customer_id" required>
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
                                <button type="button" class="btn btn-info ml-2" onclick="window.print()">
                                    <i class="ri-printer-line mr-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="col-lg-12 mb-3">
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Customers</p>
                            <h4 class="mb-0">{{ number_format($totalCustomers, 0) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Revenue</p>
                            <h4 class="mb-0">{{ number_format($totalRevenue, 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Paid</p>
                            <h4 class="mb-0">{{ number_format($totalPaid, 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Due</p>
                            <h4 class="mb-0">{{ number_format($totalDue, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Sales Table -->
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
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
                        <table class="table table-striped">
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
                                    <th>Credit Amount</th>
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
                                    <td>{{ number_format($customerSale->total_paid, 2) }}</td>
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
    </div>
</div>
@endsection

@section('specificpagestyles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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

    // Auto-submit form when customer is selected
    $('#customer_id').on('change', function() {
        if ($(this).val()) {
            $('#filterForm').submit();
        }
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
