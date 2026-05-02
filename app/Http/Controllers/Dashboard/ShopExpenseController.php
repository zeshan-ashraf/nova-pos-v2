<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Http\Requests\ShopExpense\StoreShopExpenseRequest;
use App\Http\Requests\ShopExpense\UpdateShopExpenseRequest;
use App\Models\Expense;
use App\Models\Shop;
use App\Models\ShopExpense;
use App\Support\ActiveShop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopExpenseController extends Controller
{
    use ReportTrait;

    public function __construct()
    {
        $this->middleware('permission:shop_expense.view')->only(['index']);
        $this->middleware('permission:shop_expense.create')->only(['create', 'store']);
        $this->middleware('permission:shop_expense.edit')->only(['edit', 'update']);
        $this->middleware('permission:shop_expense.delete')->only(['destroy']);
        $this->middleware('permission:shop_expense.export')->only(['exportExcel', 'exportPdf']);
    }

    public function index(Request $request)
    {
        $this->normalizeShopExpenseDateFilter($request);

        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);
        $dateRange = $this->getDateRange($request);
        $row = $this->getRowCount($request);

        $shopExpenses = $this->baseQuery($request)
            ->paginate($row)
            ->appends($request->query());

        $shopExpenseKpis = $this->shopExpensePaymentAggregates($request);

        return view('shop_expenses.index', [
            'shopExpenses' => $shopExpenses,
            'shopFilter' => $shopFilter,
            'dateRange' => $dateRange,
            'shopExpenseKpis' => $shopExpenseKpis,
        ]);
    }

    /**
     * Cash vs bank totals for the same filters as the list (not paginated).
     *
     * @return array{cash_total: float, cash_count: int, bank_total: float, bank_count: int}
     */
    protected function shopExpensePaymentAggregates(Request $request): array
    {
        $rows = (clone $this->baseQuery($request))
            ->reorder()
            ->selectRaw('payment_type, COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as expense_count')
            ->groupBy('payment_type')
            ->get()
            ->keyBy('payment_type');

        return [
            'cash_total' => (float) ($rows->get('cash')->total_amount ?? 0),
            'cash_count' => (int) ($rows->get('cash')->expense_count ?? 0),
            'bank_total' => (float) ($rows->get('bank')->total_amount ?? 0),
            'bank_count' => (int) ($rows->get('bank')->expense_count ?? 0),
        ];
    }

    public function create()
    {
        $user = auth()->user();
        abort_unless($user->shop_id, 403);

        $shopId = (int) $user->shop_id;
        $shop = Shop::query()->findOrFail($shopId);

        $expenseCategories = Expense::query()
            ->where('shop_id', $shopId)
            ->orderBy('expense_title')
            ->get();

        $shopBanks = $shop->banks()->orderBy('name')->get();

        return view('shop_expenses.create', [
            'expenseCategories' => $expenseCategories,
            'shopBanks' => $shopBanks,
        ]);
    }

    public function store(StoreShopExpenseRequest $request)
    {
        $data = $request->validated();
        if (($data['payment_type'] ?? null) === 'cash') {
            $data['bank_id'] = null;
        }

        ShopExpense::create($data);

        return Redirect::route('shop-expenses.index')
            ->with('success', 'Shop expense saved successfully.');
    }

    public function edit(ShopExpense $shopExpense)
    {
        $this->assertCanAccessShopExpense($shopExpense);

        $user = auth()->user();
        $shopId = $user->shop_id
            ? (int) $user->shop_id
            : (int) $shopExpense->shop_id;

        if ($user->shop_id) {
            abort_unless((int) $shopExpense->shop_id === (int) $user->shop_id, 403);
        }

        $shop = Shop::query()->findOrFail($shopId);

        $expenseCategories = Expense::query()
            ->where('shop_id', $shopId)
            ->orderBy('expense_title')
            ->get();

        $shopBanks = $shop->banks()->orderBy('name')->get();

        return view('shop_expenses.edit', [
            'shopExpense' => $shopExpense,
            'expenseCategories' => $expenseCategories,
            'shopBanks' => $shopBanks,
        ]);
    }

    public function update(UpdateShopExpenseRequest $request, ShopExpense $shopExpense)
    {
        $this->assertCanAccessShopExpense($shopExpense);

        $data = $request->validated();
        if (($data['payment_type'] ?? null) === 'cash') {
            $data['bank_id'] = null;
        }

        $shopExpense->update($data);

        return Redirect::route('shop-expenses.index')
            ->with('success', 'Shop expense updated successfully.');
    }

    public function destroy(ShopExpense $shopExpense)
    {
        $this->assertCanAccessShopExpense($shopExpense);

        $shopExpense->delete();

        return Redirect::route('shop-expenses.index')
            ->with('success', 'Shop expense deleted successfully.');
    }

    public function exportExcel(Request $request)
    {
        $items = $this->baseQuery($request)->get();
        $filename = 'shop-expenses-' . now()->format('Y-m-d_His') . '.xlsx';
        $grandTotal = (float) $items->sum('amount');

        return new StreamedResponse(function () use ($items, $filename, $grandTotal) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                'Date',
                'Shop',
                'Expense',
                'Payment Type',
                'Bank',
                'Description',
                'Amount',
            ], null, 'A1');

            $rowIndex = 2;
            foreach ($items as $row) {
                $sheet->fromArray([
                    $row->expense_date?->format('Y-m-d') ?? '',
                    $row->shop?->name ?? '',
                    $row->expense?->expense_title ?? '',
                    ucfirst((string) $row->payment_type),
                    $row->payment_type === 'bank' ? ($row->bank?->name ?? '') : '',
                    $row->description ?? '',
                    number_format((float) $row->amount, 2, '.', ''),
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }

            $sheet->fromArray([
                '',
                '',
                '',
                '',
                '',
                'Total',
                number_format($grandTotal, 2, '.', ''),
            ], null, 'A' . $rowIndex);
            $sheet->getStyle('A' . $rowIndex . ':G' . $rowIndex)->getFont()->setBold(true);

            foreach (range('A', 'G') as $col) {
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

    public function exportPdf(Request $request)
    {
        $this->normalizeShopExpenseDateFilter($request);

        $shopFilter = $this->getShopFilter($request, auth()->user());
        $dateRange = $this->getDateRange($request);
        $items = $this->baseQuery($request)->get();
        $total = $items->sum('amount');

        $pdf = Pdf::loadView('shop_expenses.pdf', [
            'items' => $items,
            'dateRange' => $dateRange,
            'shopFilter' => $shopFilter,
            'total' => $total,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('shop-expenses-' . now()->format('Y-m-d_His') . '.pdf');
    }

    protected function baseQuery(Request $request)
    {
        $this->normalizeShopExpenseDateFilter($request);

        $user = auth()->user();
        $shopFilter = $this->getShopFilter($request, $user);
        $dateRange = $this->getDateRange($request);

        $query = ShopExpense::query()
            ->with(['shop', 'expense', 'bank'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        $this->applyShopFilter($query, $shopFilter['shop_ids']);

        if ($dateRange['start_date'] !== null && $dateRange['end_date'] !== null) {
            $query->whereBetween('expense_date', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhereHas('expense', fn ($eq) => $eq->where('expense_title', 'like', $search))
                    ->orWhereHas('shop', fn ($sq) => $sq->where('name', 'like', $search));
            });
        }

        return $query;
    }

    protected function assertCanAccessShopExpense(ShopExpense $shopExpense): void
    {
        $user = auth()->user();
        if ($user->shop_id) {
            abort_unless((int) $shopExpense->shop_id === (int) $user->shop_id, 403);

            return;
        }

        $visible = ActiveShop::visibleShopIds($user);
        abort_unless($visible->contains($shopExpense->shop_id), 403);
    }

    /**
     * Shop expenses list defaults to all time when no date preset is chosen.
     */
    protected function normalizeShopExpenseDateFilter(Request $request): void
    {
        $request->mergeIfMissing(['date_filter' => 'all']);
    }
}
