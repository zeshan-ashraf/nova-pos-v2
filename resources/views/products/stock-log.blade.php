@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">
                        {{ $product->product_name }}
                        @if(!empty($product->product_code))
                            ({{ $product->product_code }})
                        @endif
                    </h4>
                    <p class="mb-0 text-muted">Stock movement history for this product (source: stock_logs).</p>
                </div>
                <div>
                    <a href="{{ route('products.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Products</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Controls</h6>
                </div>
                <div class="card-body pt-0">
                    <form id="stockMovementByProductForm" action="{{ route('order.stockLog', $product->id) }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label for="per_page" class="form-label">Per page</label>
                                <select class="form-control" name="per_page" id="per_page">
                                    <option value="25" {{ request('per_page') == '25' ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ request('per_page', '50') == '50' ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                                    <option value="250" {{ request('per_page') == '250' ? 'selected' : '' }}>250</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line mr-1"></i> Refresh</button>
                                <a id="exportExcelLink" class="btn btn-success ml-2" href="#">
                                    <i class="ri-file-excel-2-line mr-1"></i> Export Excel
                                </a>
                            </div>
                        </div>
                        <input type="hidden" id="product_id" value="{{ $product->id }}">
                        <input type="hidden" id="sortParam" value="id">
                        <input type="hidden" id="orderParam" value="desc">
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <h5 class="mb-0">Movements</h5>
                </div>
                <div class="card-body">
                    <div id="loading" class="text-center py-4 text-muted">Loading...</div>
                    <div id="error" class="alert alert-danger" style="display: none;"></div>
                    <div class="table-responsive" id="tableWrap" style="display: none;">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Type</th>
                                    <th class="text-right">Qty IN</th>
                                    <th class="text-right">Qty OUT</th>
                                    <th class="text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="reportBody"></tbody>
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="3" class="text-right">Total</td>
                                    <td class="text-right" id="totalQtyIn">0</td>
                                    <td class="text-right" id="totalQtyOut">0</td>
                                    <td class="text-right" id="totalBalance">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <nav id="paginationWrap" class="mt-3" style="display: none;" aria-label="Report pagination"></nav>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('specificpagescripts')
<script>
(function () {
    const form = document.getElementById('stockMovementByProductForm');
    const loading = document.getElementById('loading');
    const error = document.getElementById('error');
    const tableWrap = document.getElementById('tableWrap');
    const reportBody = document.getElementById('reportBody');
    const paginationWrap = document.getElementById('paginationWrap');
    const productId = document.getElementById('product_id').value;
    const exportExcelLink = document.getElementById('exportExcelLink');

    function getQueryParams(page, forExport) {
        const fd = new FormData(form);
        const params = new URLSearchParams();

        params.set('date_filter', 'all');
        params.set('product_id', productId);
        params.set('per_page', fd.get('per_page') || '50');
        params.set('sort', document.getElementById('sortParam').value || 'id');
        params.set('order', document.getElementById('orderParam').value || 'desc');

        if (forExport) {
            params.set('format', 'csv');
            params.set('export', 'csv');
        } else {
            params.set('format', 'json');
        }
        if (page) {
            params.set('page', String(page));
        }

        return params.toString();
    }

    function formatDate(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatReferenceCell(row) {
        const text = row.reference != null && row.reference !== '' ? String(row.reference) : '-';
        const escText = escapeHtml(text);
        if (row.reference_url) {
            return '<a href="' + escapeHtml(row.reference_url) + '" target="_blank" rel="noopener noreferrer">' + escText + '</a>';
        }
        return escText;
    }

    function renderTable(data) {
        reportBody.innerHTML = '';

        const totalQtyInEl = document.getElementById('totalQtyIn');
        const totalQtyOutEl = document.getElementById('totalQtyOut');
        const totalBalanceEl = document.getElementById('totalBalance');
        let totalQtyIn = 0;
        let totalQtyOut = 0;
        let lastBalance = 0;

        if (!data.data || data.data.length === 0) {
            reportBody.innerHTML = '<tr><td colspan="6" class="text-center">No movements found for this product.</td></tr>';
            totalQtyInEl.textContent = '0';
            totalQtyOutEl.textContent = '0';
            totalBalanceEl.textContent = '0';
            return;
        }

        data.data.forEach(function (row) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + formatDate(row.date) + '</td>' +
                '<td>' + formatReferenceCell(row) + '</td>' +
                '<td>' + escapeHtml(row.movement_type || '-') + '</td>' +
                '<td class="text-right">' + (row.qty_in > 0 ? row.qty_in : '-') + '</td>' +
                '<td class="text-right">' + (row.qty_out > 0 ? row.qty_out : '-') + '</td>' +
                '<td class="text-right">' + (row.balance ?? '-') + '</td>';
            reportBody.appendChild(tr);

            totalQtyIn += parseFloat(row.qty_in || 0);
            totalQtyOut += parseFloat(row.qty_out || 0);
            lastBalance = parseFloat(row.balance || 0);
        });
        totalQtyInEl.textContent = String(totalQtyIn);
        totalQtyOutEl.textContent = String(totalQtyOut);
        totalBalanceEl.textContent = String(lastBalance);
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
        paginationWrap.querySelectorAll('.page-link').forEach(function (a) {
            a.addEventListener('click', function (e) {
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

        const url = '{{ route("reports.inventory.stock-movement") }}?' + getQueryParams(page, false);
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                loading.style.display = 'none';
                renderTable(res);
                if (res.meta) renderPagination(res.meta);
                tableWrap.style.display = 'block';
            })
            .catch(function (err) {
                loading.style.display = 'none';
                error.textContent = err.message || 'Failed to load report.';
                error.style.display = 'block';
            });
    }

    function exportExcel() {
        const url = '{{ route("reports.inventory.stock-movement") }}?' + getQueryParams(1, true);
        exportExcelLink.href = url;
        window.location.href = url;
    }

    exportExcelLink.addEventListener('click', function (e) {
        e.preventDefault();
        exportExcel();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadReport(1);
        return false;
    });

    loadReport(1);
})();
</script>
@endsection
