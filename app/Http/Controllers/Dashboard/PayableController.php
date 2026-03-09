<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Payable;
use App\Models\PayableTransaction;
use App\Support\ActiveShop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class PayableController extends Controller
{
    use ReportTrait;
    /**
     * Display a listing of payables with calculated balance.
     */
    public function index(Request $request)
    {
        $row = (int) $request->input('row', 50);
        if ($row < 1 || $row > 100) {
            $row = 50;
        }

        $authUser = auth()->user();
        $today = now()->toDateString();
        $query = Payable::query()
            ->withCount('payableTransactions')
            ->withSum(['payableTransactions as borrow_total' => fn ($q) => $q->where('type', 'borrow')], 'amount')
            ->withSum(['payableTransactions as repayment_total' => fn ($q) => $q->where('type', 'repayment')], 'amount')
            ->withExists(['payableTransactions as has_overdue_transaction' => fn ($q) => $q->whereNotNull('expected_return_date')->whereDate('expected_return_date', '<', $today)]);

        if ($authUser && $authUser->shop_id) {
            $query->where('shop_id', $authUser->shop_id);
        } else {
            $visibleShopIds = ActiveShop::visibleShopIds($authUser);
            if ($visibleShopIds->isNotEmpty()) {
                $query->whereIn('shop_id', $visibleShopIds);
            }
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            });
        }

        $sortBy = $request->input('sort', 'name');
        $sortDir = strtolower($request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sortBy === 'balance') {
            $query->addSelect(\DB::raw("(SELECT COALESCE(SUM(CASE WHEN type = 'borrow' THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = 'repayment' THEN amount ELSE 0 END), 0) FROM payable_transactions WHERE payable_transactions.payable_id = payables.id AND payable_transactions.deleted_at IS NULL) as balance_sort"));
            $query->orderBy('balance_sort', $sortDir);
        } else {
            $query->orderBy('name', $sortDir);
        }

        $payables = $query->paginate($row)->withQueryString();

        // Summary stats (same filters: shop + search)
        $statsQuery = Payable::query();
        if ($authUser && $authUser->shop_id) {
            $statsQuery->where('shop_id', $authUser->shop_id);
        } else {
            $visibleShopIds = ActiveShop::visibleShopIds($authUser);
            if ($visibleShopIds->isNotEmpty()) {
                $statsQuery->whereIn('shop_id', $visibleShopIds);
            }
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $statsQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            });
        }
        $payableIds = $statsQuery->pluck('id');
        $totalAccounts = $payableIds->count();
        $totalBorrowed = $payableIds->isEmpty() ? 0 : (float) PayableTransaction::whereIn('payable_id', $payableIds)->where('type', 'borrow')->sum('amount');
        $totalRepaid = $payableIds->isEmpty() ? 0 : (float) PayableTransaction::whereIn('payable_id', $payableIds)->where('type', 'repayment')->sum('amount');
        $totalBalance = $totalBorrowed - $totalRepaid;
        $overdueCount = 0;
        if ($payableIds->isNotEmpty()) {
            $overdueCount = (int) DB::table('payables')
                ->whereIn('id', $payableIds)
                ->whereExists(function ($q) use ($today) {
                    $q->select(DB::raw(1))
                        ->from('payable_transactions')
                        ->whereColumn('payable_transactions.payable_id', 'payables.id')
                        ->whereNotNull('expected_return_date')
                        ->whereDate('expected_return_date', '<', $today)
                        ->whereNull('payable_transactions.deleted_at');
                })
                ->whereRaw('(SELECT COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) FROM payable_transactions WHERE payable_id = payables.id AND deleted_at IS NULL) > 0', ['borrow', 'repayment'])
                ->count();
        }

        $payableStats = [
            'total_accounts' => $totalAccounts,
            'total_borrowed' => $totalBorrowed,
            'total_repaid' => $totalRepaid,
            'total_balance' => $totalBalance,
            'overdue_count' => $overdueCount,
        ];

        // All transactions (second grid): filter by txn_* params only; does not affect payables list
        $payableIdsForTxn = $payableIds; // same shop scope
        $txnDateFilter = $request->input('txn_date_filter', 'all');
        $txnStart = $request->input('txn_start_date');
        $txnEnd = $request->input('txn_end_date');
        $txnType = $request->input('txn_type', '');
        $txnAccountId = $request->input('txn_account_id');
        $txnRow = (int) $request->input('txn_row', 25);
        if ($txnRow < 1 || $txnRow > 100) {
            $txnRow = 25;
        }

        $txnDateRange = $this->getTxnDateRange($txnDateFilter, $txnStart, $txnEnd);

        $allTransactionsQuery = PayableTransaction::query()
            ->with('payable')
            ->whereIn('payable_id', $payableIdsForTxn);

        if ($txnAccountId && $payableIdsForTxn->contains((int) $txnAccountId)) {
            $allTransactionsQuery->where('payable_id', (int) $txnAccountId);
        }
        if ($txnDateRange['start_datetime'] !== null && $txnDateRange['end_datetime'] !== null) {
            $allTransactionsQuery->whereBetween('date', [
                $txnDateRange['start_datetime']->format('Y-m-d'),
                $txnDateRange['end_datetime']->format('Y-m-d'),
            ]);
        }
        if (in_array($txnType, ['borrow', 'repayment'], true)) {
            $allTransactionsQuery->where('type', $txnType);
        }

        $allTransactions = $allTransactionsQuery->orderBy('date', 'desc')->orderBy('id', 'desc')
            ->paginate($txnRow, ['*'], 'txn_page')->withQueryString();

        $selectedPayableForTxn = null;
        if ($txnAccountId && $payableIdsForTxn->contains((int) $txnAccountId)) {
            $selectedPayableForTxn = Payable::find((int) $txnAccountId);
        }

        return view('payables.index', [
            'payables' => $payables,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'payableStats' => $payableStats,
            'allTransactions' => $allTransactions,
            'txnDateRange' => $txnDateRange,
            'txnDateFilter' => $txnDateFilter,
            'txnType' => $txnType,
            'txnAccountId' => $txnAccountId,
            'txnRow' => $txnRow,
            'selectedPayableForTxn' => $selectedPayableForTxn,
        ]);
    }

    /**
     * AJAX search for payables (Select2). Returns JSON: { results: [ { id, text } ], pagination: { more } }.
     */
    public function searchPayables(Request $request)
    {
        $search = $request->get('q', '');
        $page = (int) $request->get('page', 1);
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $query = Payable::query();
        if ($authUser && $authUser->shop_id) {
            $query->where('shop_id', $authUser->shop_id);
        } else {
            if ($visibleShopIds->isNotEmpty()) {
                $query->whereIn('shop_id', $visibleShopIds);
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }

        $totalCount = (clone $query)->count();
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $payables = $query->orderBy('name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($payable) {
                $text = $payable->name;
                if ($payable->phone) {
                    $text .= ' - ' . $payable->phone;
                }
                return ['id' => $payable->id, 'text' => $text];
            });

        return response()->json([
            'results' => $payables,
            'pagination' => ['more' => ($page * $perPage) < $totalCount],
        ]);
    }

    /**
     * Date range for transaction filter (txn_* params). Returns start/end datetime or nulls when 'all'.
     */
    private function getTxnDateRange(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        $out = [
            'date_filter' => $dateFilter,
            'start_date' => '',
            'end_date' => '',
            'start_datetime' => null,
            'end_datetime' => null,
        ];
        if ($dateFilter === 'all' || $dateFilter === '') {
            return $out;
        }
        switch ($dateFilter) {
            case 'today':
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday()->format('Y-m-d');
                $endDate = Carbon::yesterday()->format('Y-m-d');
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'last_week':
                $startDate = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
                $endDate = Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
            case 'last_year':
                $startDate = Carbon::now()->subYear()->startOfYear()->format('Y-m-d');
                $endDate = Carbon::now()->subYear()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                $startDate = $startDate ?: Carbon::today()->format('Y-m-d');
                $endDate = $endDate ?: Carbon::today()->format('Y-m-d');
                break;
            default:
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
        }
        $out['start_date'] = $startDate;
        $out['end_date'] = $endDate;
        $out['start_datetime'] = Carbon::parse($startDate)->startOfDay();
        $out['end_datetime'] = Carbon::parse($endDate)->endOfDay();
        return $out;
    }

    /**
     * Show the form for creating a new payable.
     */
    public function create()
    {
        return view('payables.create');
    }

    /**
     * Store a newly created payable.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser->shop_id ?? ActiveShop::id();
        if (!$shopId) {
            return Redirect::back()->withErrors(['name' => 'Please select a shop context.'])->withInput();
        }

        $validated['shop_id'] = $shopId;
        Payable::create($validated);

        return Redirect::route('payables.index')->with('success', 'Payable has been created.');
    }

    /**
     * Display the payable ledger (transactions list).
     */
    public function show(Payable $payable)
    {
        $this->ensureShopAccess($payable);

        $payable->load(['payableTransactions' => fn ($q) => $q->orderBy('date', 'desc')->orderBy('id', 'desc')]);

        $borrowTotal = (float) $payable->payableTransactions->where('type', 'borrow')->sum('amount');
        $repaymentTotal = (float) $payable->payableTransactions->where('type', 'repayment')->sum('amount');

        return view('payables.show', [
            'payable' => $payable,
            'borrowTotal' => $borrowTotal,
            'repaymentTotal' => $repaymentTotal,
        ]);
    }

    /**
     * Show the form for editing the payable.
     */
    public function edit(Payable $payable)
    {
        $this->ensureShopAccess($payable);

        return view('payables.edit', [
            'payable' => $payable,
        ]);
    }

    /**
     * Update the specified payable.
     */
    public function update(Request $request, Payable $payable)
    {
        $this->ensureShopAccess($payable);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $payable->update($validated);

        return Redirect::route('payables.index')->with('success', 'Payable has been updated.');
    }

    /**
     * Remove the payable (soft delete).
     */
    public function destroy(Payable $payable)
    {
        $this->ensureShopAccess($payable);

        if ($payable->payableTransactions()->exists()) {
            return Redirect::route('payables.index')->with('delete_error', 'Cannot delete this account because it has one or more transactions. Delete or clear all transactions first.');
        }

        $payable->delete();

        return Redirect::route('payables.index')->with('success', 'Payable has been deleted.');
    }

    protected function ensureShopAccess(Payable $payable): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            if ($payable->shop_id && $payable->shop_id != $authUser->shop_id) {
                abort(403, 'You do not have access to this payable.');
            }
        } elseif ($visibleShopIds->isNotEmpty() && !$visibleShopIds->contains($payable->shop_id)) {
            abort(403, 'You do not have access to this payable.');
        }
    }
}
