<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use Illuminate\Http\Request;

class ExecutiveReportController extends Controller
{
    use ReportTrait;

    public function summary(Request $request)
    {
        return view('reports.executive.summary');
    }
}
