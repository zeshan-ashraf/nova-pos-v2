@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Stock Movement Report</h4>
                    <p class="mb-0 text-muted">Chronological inventory changes per product (source: stock_logs only).</p>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form id="stockMovementFilterForm" action="{{ route('reports.inventory.stock-movement') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label for="date_filter" class="form-label">Date</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="this_week">This Week</option>
                                    <option value="last_week">Last Week</option>
                                    <option value="this_month" selected>This Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="this_year">This Year</option>
                                    <option value="last_year">Last Year</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div class="col-md-2" id="start_date_group" style="display: none;">
                                <label for="start_date" class="form-label">Start</label>
                                <input type="date" class="form-control" name="start_date" id="start_date">
                            </div>
                            <div class="col-md-2" id="end_date_group" style="display: none;">
                                <label for="end_date" class="form-label">End</label>
                                <input type="date" class="form-control" name="end_date" id="end_date">
                            </div>
                            @if(auth()->user()->shop_id == null)
                            <div class="col-md-2">
                                <label for="shop_id" class="form-label">Shop</label>
                                <select class="form-control" name="shop_id" id="shop_id">
                                    <option value="all" {{ ($shopFilter['selected_shop_id'] ?? '') == 'all' ? 'selected' : '' }}>All Shops</option>
                                    @foreach($shopFilter['shops'] ?? [] as $shop)
                                    <option value="{{ $shop->id }}" {{ ($shopFilter['selected_shop_id'] ?? '') == $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-2">
                                <label for="movement_type" class="form-label">Type</label>
                                <select class="form-control" name="movement_type" id="movement_type">
                                    <option value="">All</option>
                                    <option value="opening">Opening</option>
                                    <option value="purchase">Purchase</option>
                                    <option value="sale">Sale</option>
                                    <option value="purchase_return">Purchase Return</option>
                                    <option value="sale_return">Sale Return</option>
                                    <option value="adjustment">Adjustment</option>
                                    <option value="loss">Loss</option>
                                    <option value="expired">Expired</option>
                                    <option value="theft">Theft</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="product_id" class="form-label">Product</label>
                                <select class="form-control" name="product_id" id="product_id" style="width: 100%;">
                                    @if(isset($selectedProductId) && $selectedProduct)
                                    <option value="{{ $selectedProduct->id }}" selected>
                                        {{ $selectedProduct->product_name }}
                                        @if($selectedProduct->product_code) ({{ $selectedProduct->product_code }}) @endif
                                    </option>
                                    @endif
                                </select>
                                <small class="text-muted">Type to search products in your shop</small>
                            </div>
                            <div class="col-md-2">
                                <label for="per_page" class="form-label">Per page</label>
                                <select class="form-control" name="per_page" id="per_page">
                                    <option value="25">25</option>
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                    <option value="250">250</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line mr-1"></i> Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center"><h5 class="mb-0">Movements</h5></div>
                <div class="card-body">
                    <div id="loading" class="text-center py-4 text-muted">Loading…</div>
                    <div id="error" class="alert alert-danger" style="display: none;"></div>
                    <div class="table-responsive" id="tableWrap" style="display: none;">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th>Reference</th>
                                    <th>Type</th>
                                    <th class="text-right">Qty IN</th>
                                    <th class="text-right">Qty OUT</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="reportBody"></tbody>
                        </table>
                    </div>
                    <nav id="paginationWrap" class="mt-3" style="display: none;" aria-label="Report pagination"></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('stockMovementFilterForm');
    const loading = document.getElementById('loading');
    const error = document.getElementById('error');
    const tableWrap = document.getElementById('tableWrap');
    const reportBody = document.getElementById('reportBody');
    const paginationWrap = document.getElementById('paginationWrap');

    function getQueryParams(page) {
        const fd = new FormData(form);
        const params = new URLSearchParams();
        fd.forEach(function(v, k) { if (v) params.set(k, v); });
        params.set('format', 'json');
        if (page) params.set('page', page);
        return params.toString();
    }

    function formatDate(iso) {
        if (!iso) return '–';
        const d = new Date(iso);
        return d.toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
    }

    function renderTable(data) {
        reportBody.innerHTML = '';
        if (!data.data || data.data.length === 0) {
            reportBody.innerHTML = '<tr><td colspan="8" class="text-center">No movements for the selected filters.</td></tr>';
            return;
        }
        data.data.forEach(function(row) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + formatDate(row.date) + '</td>' +
                '<td>' + (row.product_name || '–') + '</td>' +
                '<td>' + (row.product_code || '–') + '</td>' +
                '<td>' + (row.reference || '–') + '</td>' +
                '<td>' + (row.movement_type || '–') + '</td>' +
                '<td class="text-right">' + (row.qty_in > 0 ? row.qty_in : '–') + '</td>' +
                '<td class="text-right">' + (row.qty_out > 0 ? row.qty_out : '–') + '</td>' +
                '<td class="text-right">' + (row.balance ?? '–') + '</td>';
            reportBody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        if (!meta || meta.last_page <= 1) {
            paginationWrap.style.display = 'none';
            return;
        }
        paginationWrap.style.display = 'block';
        let html = '<ul class="pagination pagination-sm mb-0">';
        for (let i = 1; i <= meta.last_page; i++) {
            const active = i === meta.current_page ? ' active' : '';
            html += '<li class="page-item' + active + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        html += '</ul>';
        paginationWrap.innerHTML = html;
        paginationWrap.querySelectorAll('.page-link').forEach(function(a) {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                loadReport(parseInt(a.getAttribute('data-page'), 10));
            });
        });
    }

    function loadReport(page) {
        page = page || 1;
        loading.style.display = 'block';
        error.style.display = 'none';
        tableWrap.style.display = 'none';
        paginationWrap.style.display = 'none';

        const params = getQueryParams(page);
        const url = '{{ route("reports.inventory.stock-movement") }}?' + params;

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                loading.style.display = 'none';
                renderTable(res);
                if (res.meta) renderPagination(res.meta);
                tableWrap.style.display = 'block';
            })
            .catch(function(err) {
                loading.style.display = 'none';
                error.textContent = err.message || 'Failed to load report.';
                error.style.display = 'block';
            });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport(1);
        return false;
    });

    function toggleCustomDates() {
        var f = document.getElementById('date_filter').value;
        document.getElementById('start_date_group').style.display = f === 'custom' ? 'block' : 'none';
        document.getElementById('end_date_group').style.display = f === 'custom' ? 'block' : 'none';
    }
    window.toggleCustomDates = toggleCustomDates;

    loadReport(1);
})();
</script>
@endsection

@section('specificpagestyles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#product_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Type to search product...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("api.reports.inventory.products.search") }}',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                var data = { q: params.term || '', page: params.page || 1 };
                var shopEl = document.getElementById('shop_id');
                if (shopEl && shopEl.value && shopEl.value !== 'all') data.shop_id = shopEl.value;
                return data;
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                if (!data || !data.results) {
                    return { results: [], pagination: { more: false } };
                }
                return {
                    results: data.results.map(function(item) {
                        return { id: item.id, text: item.text, name: item.name, code: item.code };
                    }),
                    pagination: data.pagination || { more: false }
                };
            },
            cache: true
        }
    });
});
</script>
@endsection
