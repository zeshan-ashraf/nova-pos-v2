<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class ReportController extends Controller
{
    /**
     * Display the reports index page.
     */
    public function index()
    {
        $user = auth()->user();
        
        // Get all report categories and their reports
        $reportCategories = [
            'sales' => [
                'name' => 'Sales Reports',
                'permission' => 'reports.sales',
                'icon' => 'fas fa-chart-line',
                'reports' => [
                    [
                        'name' => 'Sales Summary',
                        'route' => 'reports.sales.summary',
                        'permission' => 'reports.sales-summary',
                        'description' => 'Overview of all sales with totals, payments, and trends'
                    ],
                    [
                        'name' => 'Daily Sales',
                        'route' => 'reports.sales.daily',
                        'permission' => 'reports.daily-sales',
                        'description' => 'Daily sales breakdown with hourly analysis'
                    ],
                    [
                        'name' => 'Customer Sales',
                        'route' => 'reports.sales.customer',
                        'permission' => 'reports.customer-sales',
                        'description' => 'Sales performance by customer'
                    ],
                    [
                        'name' => 'Product Sales',
                        'route' => 'reports.sales.product',
                        'permission' => 'reports.product-sales',
                        'description' => 'Sales performance by product'
                    ],
                ]
            ],
            'purchases' => [
                'name' => 'Purchase Reports',
                'permission' => 'reports.purchases',
                'icon' => 'fas fa-shopping-cart',
                'reports' => [
                    [
                        'name' => 'Purchase Summary',
                        'route' => 'reports.purchases.summary',
                        'permission' => 'reports.purchase-summary',
                        'description' => 'Overview of all purchases with totals and payments'
                    ],
                    [
                        'name' => 'Supplier Purchase',
                        'route' => 'reports.purchases.supplier',
                        'permission' => 'reports.supplier-purchase',
                        'description' => 'Purchase performance by supplier'
                    ],
                    [
                        'name' => 'Product Purchase',
                        'route' => 'reports.purchases.product',
                        'permission' => 'reports.product-purchase',
                        'description' => 'Purchase performance by product'
                    ],
                ]
            ],
            'financial' => [
                'name' => 'Financial Reports',
                'permission' => 'reports.financial',
                'icon' => 'fas fa-dollar-sign',
                'reports' => [
                    [
                        'name' => 'Profit & Loss',
                        'route' => 'reports.financial.profit-loss',
                        'permission' => 'reports.profit-loss',
                        'description' => 'Revenue, expenses, and net profit analysis'
                    ],
                    [
                        'name' => 'Revenue Report',
                        'route' => 'reports.financial.revenue',
                        'permission' => 'reports.revenue',
                        'description' => 'Revenue trends and breakdown by period'
                    ],
                    [
                        'name' => 'Expense Report',
                        'route' => 'reports.financial.expense',
                        'permission' => 'reports.expense',
                        'description' => 'Expense analysis and trends'
                    ],
                    [
                        'name' => 'Cash Flow',
                        'route' => 'reports.financial.cash-flow',
                        'permission' => 'reports.cash-flow',
                        'description' => 'Cash inflow and outflow analysis'
                    ],
                ]
            ],
            'credit' => [
                'name' => 'Credit Reports',
                'permission' => 'reports.credit',
                'icon' => 'fas fa-credit-card',
                'reports' => [
                    [
                        'name' => 'Customer Credit',
                        'route' => 'reports.credit.customer',
                        'permission' => 'reports.customer-credit',
                        'description' => 'Customer credit analysis and aging'
                    ],
                    [
                        'name' => 'Supplier Credit',
                        'route' => 'reports.credit.supplier',
                        'permission' => 'reports.supplier-credit',
                        'description' => 'Supplier credit analysis and aging'
                    ],
                    [
                        'name' => 'Credit Summary',
                        'route' => 'reports.credit.summary',
                        'permission' => 'reports.credit-summary',
                        'description' => 'Overall credit position summary'
                    ],
                ]
            ],
            'inventory' => [
                'name' => 'Inventory Reports',
                'permission' => 'reports.inventory',
                'icon' => 'fas fa-boxes',
                'reports' => [
                    [
                        'name' => 'Stock Report',
                        'route' => 'reports.inventory.stock',
                        'permission' => 'reports.stock',
                        'description' => 'Current stock levels and alerts'
                    ],
                    [
                        'name' => 'Stock Movement',
                        'route' => 'reports.inventory.stock-movement',
                        'permission' => 'reports.stock-movement',
                        'description' => 'Stock in/out movement history'
                    ],
                    [
                        'name' => 'Stock Valuation',
                        'route' => 'reports.inventory.stock-valuation',
                        'permission' => 'reports.stock-valuation',
                        'description' => 'Stock value and valuation analysis'
                    ],
                    [
                        'name' => 'Expired Products',
                        'route' => 'reports.inventory.expired-products',
                        'permission' => 'reports.expired-products',
                        'description' => 'Expired and expiring products'
                    ],
                ]
            ],
            'payment' => [
                'name' => 'Payment Reports',
                'permission' => 'reports.payment',
                'icon' => 'fas fa-money-bill-wave',
                'reports' => [
                    [
                        'name' => 'Payment Collection',
                        'route' => 'reports.payment.collection',
                        'permission' => 'reports.payment-collection',
                        'description' => 'Payments received from customers'
                    ],
                    [
                        'name' => 'Payment Disbursement',
                        'route' => 'reports.payment.disbursement',
                        'permission' => 'reports.payment-disbursement',
                        'description' => 'Payments made to suppliers'
                    ],
                    [
                        'name' => 'Payment Summary',
                        'route' => 'reports.payment.summary',
                        'permission' => 'reports.payment-summary',
                        'description' => 'Overall payment position'
                    ],
                ]
            ],
            'returns' => [
                'name' => 'Return Reports',
                'permission' => 'reports.returns',
                'icon' => 'fas fa-undo-alt',
                'reports' => [
                    [
                        'name' => 'Sale Return',
                        'route' => 'reports.returns.sale-return',
                        'permission' => 'reports.sale-return',
                        'description' => 'Sale returns analysis and trends'
                    ],
                ]
            ],
            'employee' => [
                'name' => 'Employee Reports',
                'permission' => 'reports.employee',
                'icon' => 'fas fa-users',
                'reports' => [
                    [
                        'name' => 'Salary Report',
                        'route' => 'reports.employee.salary',
                        'permission' => 'reports.salary',
                        'description' => 'Salary payments and analysis'
                    ],
                    [
                        'name' => 'Attendance Report',
                        'route' => 'reports.employee.attendance',
                        'permission' => 'reports.attendance',
                        'description' => 'Employee attendance analysis'
                    ],
                ]
            ],
            'comparative' => [
                'name' => 'Comparative Reports',
                'permission' => 'reports.comparative',
                'icon' => 'fas fa-chart-bar',
                'reports' => [
                    [
                        'name' => 'Shop Comparison',
                        'route' => 'reports.comparative.shop-comparison',
                        'permission' => 'reports.shop-comparison',
                        'description' => 'Compare performance across shops'
                    ],
                    [
                        'name' => 'Period Comparison',
                        'route' => 'reports.comparative.period-comparison',
                        'permission' => 'reports.period-comparison',
                        'description' => 'Compare current vs previous period'
                    ],
                ]
            ],
            'executive' => [
                'name' => 'Executive Reports',
                'permission' => 'reports.executive',
                'icon' => 'fas fa-briefcase',
                'reports' => [
                    [
                        'name' => 'Executive Summary',
                        'route' => 'reports.executive.summary',
                        'permission' => 'reports.executive-summary',
                        'description' => 'Key metrics and insights overview'
                    ],
                ]
            ],
        ];

        // Filter categories and reports based on user permissions
        $availableCategories = [];
        foreach ($reportCategories as $key => $category) {
            if ($user->can($category['permission'])) {
                $availableReports = [];
                foreach ($category['reports'] as $report) {
                    if ($user->can($report['permission'])) {
                        $availableReports[] = $report;
                    }
                }
                if (!empty($availableReports)) {
                    $category['reports'] = $availableReports;
                    $availableCategories[$key] = $category;
                }
            }
        }

        return view('reports.index', [
            'categories' => $availableCategories,
        ]);
    }
}
