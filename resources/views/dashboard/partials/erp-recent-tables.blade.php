<div class="row erp-recent-tables-row mb-3" id="recentTables">
    <div class="col-lg-4 col-md-4 col-12 mb-3">
        <div class="card h-100 erp-recent-table-card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center erp-recent-header-invoices">
                <h6 class="mb-0 font-weight-bold">Recent Invoices</h6>
                <a href="{{ route('order.index') }}" class="small">View all</a>
            </div>
            <div class="card-body p-0 table-responsive">
                @include('dashboard.partials.recent-invoices', ['rows' => $tables['recent_invoices'] ?? [], 'currency' => $currency])
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 col-12 mb-3">
        <div class="card h-100 erp-recent-table-card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center erp-recent-header-purchases">
                <h6 class="mb-0 font-weight-bold">Recent Purchases</h6>
                <a href="{{ route('purchases.index') }}" class="small">View all</a>
            </div>
            <div class="card-body p-0 table-responsive">
                @include('dashboard.partials.recent-purchases', ['rows' => $tables['recent_purchases'] ?? [], 'currency' => $currency])
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 col-12 mb-3">
        <div class="card h-100 erp-recent-table-card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center erp-recent-header-expenses">
                <h6 class="mb-0 font-weight-bold">Recent Expenses</h6>
                <a href="{{ route('shop-expenses.index') }}" class="small">View all</a>
            </div>
            <div class="card-body p-0 table-responsive">
                @include('dashboard.partials.recent-expenses', ['rows' => $tables['recent_expenses'] ?? [], 'currency' => $currency])
            </div>
        </div>
    </div>
</div>
