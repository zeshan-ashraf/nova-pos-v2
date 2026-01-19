<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CreditReportController extends Controller
{
    use ReportTrait;

    public function customer(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        $customersQuery = Customer::with('shop.parent')
            ->where('credit_amount', '>', 0);

        $this->applyShopFilter($customersQuery, $shopFilter['shop_ids'], 'shop_id');

        // Calculate aging for each customer based on their credit_days
        $customers = $customersQuery->get()->map(function($customer) {
            $creditDays = $customer->credit_days ?? 0;
            $daysOverdue = max(0, $creditDays); // Simplified - would need actual order dates
            
            $customer->credit_utilization = $customer->credit_limit > 0 
                ? ($customer->credit_amount / $customer->credit_limit) * 100 
                : 0;
            
            $customer->risk_level = $customer->credit_utilization > 80 ? 'high' 
                : ($customer->credit_utilization > 50 ? 'medium' : 'low');
            
            return $customer;
        });

        $totalCredit = $customers->sum('credit_amount');
        $totalLimit = $customers->sum('credit_limit');

        return view('reports.credit.customer', compact(
            'shopFilter', 'customers', 'totalCredit', 'totalLimit', 'row'
        ));
    }

    public function supplier(Request $request)
    {
        return view('reports.credit.supplier');
    }

    public function summary(Request $request)
    {
        return view('reports.credit.summary');
    }
}
