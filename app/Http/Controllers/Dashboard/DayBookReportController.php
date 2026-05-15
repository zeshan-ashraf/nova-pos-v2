<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Services\Reports\DayBookReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DayBookReportController extends Controller
{
    use ReportTrait;

    public function __construct(
        private DayBookReportService $dayBookReportService
    ) {
    }

    public function index(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);

        $startDate = $request->input('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::today()->format('Y-m-d'));
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            [$startDate, $endDate] = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        $rows = $this->dayBookReportService->buildRows($start, $end, $shopFilter['shop_ids']);
        $totals = $this->dayBookReportService->sumTotals($rows);

        $perPage = 50;
        $page = max(1, (int) $request->input('page', 1));
        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('reports.financial.day-book', [
            'paginator' => $paginator,
            'totals' => $totals,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'shopFilter' => $shopFilter,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);

        $startDate = $request->input('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::today()->format('Y-m-d'));
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $rows = $this->dayBookReportService->buildRows($start, $end, $shopFilter['shop_ids']);
        $totals = $this->dayBookReportService->sumTotals($rows);

        $filename = 'day-book-' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '-' . now()->format('His') . '.xlsx';

        return new StreamedResponse(function () use ($rows, $totals, $filename) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                'Date',
                'Ref No',
                'Type',
                'Particular',
                'Net Amount',
                'Title',
                'Amount (In Cash)',
                'Amount (Out Cash)',
                'Amount In Online',
                'Amount Out Online',
            ], null, 'A1');

            $rowIndex = 2;
            foreach ($rows as $r) {
                $sheet->fromArray([
                    Carbon::parse($r->date)->format('Y-m-d'),
                    $r->ref_no,
                    $r->type,
                    $r->particular,
                    number_format((float) $r->net_amount, 2, '.', ''),
                    $r->title,
                    number_format((float) $r->amount_in_cash, 2, '.', ''),
                    number_format((float) $r->amount_out_cash, 2, '.', ''),
                    number_format((float) $r->amount_in_online, 2, '.', ''),
                    number_format((float) $r->amount_out_online, 2, '.', ''),
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }

            $sheet->fromArray([
                '',
                '',
                '',
                'Totals',
                number_format($totals['net_amount'], 2, '.', ''),
                '',
                number_format($totals['amount_in_cash'], 2, '.', ''),
                number_format($totals['amount_out_cash'], 2, '.', ''),
                number_format($totals['amount_in_online'], 2, '.', ''),
                number_format($totals['amount_out_online'], 2, '.', ''),
            ], null, 'A' . $rowIndex);
            $sheet->getStyle('A' . $rowIndex . ':J' . $rowIndex)->getFont()->setBold(true);

            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
