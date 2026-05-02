@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
/* Same KPI strip / tile styling as orders list (order.index) */
.shop-expenses-page-kpis .shop-expenses-kpi-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.75rem 1rem;
}
.shop-expenses-page-kpis .shop-expenses-kpi-col {
    flex: 0 1 auto;
    width: 100%;
    max-width: 100%;
}
@media (min-width: 576px) and (max-width: 991.98px) {
    .shop-expenses-page-kpis .shop-expenses-kpi-col {
        flex: 1 1 calc((100% - 1rem) / 2);
        min-width: 0;
        max-width: calc((100% - 1rem) / 2);
    }
}
@media (min-width: 992px) {
    .shop-expenses-page-kpis .shop-expenses-kpi-strip {
        flex-wrap: nowrap;
    }
    .shop-expenses-page-kpis .shop-expenses-kpi-col {
        flex: 1 1 0;
        min-width: 0;
        max-width: none;
    }
}
.shop-expenses-page-kpis .shop-expenses-kpi-card {
    position: relative;
    border: 1px solid rgba(30, 41, 59, 0.16);
    border-radius: 14px;
    background: #fff;
    width: 100%;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.9) inset,
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 4px 12px rgba(67, 89, 113, 0.07);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.shop-expenses-page-kpis .shop-expenses-kpi-card:hover {
    border-color: rgba(30, 41, 59, 0.26);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.9) inset,
        0 2px 4px rgba(15, 23, 42, 0.06),
        0 8px 20px rgba(67, 89, 113, 0.1);
}
.shop-expenses-page-kpis .shop-expenses-kpi-card .card-body {
    padding: 0.85rem 1rem 0.75rem;
}
.shop-expenses-page-kpis .shop-expenses-kpi-top {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 0.5rem;
}
.shop-expenses-page-kpis .shop-expenses-kpi-main {
    flex: 1;
    min-width: 0;
}
.shop-expenses-page-kpis .shop-expenses-kpi-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    border: 1px solid rgba(30, 41, 59, 0.08);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) inset;
}
.shop-expenses-page-kpis .shop-expenses-kpi-card--success .shop-expenses-kpi-icon-wrap {
    background: rgba(25, 135, 84, 0.1);
    color: #146c43;
    border-color: rgba(25, 135, 84, 0.22);
}
.shop-expenses-page-kpis .shop-expenses-kpi-card--info .shop-expenses-kpi-icon-wrap {
    background: rgba(13, 202, 240, 0.12);
    color: #0aa2c0;
    border-color: rgba(13, 202, 240, 0.28);
}
.shop-expenses-page-kpis .shop-expenses-kpi-value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.15;
    color: #1e293b;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    word-break: break-word;
}
.shop-expenses-page-kpis .shop-expenses-kpi-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #566a7f;
    margin-bottom: 0.25rem;
    line-height: 1.3;
}
.shop-expenses-page-kpis .shop-expenses-kpi-meta {
    font-size: 0.78rem;
    color: #8592a3;
    line-height: 1.35;
    max-width: 36em;
}
.shop-expenses-page-kpis .shop-expenses-kpi-accent {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    border-radius: 0 0 14px 14px;
}
.shop-expenses-page-kpis .shop-expenses-kpi-card--success .shop-expenses-kpi-accent {
    background: linear-gradient(90deg, #198754, #51cf66);
}
.shop-expenses-page-kpis .shop-expenses-kpi-card--info .shop-expenses-kpi-accent {
    background: linear-gradient(90deg, #17a2b8, #3dd5f3);
}
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif

            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1">Shop Expenses</h4>
                    <p class="text-muted mb-0 small">Reporting only — does not affect accounting or balances.</p>
                </div>
                <div class="d-flex flex-wrap">
                    @can('shop_expense.export')
                    <a href="{{ route('shop-expenses.export.excel', request()->query()) }}" class="btn btn-outline-success mr-2 mb-2">Export Excel</a>
                    <a href="{{ route('shop-expenses.export.pdf', request()->query()) }}" class="btn btn-outline-danger mr-2 mb-2">Export PDF</a>
                    @endcan
                    @can('shop_expense.create')
                        @if(auth()->user()->shop_id)
                        <a href="{{ route('shop-expenses.create') }}" class="btn btn-primary mb-2">Add Shop Expense</a>
                        @endif
                    @endcan
                </div>
            </div>
        </div>

        @php
            $dateRange = $dateRange ?? [];
            $dateFilter = $dateRange['date_filter'] ?? 'all';
            $shopFilter = $shopFilter ?? [];
            $selectedShopId = $shopFilter['selected_shop_id'] ?? 'all';
        @endphp

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('shop-expenses.index') }}" method="get" id="shop-expenses-filter-form">
                        <div class="d-flex flex-wrap align-items-end mb-2">
                            <div class="form-group mr-3 mb-2">
                                <label for="date_filter" class="small mb-0">Date</label>
                                <select class="form-control form-control-sm" name="date_filter" id="date_filter" onchange="toggleShopExpenseCustomDates()">
                                    <option value="today" {{ $dateFilter == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $dateFilter == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $dateFilter == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $dateFilter == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $dateFilter == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $dateFilter == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $dateFilter == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $dateFilter == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $dateFilter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                    <option value="all" {{ $dateFilter == 'all' ? 'selected' : '' }}>All time</option>
                                </select>
                            </div>
                            <div class="form-group mr-3 mb-2" id="shop_expense_start_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="small mb-0">Start Date</label>
                                <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="form-group mr-3 mb-2" id="shop_expense_end_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="small mb-0">End Date</label>
                                <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                            </div>
                            @if (($shopFilter['shops'] ?? collect())->isNotEmpty())
                            <div class="form-group mr-3 mb-2">
                                <label for="shop_id" class="small mb-0">Shop</label>
                                <select class="form-control form-control-sm" name="shop_id" id="shop_id">
                                    <option value="all" {{ $selectedShopId === 'all' ? 'selected' : '' }}>All shops</option>
                                    @foreach ($shopFilter['shops'] as $s)
                                        <option value="{{ $s->id }}" {{ (string) $selectedShopId === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="form-group mr-3 mb-2">
                                <label for="row" class="small mb-0">Per page</label>
                                <select class="form-control form-control-sm" name="row" onchange="this.form.submit()">
                                    @foreach ([10, 25, 50, 100] as $r)
                                        <option value="{{ $r }}" {{ request('row', '50') == $r ? 'selected' : '' }}>{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mr-3 mb-2">
                                <label class="small mb-0" for="search">Search</label>
                                <div class="input-group input-group-sm flex-nowrap">
                                    <input type="text" id="search" class="form-control form-control-sm" name="search" placeholder="Description, category, shop…" value="{{ request('search') }}">
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text bg-primary text-white"><i class="las la-search"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="ri-search-line mr-1"></i> Apply</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @php
            $kpis = $shopExpenseKpis ?? [
                'cash_total' => 0.0,
                'cash_count' => 0,
                'bank_total' => 0.0,
                'bank_count' => 0,
            ];
        @endphp
        <div class="col-lg-12 mb-3 shop-expenses-page-kpis">
            <div class="shop-expenses-kpi-strip">
                <div class="shop-expenses-kpi-col">
                    <div class="card shop-expenses-kpi-card shop-expenses-kpi-card--success h-100">
                        <div class="card-body">
                            <div class="shop-expenses-kpi-top">
                                <div class="shop-expenses-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="shop-expenses-kpi-main">
                                    <div class="shop-expenses-kpi-value">{{ number_format($kpis['cash_total'], 2) }}</div>
                                </div>
                            </div>
                            <div class="shop-expenses-kpi-label">Total cash</div>
                            <div class="shop-expenses-kpi-meta">
                                {{ number_format($kpis['cash_count']) }} {{ $kpis['cash_count'] === 1 ? 'expense' : 'expenses' }} in this total
                            </div>
                        </div>
                        <div class="shop-expenses-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="shop-expenses-kpi-col">
                    <div class="card shop-expenses-kpi-card shop-expenses-kpi-card--info h-100">
                        <div class="card-body">
                            <div class="shop-expenses-kpi-top">
                                <div class="shop-expenses-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="shop-expenses-kpi-main">
                                    <div class="shop-expenses-kpi-value">{{ number_format($kpis['bank_total'], 2) }}</div>
                                </div>
                            </div>
                            <div class="shop-expenses-kpi-label">Total bank</div>
                            <div class="shop-expenses-kpi-meta">
                                {{ number_format($kpis['bank_count']) }} {{ $kpis['bank_count'] === 1 ? 'expense' : 'expenses' }} in this total
                            </div>
                        </div>
                        <div class="shop-expenses-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Shop</th>
                                    <th>Expense</th>
                                    <th>Payment</th>
                                    <th>Bank</th>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($shopExpenses as $row)
                                    <tr>
                                        <td>{{ $row->expense_date?->format('Y-m-d') }}</td>
                                        <td>{{ $row->shop?->name ?? '—' }}</td>
                                        <td>{{ $row->expense?->expense_title ?? '—' }}</td>
                                        <td>{{ ucfirst($row->payment_type) }}</td>
                                        <td>{{ $row->payment_type === 'bank' ? ($row->bank?->name ?? '—') : '—' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($row->description ?? '', 80) }}</td>
                                        <td class="text-right">{{ number_format((float) $row->amount, 2) }}</td>
                                        <td class="text-right text-nowrap">
                                            @can('shop_expense.edit')
                                            <a href="{{ route('shop-expenses.edit', $row) }}" class="btn btn-sm btn-primary">Edit</a>
                                            @endcan
                                            @can('shop_expense.delete')
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-danger"
                                                onclick="showDeleteShopExpenseModal('{{ route('shop-expenses.destroy', $row) }}')">
                                                Delete
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No shop expenses found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($shopExpenses->hasPages())
                        <div class="card-footer">{{ $shopExpenses->withQueryString()->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete shop expense confirmation -->
<div class="modal fade" id="deleteShopExpenseModal" tabindex="-1" role="dialog" aria-labelledby="deleteShopExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteShopExpenseModalLabel">Delete shop expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0">This will remove the shop expense record from the list. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteShopExpenseForm" method="post" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleShopExpenseCustomDates() {
    var v = document.getElementById('date_filter').value;
    var show = v === 'custom';
    document.getElementById('shop_expense_start_date_group').style.display = show ? 'block' : 'none';
    document.getElementById('shop_expense_end_date_group').style.display = show ? 'block' : 'none';
}

function showDeleteShopExpenseModal(actionUrl) {
    document.getElementById('deleteShopExpenseForm').setAttribute('action', actionUrl);
    $('#deleteShopExpenseModal').modal('show');
}
</script>
@endsection
