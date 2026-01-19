<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use Illuminate\Http\Request;

class ComparativeReportController extends Controller
{
    use ReportTrait;

    public function shopComparison(Request $request)
    {
        return view('reports.comparative.shop-comparison');
    }

    public function periodComparison(Request $request)
    {
        return view('reports.comparative.period-comparison');
    }
}
