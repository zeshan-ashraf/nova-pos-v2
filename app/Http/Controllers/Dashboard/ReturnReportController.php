<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use Illuminate\Http\Request;

class ReturnReportController extends Controller
{
    use ReportTrait;

    public function saleReturn(Request $request)
    {
        return view('reports.returns.sale-return');
    }
}
