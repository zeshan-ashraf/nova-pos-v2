<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use Illuminate\Http\Request;

class PaymentReportController extends Controller
{
    use ReportTrait;

    public function collection(Request $request)
    {
        return view('reports.payment.collection');
    }

    public function disbursement(Request $request)
    {
        return view('reports.payment.disbursement');
    }

    public function summary(Request $request)
    {
        return view('reports.payment.summary');
    }
}
