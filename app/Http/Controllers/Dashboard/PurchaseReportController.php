<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\ActiveShop;

class PurchaseReportController extends Controller
{
    use ReportTrait;

    public function summary(Request $request)
    {
        // Implementation similar to SalesReportController::summary()
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        $purchasesQuery = Purchase::with(['supplier', 'shop.parent'])
            ->whereBetween('purchase_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        $this->applyShopFilter($purchasesQuery, $shopFilter['shop_ids']);

        // Summary calculations
        $totalPurchases = (clone $purchasesQuery)->count();
        $totalAmount = (clone $purchasesQuery)->sum('total');
        $totalPaid = (clone $purchasesQuery)->sum('pay');
        $totalDue = (clone $purchasesQuery)->sum('due');
        $totalVat = (clone $purchasesQuery)->sum('vat');
        $totalDiscount = (clone $purchasesQuery)->sum('invoice_discount');

        $purchases = $purchasesQuery->orderByDesc('id')->paginate($row)->appends($request->query());

        return view('reports.purchases.summary', compact(
            'dateRange', 'shopFilter', 'totalPurchases', 'totalAmount', 
            'totalPaid', 'totalDue', 'totalVat', 'totalDiscount', 'purchases', 'row'
        ));
    }

    public function supplier(Request $request)
    {
        // Implementation for supplier purchase report
        return view('reports.purchases.supplier');
    }

    public function product(Request $request)
    {
        // Implementation for product purchase report
        return view('reports.purchases.product');
    }
}
