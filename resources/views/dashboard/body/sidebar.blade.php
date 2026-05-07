
<div class="iq-sidebar sidebar-default ">
    <div class="iq-sidebar-logo d-flex align-items-center justify-content-between">
        <a href="{{ route('dashboard') }}" class="header-logo">
            <img src="{{ auth()->user()->shop?->logo_url ?? asset('assets/images/login/company-logo.png') }}" class="img-fluid rounded-normal light-logo" alt="logo">
        </a>
        <div class="iq-menu-bt-sidebar ml-0">
            <i class="las la-bars wrapper-menu"></i>
        </div>
    </div>
    <div class="data-scrollbar" data-scroll="1">
        <nav class="iq-sidebar-menu">
            <ul id="iq-sidebar-toggle" class="iq-menu">
                <!--<li class="{{ Request::is('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="svg-icon">
                        <svg  class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="ml-4">Dashboards</span>
                    </a>
                </li>-->
                <li class="{{ Request::is('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="svg-icon">
                        <svg  class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="ml-4">Dashboards</span>
                    </a>
                </li>
                @if (\App\Support\MotherShopSuperAdmin::allows(auth()->user()))
                <li class="{{ Request::is('super-admin*') ? 'active' : '' }}">
                    <a href="{{ route('super-admin.dashboard') }}" class="svg-icon">
                        <svg  class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="ml-4">Super Dashboards</span>
                    </a>
                </li>
                @endif
                
                @if (auth()->user()->can('advance.pos.menu'))
                <li class="{{ Request::is('invoice*') ? 'active' : '' }}">
                    <a href="{{ route('invoice.create') }}" class="svg-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="ml-3">Invoices</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->can('shop.menu') || auth()->user()->can('manage_payables'))
                <li>
                    <a href="#shop-menu" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-store"></i>
                        <span class="ml-3">Shop</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="shop-menu" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        @if (auth()->user()->can('shop.menu'))
                        <li class="{{ Request::is('shops*') ? 'active' : '' }}">
                            <a href="{{ route('shops.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Shops</span>
                            </a>
                        </li>
                        @endif
                        @if (auth()->user()->can('manage_payables'))
                        <li class="{{ Request::is('payables*') ? 'active' : '' }}">
                            <a href="{{ route('payables.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Payables</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('reports.menu'))
                <li>
                    <a href="#reports" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-chart-pie"></i>
                        <span class="ml-3">Reports</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="reports" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        <li class="{{ Request::is('reports') && !Request::is('reports/*') ? 'active' : '' }}">
                            <a href="{{ route('reports.index') }}">
                                <i class="fas fa-chart-pie"></i><span>All Reports</span>
                            </a>
                        </li>
                        @if (auth()->user()->can('reports.sales'))
                        <li>
                            <a href="#reports-sales" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-chart-line"></i><span>Sales Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-sales" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.sales-summary'))
                                <li class="{{ Request::is('reports/sales/summary*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.sales.summary') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Sales Summary</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.daily-sales'))
                                <li class="{{ Request::is('reports/sales/daily*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.sales.daily') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Daily Sales</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.customer-sales'))
                                <li class="{{ Request::is('reports/sales/customer*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.sales.customer') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Customer Sales</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.product-sales'))
                                <li class="{{ Request::is('reports/sales/product*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.sales.product') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Product Sales</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.purchases'))
                        <li>
                            <a href="#reports-purchases" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-shopping-cart"></i><span>Purchase Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-purchases" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.purchase-summary'))
                                <li class="{{ Request::is('reports/purchases/summary*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.purchases.summary') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Purchase Summary</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.supplier-purchase'))
                                <li class="{{ Request::is('reports/purchases/supplier*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.purchases.supplier') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Supplier Purchase</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.product-purchase'))
                                <li class="{{ Request::is('reports/purchases/product*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.purchases.product') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Product Purchase</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.financial'))
                        <li>
                            <a href="#reports-financial" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-dollar-sign"></i><span>Financial Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-financial" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.profit-loss'))
                                <li class="{{ Request::is('reports/financial/profit-loss*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.financial.profit-loss') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Profit & Loss</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.revenue'))
                                <li class="{{ Request::is('reports/financial/revenue*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.financial.revenue') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Revenue</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.expense'))
                                <li class="{{ Request::is('reports/financial/expense*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.financial.expense') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Expense</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.cash-flow'))
                                <li class="{{ Request::is('reports/financial/cash-flow*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.financial.cash-flow') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Cash Flow</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.credit'))
                        <li>
                            <a href="#reports-credit" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-credit-card"></i><span>Credit Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-credit" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.customer-credit'))
                                <li class="{{ Request::is('reports/credit/customer*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.credit.customer') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Customer Credit</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.supplier-credit'))
                                <li class="{{ Request::is('reports/credit/supplier*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.credit.supplier') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Supplier Credit</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.credit-summary'))
                                <li class="{{ Request::is('reports/credit/summary*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.credit.summary') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Credit Summary</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.inventory'))
                        <li>
                            <a href="#reports-inventory" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-boxes"></i><span>Inventory Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-inventory" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.stock'))
                                <li class="{{ Request::is('reports/inventory/stock') ? 'active' : '' }}">
                                    <a href="{{ route('reports.inventory.stock') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Stock Report</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.stock-movement'))
                                <li class="{{ Request::is('reports/inventory/stock-movement*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.inventory.stock-movement') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Stock Movement</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.stock-valuation'))
                                <li class="{{ Request::is('reports/inventory/stock-valuation*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.inventory.stock-valuation') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Stock Valuation</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('stock_audit_report_view'))
                                <li class="{{ Request::is('reports/stock-audit*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.stock.audit') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Stock Audit</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.expired-products'))
                                <!--<li class="{{ Request::is('reports/inventory/expired-products*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.inventory.expired-products') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Expired Products</span>
                                    </a>
                                </li>-->
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.payment'))
                        <li>
                            <a href="#reports-payment" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-money-bill-wave"></i><span>Payment Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-payment" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.payment-collection'))
                                <li class="{{ Request::is('reports/payment/collection*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.payment.collection') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Payment Collection</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.payment-disbursement'))
                                <li class="{{ Request::is('reports/payment/disbursement*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.payment.disbursement') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Payment Disbursement</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.payment-summary'))
                                <li class="{{ Request::is('reports/payment/summary*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.payment.summary') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Payment Summary</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.returns'))
                        <li>
                            <a href="#reports-returns" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-undo-alt"></i><span>Return Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-returns" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.sale-return'))
                                <li class="{{ Request::is('reports/returns/sale-return*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.returns.sale-return') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Sale Return</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.employee'))
                        <li>
                            <a href="#reports-employee" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-users"></i><span>Employee Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-employee" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.salary'))
                                <li class="{{ Request::is('reports/employee/salary*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.employee.salary') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Salary Report</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.attendance'))
                                <li class="{{ Request::is('reports/employee/attendance*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.employee.attendance') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Attendance Report</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.comparative'))
                        <li>
                            <a href="#reports-comparative" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-chart-bar"></i><span>Comparative Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-comparative" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.shop-comparison'))
                                <li class="{{ Request::is('reports/comparative/shop-comparison*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.comparative.shop-comparison') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Shop Comparison</span>
                                    </a>
                                </li>
                                @endif
                                @if (auth()->user()->can('reports.period-comparison'))
                                <li class="{{ Request::is('reports/comparative/period-comparison*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.comparative.period-comparison') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Period Comparison</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                        @if (auth()->user()->can('reports.executive'))
                        <li>
                            <a href="#reports-executive" class="collapsed" data-toggle="collapse" aria-expanded="false">
                                <i class="fas fa-briefcase"></i><span>Executive Reports</span>
                                <svg class="svg-icon iq-arrow-right arrow-active" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                                </svg>
                            </a>
                            <ul id="reports-executive" class="iq-submenu collapse" data-parent="#reports" style="">
                                @if (auth()->user()->can('reports.executive-summary'))
                                <li class="{{ Request::is('reports/executive/summary*') ? 'active' : '' }}">
                                    <a href="{{ route('reports.executive.summary') }}">
                                        <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i><span>Executive Summary</span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                <hr>

                @if (auth()->user()->can('orders.menu'))
                <li>
                    <a href="#orders" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-shopping-basket"></i>
                        <span class="ml-3">Orders</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="orders" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        <li class="{{ Request::is('orders/all*') ? 'active' : '' }}">
                            <a href="{{ route('order.index') }}">
                                <i class="fas fa-arrow-right"></i><span>All Orders</span>
                            </a>
                        </li>
                       
                        <li class="{{ Request::is('orders/complete*') ? 'active' : '' }}">
                            <a href="{{ route('order.completeOrders') }}">
                                <i class="fas fa-arrow-right"></i><span>Complete Orders</span>
                            </a>
                        </li>
                        <!--http://nova-pos.test/reports/sales/customer?date_filter=last_month&start_date=2026-02-01&end_date=2026-02-28&customer_id=13<li class="{{ Request::is('pending/due*') ? 'active' : '' }}">
                            <a href="{{ route('order.pendingDue') }}">
                                <i class="fas fa-arrow-right"></i><span>Pending Due</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('orders/pending*') ? 'active' : '' }}">
                            <a href="{{ route('order.pendingOrders') }}">
                                <i class="fas fa-arrow-right"></i><span>Pending Orders</span>
                            </a>
                        </li>
                        <li class="{{ Request::is(['stock*']) ? 'active' : '' }}">
                            <a href="{{ route('order.stockManage') }}">
                                <i class="fas fa-arrow-right"></i><span>Stock Management</span>
                            </a>
                        </li>-->
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('sale-returns.menu'))
                <li class="{{ Request::is('sale-returns*') ? 'active' : '' }}">
                    <a href="{{ route('sale-returns.index') }}" class="svg-icon">
                        <i class="fas fa-undo-alt"></i>
                        <span class="ml-3">Sale Returns</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->can('purchases.menu'))
                <li>
                    <a href="#purchases" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="ml-3">Purchases</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="purchases" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        <li class="{{ Request::is('purchases') && !Request::is('purchases/pending') && !Request::is('purchases/complete') && !Request::is('purchases/create') ? 'active' : '' }}">
                            <a href="{{ route('purchases.index') }}">
                                <i class="fas fa-arrow-right"></i><span>All Purchases</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('purchases/pending*') ? 'active' : '' }}">
                            <a href="{{ route('purchases.pending') }}">
                                <i class="fas fa-arrow-right"></i><span>Pending Purchases</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('purchases/complete*') ? 'active' : '' }}">
                            <a href="{{ route('purchases.complete') }}">
                                <i class="fas fa-arrow-right"></i><span>Complete Purchases</span>
                            </a>
                        </li>
                        @if(auth()->user()->shop_id)
                        <li class="{{ Request::is('shop-purchase-requests*') ? 'active' : '' }}">
                            <a href="{{ route('shop-purchase-requests.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Pending For Approval</span>
                            </a>
                        </li>
                        @endif
                        @if (auth()->user()->can('purchase_returns.create') || auth()->user()->can('purchase_returns.view'))
                        <li class="{{ Request::is('purchase-returns*') ? 'active' : '' }}">
                            <a href="{{ route('purchase-returns.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Purchase Returns</span>
                            </a>
                        </li>
                        @endif
                        @if (auth()->user()->can('transfer_returns.view'))
                        <li class="{{ Request::is('transfer-returns*') ? 'active' : '' }}">
                            <a href="{{ route('transfer-returns.pending') }}">
                                <i class="fas fa-arrow-right"></i><span>Transfer Return Reviews</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('product.menu'))
                <li>
                    <a href="#products" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-boxes"></i>
                        <span class="ml-3">Products</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="products" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        <li class="{{ Request::is(['products']) ? 'active' : '' }}">
                            <a href="{{ route('products.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Products</span>
                            </a>
                        </li>
                        <li class="{{ Request::is(['products/create']) ? 'active' : '' }}">
                            <a href="{{ route('products.create') }}">
                                <i class="fas fa-arrow-right"></i><span>Add Product</span>
                            </a>
                        </li>
                        <li class="{{ Request::is(['categories*']) ? 'active' : '' }}">
                            <a href="{{ route('categories.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Categories</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif
                <hr>

                @if (auth()->user()->can('employee.menu'))
                <li class="{{ Request::is('employees*') ? 'active' : '' }}">
                    <a href="{{ route('employees.index') }}" class="svg-icon">
                        <i class="fas fa-users"></i>
                        <span class="ml-3">Employees</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->can('customer.menu'))
                <li class="{{ Request::is('customers*') ? 'active' : '' }}">
                    <a href="{{ route('customers.index') }}" class="svg-icon">
                        <i class="fas fa-users"></i>
                        <span class="ml-3">Customers</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->can('supplier.menu'))
                <li class="{{ Request::is('suppliers*') ? 'active' : '' }}">
                    <a href="{{ route('suppliers.index') }}" class="svg-icon">
                        <i class="fas fa-users"></i>
                        <span class="ml-3">Suppliers</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->can('customer_payment.menu') || auth()->user()->can('supplier_payment.menu'))
                <li>
                    <a href="#payments" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-exchange-alt"></i>
                        <span class="ml-3">Payments</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="payments" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        @if (auth()->user()->can('customer_payment.menu'))
                        <li class="{{ Request::is('customer-payments*') ? 'active' : '' }}">
                            <a href="{{ route('customer-payments.create') }}">
                                <i class="fas fa-arrow-right"></i><span>Customer Payment</span>
                            </a>
                        </li>
                        @endif
                        @if (auth()->user()->can('supplier_payment.menu'))
                        <li class="{{ Request::is('supplier-payments*') ? 'active' : '' }}">
                            <a href="{{ route('supplier-payments.create') }}">
                                <i class="fas fa-arrow-right"></i><span>Supplier Payment</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('expense.menu') || auth()->user()->can('shop_expense.view'))
                <li>
                    <a href="#expenses" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-boxes"></i>
                        <span class="ml-3">Expenses</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="expenses" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        @if (auth()->user()->can('expense.menu'))
                        <li class="{{ Request::is(['expenses']) && !Request::is('expenses/create') ? 'active' : '' }}">
                            <a href="{{ route('expenses.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Expenses</span>
                            </a>
                        </li>
                        <li class="{{ Request::is(['expenses/create']) ? 'active' : '' }}">
                            <a href="{{ route('expenses.create') }}">
                                <i class="fas fa-arrow-right"></i><span>Add Expense</span>
                            </a>
                        </li>
                        @endif
                        @if (auth()->user()->can('expense-categories.menu'))
                        <li class="{{ Request::is('expense-categories*') ? 'active' : '' }}">
                            <a href="{{ route('expense-categories.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Expense Categories</span>
                            </a>
                        </li>
                        @endif
                        @can('shop_expense.view')
                        <li class="{{ Request::is('shop-expenses*') ? 'active' : '' }}">
                            <a href="{{ route('shop-expenses.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Shop Expenses</span>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('salary.menu'))
                <li>
                    <a href="#advance-salary" class="collapsed" data-toggle="collapse" aria-expanded="false">
                    <i class="fas fa-cash-register"></i>
                        <span class="ml-3">Salary</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="advance-salary" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">

                        <li class="{{ Request::is(['advance-salary', 'advance-salary/*/edit']) ? 'active' : '' }}">
                            <a href="{{ route('advance-salary.index') }}">
                                <i class="fas fa-arrow-right"></i><span>All Advance Salary</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('advance-salary/create*') ? 'active' : '' }}">
                            <a href="{{ route('advance-salary.create') }}">
                                <i class="fas fa-arrow-right"></i><span>Create Advance Salary</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('pay-salary') ? 'active' : '' }}">
                            <a href="{{ route('pay-salary.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Pay Salary</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('pay-salary/history*') ? 'active' : '' }}">
                            <a href="{{ route('pay-salary.payHistory') }}">
                                <i class="fas fa-arrow-right"></i><span>History Pay Salary</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('attendence.menu'))
                <li>
                    <a href="#attendence" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-calendar-day"></i>
                        <span class="ml-3">Attendence</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="attendence" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">

                        <li class="{{ Request::is(['employee/attendence']) ? 'active' : '' }}">
                            <a href="{{ route('attendence.index') }}">
                                <i class="fas fa-arrow-right"></i><span>All Attedence</span>
                            </a>
                        </li>
                        <li class="{{ Request::is('employee/attendence/*') ? 'active' : '' }}">
                            <a href="{{ route('attendence.create') }}">
                                <i class="fas fa-arrow-right"></i><span>Create Attendence</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('roles.menu'))
                <li>
                    <a href="#permission" class="collapsed" data-toggle="collapse" aria-expanded="false">
                        <i class="fas fa-key"></i>
                        <span class="ml-3">Role & Permission</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="permission" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle" style="">
                        <li class="{{ Request::is(['permission', 'permission/create', 'permission/edit/*']) ? 'active' : '' }}">
                            <a href="{{ route('permission.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Permissions</span>
                            </a>
                        </li>
                        <li class="{{ Request::is(['role', 'role/create', 'role/edit/*']) ? 'active' : '' }}">
                            <a href="{{ route('role.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Roles</span>
                            </a>
                        </li>
                        <li class="{{ Request::is(['role/permission*']) ? 'active' : '' }}">
                            <a href="{{ route('rolePermission.index') }}">
                                <i class="fas fa-arrow-right"></i><span>Role in Permissions</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif

                @if (auth()->user()->can('user.menu'))
                <li class="{{ Request::is('users*') ? 'active' : '' }}">
                    <a href="{{ route('users.index') }}" class="svg-icon">
                        <i class="fas fa-users"></i>
                        <span class="ml-3">Users</span>
                    </a>
                </li>
                @endif

                @if (auth()->user()->can('database.menu'))
                <li class="{{ Request::is('database/backup*') ? 'active' : '' }}">
                    <a href="{{ route('backup.index') }}" class="svg-icon">
                        <i class="fas fa-database"></i>
                        <span class="ml-3">Backup Database</span>
                    </a>
                </li>
                @endif
            </ul>
        </nav>
        <div class="p-3"></div>
    </div>
</div>
