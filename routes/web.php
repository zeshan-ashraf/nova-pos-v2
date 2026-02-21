<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\StockAdjustController;
use App\Http\Controllers\Dashboard\ProfileController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\CustomerController;
use App\Http\Controllers\Dashboard\CustomerPaymentController;
use App\Http\Controllers\Dashboard\EmployeeController;
use App\Http\Controllers\Dashboard\SupplierController;
use App\Http\Controllers\Dashboard\SupplierPaymentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\PaySalaryController;
use App\Http\Controllers\Dashboard\AttendenceController;
use App\Http\Controllers\Dashboard\AdvanceSalaryController;
use App\Http\Controllers\Dashboard\ShopController;
use App\Http\Controllers\Dashboard\DatabaseBackupController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\PosController;
use App\Http\Controllers\Dashboard\RoleController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\Dashboard\ExpenseController;
use App\Http\Controllers\Dashboard\ExpenseCategoryController;
use App\Http\Controllers\Dashboard\ActiveShopController;
use App\Http\Controllers\Dashboard\ShopSwitchController;
use App\Http\Controllers\Dashboard\SaleReturnController;
use App\Http\Controllers\Dashboard\PurchaseController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\SalesReportController;
use App\Http\Controllers\Dashboard\PurchaseReportController;
use App\Http\Controllers\Dashboard\FinancialReportController;
use App\Http\Controllers\Dashboard\CreditReportController;
use App\Http\Controllers\Dashboard\InventoryReportController;
use App\Http\Controllers\Dashboard\PaymentReportController;
use App\Http\Controllers\Dashboard\ReturnReportController;
use App\Http\Controllers\Dashboard\EmployeeReportController;
use App\Http\Controllers\Dashboard\ComparativeReportController;
use App\Http\Controllers\Dashboard\ExecutiveReportController;
use App\Http\Controllers\Dashboard\SystemResetController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});


// DEFAULT DASHBOARD & PROFILE
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password');

    Route::post('/active-shop', [ActiveShopController::class, 'update'])->name('active-shop.update');

    Route::post('/switch-shop/reset', [ShopSwitchController::class, 'reset'])->name('shop-switch.reset');
    Route::post('/switch-shop/{shop}', [ShopSwitchController::class, 'switch'])->name('shop-switch.switch')->whereNumber('shop');

    // Shop logo: served from storage so it works on live without symlink (public/storage)
    Route::get('/shop-logo/{filename}', function (string $filename) {
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            abort(404);
        }
        $path = 'shops/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';
        return response()->file($fullPath, ['Content-Type' => $mime]);
    })->name('shop.logo')->where('filename', '[a-zA-Z0-9._-]+');
});

// ====== USERS ======
Route::middleware(['permission:user.menu'])->group(function () {
    Route::resource('/users', UserController::class)->except(['show']);
});

// ====== CUSTOMERS ======
Route::middleware(['permission:customer.menu'])->group(function () {
    Route::resource('/customers', CustomerController::class);
});

// ====== SUPPLIERS ======
Route::middleware(['permission:supplier.menu'])->group(function () {
    Route::resource('/suppliers', SupplierController::class);
});

// ====== EMPLOYEES ======
Route::middleware(['permission:employee.menu'])->group(function () {
    Route::resource('/employees', EmployeeController::class);
});

// ====== EMPLOYEE ATTENDENCE ======
Route::middleware(['permission:attendence.menu'])->group(function () {
    Route::resource('/employee/attendence', AttendenceController::class)->except(['show', 'update', 'destroy']);
});

// ====== SALARY EMPLOYEE ======
Route::middleware(['permission:salary.menu'])->group(function () {
    // PaySalary
    Route::resource('/pay-salary', PaySalaryController::class)->except(['show', 'create', 'edit', 'update']);
    Route::get('/pay-salary/history', [PaySalaryController::class, 'payHistory'])->name('pay-salary.payHistory');
    Route::get('/pay-salary/history/{id}', [PaySalaryController::class, 'payHistoryDetail'])->name('pay-salary.payHistoryDetail');
    Route::get('/pay-salary/{id}', [PaySalaryController::class, 'paySalary'])->name('pay-salary.paySalary');

    // Advance Salary
    Route::resource('/advance-salary', AdvanceSalaryController::class)->except(['show']);
});

// ====== PRODUCTS ======
Route::middleware(['permission:product.menu'])->group(function () {
    Route::get('/products/import', [ProductController::class, 'importView'])->name('products.importView');
    Route::post('/products/import', [ProductController::class, 'importStore'])->name('products.importStore');
    Route::get('/products/export', [ProductController::class, 'exportData'])->name('products.exportData');
    Route::get('/products/generate-code', [ProductController::class, 'generateCode'])->name('products.generateCode');
    Route::resource('/products', ProductController::class);
    Route::post('/stock/adjust', [StockAdjustController::class, 'adjust'])->name('stock.adjust');
});

// ====== CATEGORY PRODUCTS ======
Route::middleware(['permission:category.menu'])->group(function () {
    Route::resource('/categories', CategoryController::class);
});

// ====== POS ======
Route::middleware(['permission:pos.menu'])->group(function () {
    Route::get('/pos', [PosController::class,'index'])->name('pos.index');
    Route::post('/pos/add', [PosController::class, 'addCart'])->name('pos.addCart');
    Route::post('/pos/update/{rowId}', [PosController::class, 'updateCart'])->name('pos.updateCart');
    Route::get('/pos/delete/{rowId}', [PosController::class, 'deleteCart'])->name('pos.deleteCart');
    Route::post('/pos/invoice/create', [PosController::class, 'createInvoice'])->name('pos.createInvoice');
    Route::post('/pos/invoice/print', [PosController::class, 'printInvoice'])->name('pos.printInvoice');

    // Create Order
    Route::post('/pos/order', [OrderController::class, 'storeOrder'])->name('pos.storeOrder');
});

// ====== ADVANCE POS ======
Route::middleware(['permission:advance.pos.menu'])->group(function () {
    // Invoice Creation
    Route::get('/invoice/create', [OrderController::class, 'createInvoice'])->name('invoice.create');
    Route::post('/invoice/store', [OrderController::class, 'storeInvoice'])->name('invoice.store');
});

// ====== SHOPS ======
Route::resource('/shops', ShopController::class);

// ====== CUSTOMER CREDIT LOG ======
Route::get('/customers/{customer}/credit-log', [CustomerController::class, 'creditLog'])->name('customers.creditLog');
Route::get('/customers/{customer}/ledger', [CustomerController::class, 'ledger'])->name('customers.ledger');
Route::get('/customers/{customer}/ledger/pdf', [CustomerController::class, 'ledgerPdf'])->name('customers.ledgerPdf');

Route::middleware(['permission:customer_payment.menu'])->group(function () {
    Route::get('/customer-payments/create', [CustomerPaymentController::class, 'create'])->name('customer-payments.create');
    Route::get('/customer-payments/{id}/content', [CustomerPaymentController::class, 'paymentDetailContent'])->name('customer-payments.content');
    Route::post('/customer-payments', [CustomerPaymentController::class, 'store'])->name('customer-payments.store');
    Route::delete('/customer-payments/{id}', [CustomerPaymentController::class, 'destroy'])->name('customer-payments.destroy');
});

Route::get('/suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])->name('suppliers.ledger');
Route::get('/suppliers/{supplier}/ledger/pdf', [SupplierController::class, 'ledgerPdf'])->name('suppliers.ledgerPdf');

Route::middleware(['permission:supplier_payment.menu'])->group(function () {
    Route::get('/supplier-payments/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
    Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
});

// ====== ORDERS ======
Route::middleware(['permission:orders.menu'])->group(function () {
    Route::get('/orders/all', [OrderController::class, 'index'])->name('order.index');
    Route::get('/orders/pending', [OrderController::class, 'pendingOrders'])->name('order.pendingOrders');
    Route::get('/orders/complete', [OrderController::class, 'completeOrders'])->name('order.completeOrders');
    Route::get('/orders/details/{order_id}', [OrderController::class, 'orderDetails'])->name('order.orderDetails');
    Route::get('/orders/details/{order_id}/content', [OrderController::class, 'orderDetailsContent'])->name('order.orderDetailsContent');
    Route::put('/orders/update/status', [OrderController::class, 'updateStatus'])->name('order.updateStatus');
    Route::get('/orders/invoice/download/{order_id}', [OrderController::class, 'invoiceDownload'])->name('order.invoiceDownload');

    Route::get('/api/products/search', [OrderController::class, 'searchProducts'])->name('api.products.search');
    Route::get('/api/categories', [OrderController::class, 'getCategories'])->name('api.categories');
    Route::get('/api/customers/{customerId}/details', [OrderController::class, 'getCustomerDetails'])->name('api.customers.details');

    // Pending Due
    Route::get('/pending/due', [OrderController::class, 'pendingDue'])->name('order.pendingDue');
    Route::get('/order/due/{id}', [OrderController::class, 'orderDueAjax'])->name('order.orderDueAjax');
    Route::post('/update/due', [OrderController::class, 'updateDue'])->name('order.updateDue');

    // Stock Management
    Route::get('/stock', [OrderController::class, 'stockManage'])->name('order.stockManage');
    Route::get('/order/product/{id}', [OrderController::class, 'stockLog'])->name('order.stockLog');
    Route::post('/order/{id}/upload-invoice', [OrderController::class, 'uploadInvoice'])->name('order.uploadInvoice');
    Route::get('/search/{productId}', [OrderController::class, 'search'])->name('stock.search');

    //orders Payment Log
    Route::get('/order/{id}', [OrderController::class, 'paymentLog'])->name('order.paymentLog');
    Route::get('paymentLogs/search/{orderId}', [OrderController::class, 'paymentSearch'])->name('paymentlog.search');
    Route::post('/payment-log/{paymentLogId}/upload-invoice', [OrderController::class, 'uploadInvoice'])->name('paymentlog.uploadInvoice');

    // Order Delete
    Route::get('/orders/{order_id}/delete-info', [OrderController::class, 'getOrderInfoForDelete'])->name('order.deleteInfo');
    Route::delete('/orders/{order_id}', [OrderController::class, 'destroy'])->name('order.destroy');

});

// ====== SALE RETURNS ======
Route::middleware(['permission:sale-returns.menu'])->group(function () {
    Route::get('/sale-returns', [SaleReturnController::class, 'index'])->name('sale-returns.index');
    Route::get('/sale-returns/create', [SaleReturnController::class, 'create'])->name('sale-returns.create');
    Route::post('/sale-returns', [SaleReturnController::class, 'store'])->name('sale-returns.store');
    Route::get('/sale-returns/{return_id}', [SaleReturnController::class, 'show'])->name('sale-returns.show');
    Route::delete('/sale-returns/{return_id}', [SaleReturnController::class, 'destroy'])->name('sale-returns.destroy');
    Route::get('/sale-returns/customer/{customerId}/orders', [SaleReturnController::class, 'getCustomerOrders'])->name('sale-returns.getCustomerOrders');
    Route::get('/sale-returns/order/{orderId}/details', [SaleReturnController::class, 'getOrderDetails'])->name('sale-returns.getOrderDetails');
});

// ====== PURCHASES ======
Route::middleware(['permission:purchases.menu'])->group(function () {
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/pending', [PurchaseController::class, 'pending'])->name('purchases.pending');
    Route::get('/purchases/complete', [PurchaseController::class, 'complete'])->name('purchases.complete');
    Route::get('/purchases/{purchase_id}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::put('/purchases/update/status', [PurchaseController::class, 'updateStatus'])->name('purchases.updateStatus');
    Route::delete('/purchases/{purchase_id}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    Route::get('/api/purchases/products/search', [PurchaseController::class, 'searchProducts'])->name('api.purchases.products.search');
});

// ====== EXPENSE CONTROLLER ======
Route::middleware(['permission:expense.menu'])->group(function () {
    Route::get('/expenses/bulk-create', [ExpenseController::class, 'bulkCreate'])->name('expenses.bulk-create');
    Route::post('/expenses/bulk-create', [ExpenseController::class, 'storeBulk'])->name('expenses.bulk-store');
    Route::resource('/expenses', ExpenseController::class);
    Route::get('/expense-search', [ExpenseController::class, 'expenseSearch'])->name('expenses.search');
});

// ====== EXPENSE CATEGORIES (expenses table CRUD) ======
Route::middleware(['permission:expense-categories.menu'])->group(function () {
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('/expense-categories/{expense_category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::delete('/expense-categories/{expense_category}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');
    Route::get('/expense-categories/{expense_category}/entries', [ExpenseCategoryController::class, 'entries'])->name('expense-categories.entries');
});

// ====== DATABASE BACKUP ======
Route::middleware(['permission:database.menu'])->group(function () {
    Route::get('/database/backup', [DatabaseBackupController::class, 'index'])->name('backup.index');
    Route::get('/database/backup/now', [DatabaseBackupController::class, 'create'])->name('backup.create');
    Route::get('/database/backup/download/{getFileName}', [DatabaseBackupController::class, 'download'])->name('backup.download');
    Route::get('/database/backup/delete/{getFileName}', [DatabaseBackupController::class, 'delete'])->name('backup.delete');
});

// ====== ROLE CONTROLLER ======
Route::middleware(['permission:roles.menu'])->group(function () {
    // Permissions
    Route::get('/permission', [RoleController::class, 'permissionIndex'])->name('permission.index');
    Route::get('/permission/create', [RoleController::class, 'permissionCreate'])->name('permission.create');
    Route::post('/permission', [RoleController::class, 'permissionStore'])->name('permission.store');
    Route::get('/permission/edit/{id}', [RoleController::class, 'permissionEdit'])->name('permission.edit');
    Route::put('/permission/{id}', [RoleController::class, 'permissionUpdate'])->name('permission.update');
    Route::delete('/permission/{id}', [RoleController::class, 'permissionDestroy'])->name('permission.destroy');

    // Roles
    Route::get('/role', [RoleController::class, 'roleIndex'])->name('role.index');
    Route::get('/role/create', [RoleController::class, 'roleCreate'])->name('role.create');
    Route::post('/role', [RoleController::class, 'roleStore'])->name('role.store');
    Route::get('/role/edit/{id}', [RoleController::class, 'roleEdit'])->name('role.edit');
    Route::put('/role/{id}', [RoleController::class, 'roleUpdate'])->name('role.update');
    Route::delete('/role/{id}', [RoleController::class, 'roleDestroy'])->name('role.destroy');

    // Role Permissions
    Route::get('/role/permission', [RoleController::class, 'rolePermissionIndex'])->name('rolePermission.index');
    Route::get('/role/permission/create', [RoleController::class, 'rolePermissionCreate'])->name('rolePermission.create');
    Route::post('/role/permission', [RoleController::class, 'rolePermissionStore'])->name('rolePermission.store');
    Route::get('/role/permission/{id}', [RoleController::class, 'rolePermissionEdit'])->name('rolePermission.edit');
    Route::put('/role/permission/{id}', [RoleController::class, 'rolePermissionUpdate'])->name('rolePermission.update');
    Route::delete('/role/permission/{id}', [RoleController::class, 'rolePermissionDestroy'])->name('rolePermission.destroy');
});

// ====== REPORTS ======
Route::middleware(['permission:reports.menu'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    
    // Sales Reports
    Route::middleware(['permission:reports.sales'])->group(function () {
        Route::get('/reports/sales/summary', [SalesReportController::class, 'summary'])->name('reports.sales.summary');
        Route::get('/reports/sales/daily', [SalesReportController::class, 'daily'])->name('reports.sales.daily');
        Route::get('/reports/sales/customer', [SalesReportController::class, 'customer'])->name('reports.sales.customer');
        Route::get('/reports/sales/product', [SalesReportController::class, 'product'])->name('reports.sales.product');
        Route::get('/api/reports/customers/search', [SalesReportController::class, 'searchCustomers'])->name('api.reports.customers.search');
        Route::get('/api/reports/products/search', [SalesReportController::class, 'searchProducts'])->name('api.reports.products.search');
    });
    
    // Purchase Reports
    Route::middleware(['permission:reports.purchases'])->group(function () {
        Route::get('/reports/purchases/summary', [PurchaseReportController::class, 'summary'])->name('reports.purchases.summary');
        Route::get('/reports/purchases/supplier', [PurchaseReportController::class, 'supplier'])->name('reports.purchases.supplier');
        Route::get('/reports/purchases/product', [PurchaseReportController::class, 'product'])->name('reports.purchases.product');
        Route::get('/api/reports/suppliers/search', [PurchaseReportController::class, 'searchSuppliers'])->name('api.reports.suppliers.search');
        Route::get('/api/reports/purchases/products/search', [PurchaseReportController::class, 'searchProducts'])->name('api.reports.purchases.products.search');
    });
    
    // Financial Reports
    Route::middleware(['permission:reports.financial'])->group(function () {
        Route::get('/reports/financial/profit-loss', [FinancialReportController::class, 'profitLoss'])->name('reports.financial.profit-loss');
        Route::get('/reports/financial/profit-loss/line-detail', [FinancialReportController::class, 'profitLossLineDetail'])->name('reports.financial.profit-loss-line-detail');
        Route::get('/reports/financial/revenue', [FinancialReportController::class, 'revenue'])->name('reports.financial.revenue');
        Route::get('/reports/financial/expense', [FinancialReportController::class, 'expense'])->name('reports.financial.expense');
        Route::get('/reports/financial/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('reports.financial.cash-flow');
    });
    
    // Credit Reports
    Route::middleware(['permission:reports.credit'])->group(function () {
        Route::get('/reports/credit/customer', [CreditReportController::class, 'customer'])->name('reports.credit.customer');
        Route::get('/reports/credit/supplier', [CreditReportController::class, 'supplier'])->name('reports.credit.supplier');
        Route::get('/reports/credit/summary', [CreditReportController::class, 'summary'])->name('reports.credit.summary');
    });
    
    // Inventory Reports
    Route::middleware(['permission:reports.inventory'])->group(function () {
        Route::get('/reports/inventory/stock', [InventoryReportController::class, 'stock'])->name('reports.inventory.stock');
        Route::get('/reports/inventory/stock-movement', [InventoryReportController::class, 'stockMovement'])->name('reports.inventory.stock-movement');
        Route::get('/reports/inventory/stock-valuation', [InventoryReportController::class, 'stockValuation'])->name('reports.inventory.stock-valuation');
        Route::get('/reports/inventory/expired-products', [InventoryReportController::class, 'expiredProducts'])->name('reports.inventory.expired-products');
        Route::get('/api/reports/inventory/products/search', [InventoryReportController::class, 'searchProducts'])->name('api.reports.inventory.products.search');
    });
    
    // Payment Reports
    Route::middleware(['permission:reports.payment'])->group(function () {
        Route::get('/reports/payment/collection', [PaymentReportController::class, 'collection'])->name('reports.payment.collection');
        Route::get('/reports/payment/disbursement', [PaymentReportController::class, 'disbursement'])->name('reports.payment.disbursement');
        Route::get('/reports/payment/summary', [PaymentReportController::class, 'summary'])->name('reports.payment.summary');
    });
    
    // Return Reports
    Route::middleware(['permission:reports.returns'])->group(function () {
        Route::get('/reports/returns/sale-return', [ReturnReportController::class, 'saleReturn'])->name('reports.returns.sale-return');
    });
    
    // Employee Reports
    Route::middleware(['permission:reports.employee'])->group(function () {
        Route::get('/reports/employee/salary', [EmployeeReportController::class, 'salary'])->name('reports.employee.salary');
        Route::get('/reports/employee/attendance', [EmployeeReportController::class, 'attendance'])->name('reports.employee.attendance');
    });
    
    // Comparative Reports
    Route::middleware(['permission:reports.comparative'])->group(function () {
        Route::get('/reports/comparative/shop-comparison', [ComparativeReportController::class, 'shopComparison'])->name('reports.comparative.shop-comparison');
        Route::get('/reports/comparative/period-comparison', [ComparativeReportController::class, 'periodComparison'])->name('reports.comparative.period-comparison');
    });
    
    // Executive Reports
    Route::middleware(['permission:reports.executive'])->group(function () {
        Route::get('/reports/executive/summary', [ExecutiveReportController::class, 'summary'])->name('reports.executive.summary');
    });
});

// ====== SYSTEM RESET (Super Admin Only) ======
Route::middleware(['auth', 'role:Super Admin'])->group(function () {
    Route::get('/system-reset', [SystemResetController::class, 'show'])->name('system-reset.show');
    Route::post('/system-reset/execute', [SystemResetController::class, 'execute'])->name('system-reset.execute');
    Route::get('/system-reset/logs', [SystemResetController::class, 'logs'])->name('system-reset.logs');
});

require __DIR__.'/auth.php';
