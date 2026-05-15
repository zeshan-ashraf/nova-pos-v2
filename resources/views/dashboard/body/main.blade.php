<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Nova POS APP</title>

        <!-- Favicon -->
        <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}"/>
        <link rel="stylesheet" href="{{ asset('assets/css/backend-plugin.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/backend.css?v=1.0.0') }}">

        <link rel="stylesheet" href="{{ asset('assets/vendor/@fortawesome/fontawesome-free/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/remixicon/fonts/remixicon.css') }}">

        @if (request()->route()->getName() !== 'dashboard')
        <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}">
        @endif

        <style>
            :root {
                --color-table-sortable-header: #FF7E41;
            }
            a.table-sortable-th {
                color: var(--color-table-sortable-header) !important;
                text-decoration: none;
                cursor: pointer;
                white-space: nowrap;
            }
            a.table-sortable-th:hover {
                color: var(--color-table-sortable-header) !important;
                text-decoration: underline;
                opacity: 0.92;
            }
            a.table-sortable-th i {
                color: var(--color-table-sortable-header) !important;
            }
            a.table-sortable-th + i {
                color: var(--color-table-sortable-header) !important;
            }

            .iq-top-navbar .navbar {
                min-height: 64px;
                padding-top: 8px;
                padding-bottom: 8px;
            }

            .iq-sidebar {
                padding-top: 64px;
            }

            .content-page {
               /* padding-top: 85px;*/
            }

            @media (max-width: 991px) {
                .iq-sidebar {
                    padding-top: 90px;
                }

                .content-page {
                    padding-top: 40px;
                }
                /* Prevent horizontal overflow on mobile */
                body, .wrapper {
                    overflow-x: hidden;
                    max-width: 100vw;
                }
                /* Top navbar: hide menu by default; show only when .show is toggled (overrides .d-flex) */
                .iq-top-navbar .navbar .d-flex.align-items-center {
                    flex-wrap: wrap;
                    width: 100%;
                }
                .iq-top-navbar .navbar-toggler {
                    order: 1;
                    margin-left: auto;
                }
                .iq-top-navbar .navbar-collapse {
                    order: 2;
                    flex-basis: 100%;
                    width: 100%;
                    max-height: calc(100vh - 80px);
                    overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                    padding: 0.5rem 0;
                    margin-top: 0.5rem;
                    border-top: 1px solid rgba(0,0,0,0.08);
                }
                .iq-top-navbar .navbar-collapse:not(.show) {
                    display: none !important;
                }
                .iq-top-navbar .navbar-collapse.show {
                    display: flex !important;
                    flex-direction: column !important;
                    align-items: stretch !important;
                    justify-content: flex-start !important;
                }
                .iq-top-navbar .navbar-left-menu,
                .iq-top-navbar .navbar-right-menu {
                    flex-direction: column !important;
                    align-items: stretch !important;
                    padding: 0 !important;
                }
                .iq-top-navbar .navbar-left-menu {
                    border-bottom: 1px solid rgba(0,0,0,0.06);
                    padding-bottom: 0.5rem !important;
                }
                .iq-top-navbar .navbar-right-menu {
                    padding-top: 0.5rem !important;
                }
                .iq-top-navbar .navbar-list .nav-item .nav-link {
                    padding: 0.5rem 1rem !important;
                    display: block;
                }
                .iq-top-navbar .navbar-quick-link {
                    white-space: normal;
                }
            }

            /* Left accent border for summary/KPI cards (used on payables, orders, sales reports) */
            .summary-kpi-card { border-left: 4px solid; }
            .summary-kpi-card.card-primary { border-left-color: #0d6efd; }
            .summary-kpi-card.card-warning { border-left-color: #ffc107; }
            .summary-kpi-card.card-success { border-left-color: #198754; }
            .summary-kpi-card.card-info { border-left-color: #0dcaf0; }
            .summary-kpi-card.card-danger { border-left-color: #dc3545; }
            .summary-kpi-card.card-secondary { border-left-color: #6c757d; }
            .summary-kpi-card.card-dark { border-left-color: #212529; }

            .table .ligth th {
                background-color: #a7e7fc !important;
            }
            /* Alternate row background: list tables + DataTables (tr.odd) */
            .content-page .table:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(odd) td,
            .content-page .table:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(odd) th {
                background-color: #eef3f8 !important;
            }
            .content-page table.dataTable:not(.table-dark) tbody tr.odd td,
            .content-page table.dataTable:not(.table-dark) tbody tr.odd th {
                background-color: #eef3f8 !important;
            }
            /* Row hover — odd rows need :hover matched to zebra specificity or odd color wins */
            .content-page .table tbody tr:hover td,
            .content-page .table tbody tr:hover th {
                background-color: #ffd8c9 !important;
            }
            .content-page .table:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(odd):hover td,
            .content-page .table:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(odd):hover th {
                background-color: #ffd8c9 !important;
            }
            .content-page table.dataTable:not(.table-dark) tbody tr.odd:hover td,
            .content-page table.dataTable:not(.table-dark) tbody tr.odd:hover th {
                background-color: #ffd8c9 !important;
            }
        </style>

        @yield('specificpagestyles')
    </head>
<body>
    <!-- loader Start -->
    {{-- <div id="loading">
        <div id="loading-center"></div>
    </div> --}}
    <!-- loader END -->

    <!-- Wrapper Start -->
    <div class="wrapper">
        @include('dashboard.body.sidebar')

        @include('dashboard.body.navbar')

        <div class="content-page">
            @yield('container')
        </div>
    </div>
    <!-- Wrapper End-->

    @include('dashboard.body.footer')

    <!-- Backend Bundle JavaScript -->
    <script src="{{ asset('assets/js/backend-bundle.min.js') }}"></script>
    <script src="https://kit.fontawesome.com/4c897dc313.js" crossorigin="anonymous"></script>

    @yield('specificpagescripts')

    <!-- App JavaScript -->
    <script src="{{ asset('assets/js/app.js') }}"></script>
    
    <!-- Navbar toggler: single handler so menu stays open/closed (no double-toggle with Bootstrap) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toggler = document.querySelector('.iq-top-navbar .navbar-toggler');
            var collapseEl = document.getElementById('navbarSupportedContent');
            if (toggler && collapseEl) {
                toggler.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    var isShown = collapseEl.classList.contains('show');
                    if (isShown) {
                        collapseEl.classList.remove('show');
                        toggler.setAttribute('aria-expanded', 'false');
                    } else {
                        collapseEl.classList.add('show');
                        toggler.setAttribute('aria-expanded', 'true');
                    }
                }, true);
            }
        });
    </script>
    <!-- Fix for stuck modal backdrops -->
    <script>
        // Remove any stuck modal backdrops on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any modal backdrops that shouldn't be there
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(backdrop) {
                backdrop.remove();
            });
            
            // Remove modal-open class from body if no modals are open
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        });
        
        // Also check periodically for stuck backdrops
        setInterval(function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            const openModals = document.querySelectorAll('.modal.show');
            
            if (openModals.length === 0 && backdrops.length > 0) {
                backdrops.forEach(function(backdrop) {
                    backdrop.remove();
                });
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }, 1000);
    </script>
</body>
</html>
