<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use Illuminate\Http\Request;

class EmployeeReportController extends Controller
{
    use ReportTrait;

    public function salary(Request $request)
    {
        return view('reports.employee.salary');
    }

    public function attendance(Request $request)
    {
        return view('reports.employee.attendance');
    }
}
