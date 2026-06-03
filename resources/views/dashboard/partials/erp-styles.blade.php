<style>
:root {
    --erp-dash-bg: #f4f6f9;
    --erp-card-bg: #ffffff;
    --erp-card-border: rgba(67, 89, 113, 0.12);
    --erp-text: #32475c;
    --erp-text-muted: #697a8d;
    --erp-shadow: 0 0.125rem 0.5rem rgba(67, 89, 113, 0.12);
    --erp-shadow-hover: 0 0.35rem 1rem rgba(67, 89, 113, 0.18);
    --erp-radius: 0.5rem;
}
[data-theme="dark"] {
    --erp-dash-bg: #1e2230;
    --erp-card-bg: #283046;
    --erp-card-border: rgba(255, 255, 255, 0.08);
    --erp-text: #e7eaf0;
    --erp-text-muted: #a1acb8;
    --erp-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.35);
    --erp-shadow-hover: 0 0.35rem 1rem rgba(0, 0, 0, 0.45);
}
.erp-dashboard {
    background: var(--erp-dash-bg);
    margin: -0.5rem -1rem 0;
    padding: 1rem 1rem 2rem;
    color: var(--erp-text);
}
@media (min-width: 992px) {
    .erp-dashboard { margin: -0.5rem -1.5rem 0; padding: 1.25rem 1.5rem 2.5rem; }
}
.erp-dashboard .card {
    background: var(--erp-card-bg);
    border: 1px solid var(--erp-card-border);
    border-radius: var(--erp-radius);
    box-shadow: var(--erp-shadow);
    color: var(--erp-text);
}
.erp-dashboard .text-muted { color: var(--erp-text-muted) !important; }
.erp-filter-card { border-left: 3px solid #0d6efd !important; }
.erp-filter-pills .btn { font-size: 0.8125rem; padding: 0.35rem 0.85rem; border-radius: 2rem; }
.erp-kpi-card {
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-top: 3px solid transparent !important;
}
.erp-kpi-card:hover { transform: translateY(-2px); box-shadow: var(--erp-shadow-hover); }
.erp-kpi-card .kpi-icon {
    width: 44px; height: 44px; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
}
.erp-kpi-card .kpi-value { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
.erp-kpi-card .kpi-trend { font-size: 0.75rem; }
.erp-kpi-card.border-primary { border-top-color: #0d6efd !important; }
.erp-kpi-card.border-success { border-top-color: #198754 !important; }
.erp-kpi-card.border-info { border-top-color: #0dcaf0 !important; }
.erp-kpi-card.border-warning { border-top-color: #ffc107 !important; }
.erp-kpi-card.border-danger { border-top-color: #dc3545 !important; }
.erp-kpi-card.border-secondary { border-top-color: #6c757d !important; }
.erp-kpi-card.border-dark { border-top-color: #212529 !important; }
.erp-recent-tables-row { margin-left: 0; margin-right: 0; }
.erp-recent-tables-row > [class*="col-"] { padding-left: 8px; padding-right: 8px; }
.erp-recent-table-card { min-height: 320px; }
.erp-recent-table-card .card-header {
    border-bottom: 0;
    border-radius: var(--erp-radius) var(--erp-radius) 0 0;
}
.erp-recent-header-invoices {
    background-color: #FF4D6B !important;
}
.erp-recent-header-invoices h6,
.erp-recent-header-invoices a {
    color: #fff !important;
}
.erp-recent-header-invoices a:hover {
    color: rgba(255, 255, 255, 0.85) !important;
}
.erp-recent-header-purchases {
    background-color: #FFDE73 !important;
}
.erp-recent-header-purchases h6,
.erp-recent-header-purchases a {
    color: #32475c !important;
}
.erp-recent-header-purchases a:hover {
    color: #1a2332 !important;
}
.erp-recent-header-expenses {
    background-color: #8E32E9 !important;
}
.erp-recent-header-expenses h6,
.erp-recent-header-expenses a {
    color: #fff !important;
}
.erp-recent-header-expenses a:hover {
    color: rgba(255, 255, 255, 0.85) !important;
}
.erp-dashboard .card > .card-header[class*="erp-widget-header"],
.erp-recent-table-card .card-header[class*="erp-recent-header"] {
    border-bottom: 0;
    border-radius: var(--erp-radius) var(--erp-radius) 0 0;
}
.erp-widget-header-sales-trend { background-color: #8E32E9 !important; }
.erp-widget-header-sales-trend h6,
.erp-widget-header-sales-trend h6 small,
.erp-widget-header-sales-trend a { color: #fff !important; }
.erp-widget-header-sales-trend h6 small { opacity: 0.9; }
.erp-widget-header-revenue-expense { background-color: #17A2B8 !important; }
.erp-widget-header-revenue-expense h6,
.erp-widget-header-revenue-expense a { color: #fff !important; }
.erp-widget-header-payment-methods { background-color: #1BCFB4 !important; }
.erp-widget-header-payment-methods h6,
.erp-widget-header-payment-methods a { color: #fff !important; }
.erp-widget-header-low-stock { background-color: #FF4D6B !important; }
.erp-widget-header-low-stock h6,
.erp-widget-header-low-stock a { color: #fff !important; }
.erp-widget-header-low-stock a:hover { color: rgba(255, 255, 255, 0.85) !important; }
.erp-widget-header-recent-sales { background-color: #38CE3C !important; }
.erp-widget-header-recent-sales h6,
.erp-widget-header-recent-sales a { color: #fff !important; }
.erp-widget-header-revenue-cost { background-color: #844FC1 !important; }
.erp-widget-header-revenue-cost h6,
.erp-widget-header-revenue-cost small { color: #fff !important; }
.erp-widget-header-revenue-cost small { opacity: 0.9; }
.erp-widget-header-top-products { background-color: #21BF06 !important; }
.erp-widget-header-top-products h6 { color: #fff !important; }
.erp-widget-header-top-customers { background-color: #71C02B !important; }
.erp-widget-header-top-customers h6 { color: #fff !important; }
.erp-recent-table-card .table-responsive { max-height: 360px; overflow-y: auto; }
.erp-recent-table-card .erp-dash-table { width: 100%; table-layout: auto; }
.erp-recent-table-card .erp-dash-table td,
.erp-recent-table-card .erp-dash-table th { white-space: nowrap; }
.erp-recent-table-card .erp-dash-table .cell-wrap {
    white-space: normal;
    max-width: none;
}
.erp-today-overview-card { overflow: hidden; }
.erp-today-stat { text-align: center; padding: 0.75rem 0.25rem; border-right: 1px solid rgba(255, 255, 255, 0.35); }
.erp-today-stat:last-child { border-right: 0; }
.erp-today-stat .val { font-size: 1.1rem; font-weight: 700; }
.erp-today-stat .lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.03em; }
.erp-today-stat-invoices { background-color: #198754; }
.erp-today-stat-invoices .val,
.erp-today-stat-invoices .lbl { color: #fff !important; }
.erp-today-stat-purchases { background-color: #0DCAF0; }
.erp-today-stat-purchases .val,
.erp-today-stat-purchases .lbl { color: #1a2332 !important; }
.erp-today-stat-expenses { background-color: #AB2E3C; }
.erp-today-stat-expenses .val,
.erp-today-stat-expenses .lbl { color: #fff !important; }
.erp-today-stat-returns { background-color: #AF1763; }
.erp-today-stat-returns .val,
.erp-today-stat-returns .lbl { color: #fff !important; }
.erp-today-stat-payments { background-color: #71C02B; }
.erp-today-stat-payments .val,
.erp-today-stat-payments .lbl { color: #fff !important; }
.erp-widget-list { max-height: 280px; overflow-y: auto; }
.erp-dash-table { font-size: 0.8125rem; margin-bottom: 0; }
.erp-dash-table thead th {
    background: #eef3f8; border-bottom: 1px solid var(--erp-card-border);
    font-weight: 600; white-space: nowrap; padding: 0.5rem 0.65rem;
}
[data-theme="dark"] .erp-dash-table thead th { background: rgba(255,255,255,0.06); }
.erp-dash-table td { padding: 0.45rem 0.65rem; vertical-align: middle; }
.erp-empty-state { text-align: center; padding: 1.5rem 1rem; color: var(--erp-text-muted); font-size: 0.875rem; }
.erp-empty-state i { font-size: 2rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }
.chart-wrap { min-height: 300px; }
.status-badge { font-size: 0.7rem; padding: 0.2em 0.55em; }
.erp-dashboard.is-loading { opacity: 0.65; pointer-events: none; }
</style>
