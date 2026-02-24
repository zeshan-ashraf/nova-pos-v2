<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Expense;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Services\Ledger\ExpenseLedgerService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class ExpenseController extends Controller
{
    use ReportTrait;

    public function __construct(
        protected ExpenseLedgerService $expenseLedgerService
    ) {}

    /**
     * Display a listing of the expenses.
     * Date filter defaults to Today. Group by: None (default), Date, or Category.
     */
    public function index(Request $request)
    {
        $row = (int) $request->input('row', 50);
        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $groupBy = $request->input('group_by', 'none');
        if (!in_array($groupBy, ['none', 'date', 'category'], true)) {
            $groupBy = 'none';
        }

        $expensesQuery = Activity::query();

        // Shop filter: use logged-in user's shop_id when set
        if ($authUser && $authUser->shop_id) {
            $expensesQuery->where('activities.shop_id', $authUser->shop_id);
        }

        // Date filter (default today)
        $expensesQuery->whereBetween('date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);

        // Search filter
        if ($request->filled('search')) {
            $term = $request->input('search');
            $expensesQuery->where(function ($query) use ($term) {
                $query->where('description', 'like', "%{$term}%")
                    ->orWhere('date', 'like', "%{$term}%")
                    ->orWhere('activity_cost', 'like', "%{$term}%")
                    ->orWhereHas('expense', fn ($q) => $q->where('expense_title', 'like', "%{$term}%"));
            });
        }

        $expensesQuery->with('expense');

        // Total and count for filtered range (same filters, no pagination)
        $expenseTotal = (clone $expensesQuery)->sum('activity_cost');
        $expenseCount = (clone $expensesQuery)->count();

        if (!$request->has('sort')) {
            $expensesQuery->orderBy('id', 'desc');
        }
        $expensesQuery->sortable();

        $expenses = $expensesQuery->paginate($row)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'dateRange' => $dateRange,
            'groupBy' => $groupBy,
            'expenseTotal' => $expenseTotal,
            'expenseCount' => $expenseCount,
        ]);
    }


    /**
     * Show the form for creating a new expense.
     */
    public function create()
    {
        $authUser = auth()->user();
        $shopId = $authUser ? $authUser->shop_id : null;
        $shopBanks = collect();
        if ($shopId) {
            $shopBanks = DB::table('bank_shop')
                ->where('bank_shop.shop_id', $shopId)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->select('bank_shop.id', 'banks.name')
                ->orderBy('banks.name')
                ->get();
        }

        // Expense categories for dropdown (shop-scoped when user has shop)
        $expenseCategories = Expense::query()
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->orderBy('expense_title')
            ->get(['id', 'expense_title']);

        return view('expenses.create', [
            'shopBanks' => $shopBanks,
            'expenseCategories' => $expenseCategories,
        ]);
    }

    /**
     * Show the bulk expense form (view only – no submit logic).
     * Same shop rule as normal expense: user's shop or active shop for categories/banks.
     */
    public function bulkCreate()
    {
        $authUser = auth()->user();
        $shopId = $authUser ? $authUser->shop_id : null;
        if (!$shopId) {
            $shopId = ActiveShop::current()?->id;
        }

        $shopBanks = collect();
        if ($shopId) {
            $shopBanks = DB::table('bank_shop')
                ->where('bank_shop.shop_id', $shopId)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->select('bank_shop.id', 'banks.name')
                ->orderBy('banks.name')
                ->get();
        }

        $expenseCategories = Expense::query()
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->orderBy('expense_title')
            ->get(['id', 'expense_title']);

        return view('expenses.bulk-create', [
            'shopBanks' => $shopBanks,
            'expenseCategories' => $expenseCategories,
        ]);
    }

    /**
     * Store bulk expenses. One date for all; each filled row creates one Activity and one account_transaction.
     * Same shop rule as single expense; all-or-nothing in one DB transaction.
     */
    public function storeBulk(Request $request)
    {
        $request->validate([
            'bulk_expense_date' => 'required|date',
            'expenses' => 'nullable|array',
            'expenses.*.expense_id' => 'nullable|exists:expenses,id',
            'expenses.*.activity_cost' => 'nullable|numeric|min:0',
            'expenses.*.description' => 'nullable|string',
            'expenses.*.payment_method' => 'nullable|string|in:cash,bank',
            'expenses.*.shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser ? $authUser->shop_id : null;
        if (!$shopId) {
            $shopId = ActiveShop::current()?->id;
        }
        if (!$shopId) {
            return Redirect::back()
                ->withErrors(['bulk_expense_date' => 'Please select a shop to add expenses.'])
                ->withInput();
        }

        $expensesInput = $request->input('expenses', []);
        $rows = [];
        foreach ($expensesInput as $row) {
            $expenseId = isset($row['expense_id']) ? (trim((string) $row['expense_id']) !== '' ? (int) $row['expense_id'] : null) : null;
            $cost = isset($row['activity_cost']) && $row['activity_cost'] !== '' && $row['activity_cost'] !== null
                ? (float) $row['activity_cost']
                : null;
            if ($expenseId === null || $cost === null || $cost < 0) {
                continue;
            }
            $rows[] = [
                'expense_id' => $expenseId,
                'activity_cost' => $cost,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'payment_method' => isset($row['payment_method']) && in_array($row['payment_method'], ['cash', 'bank'], true)
                    ? $row['payment_method']
                    : 'cash',
                'shop_bank_id' => isset($row['shop_bank_id']) && trim((string) $row['shop_bank_id']) !== ''
                    ? (int) $row['shop_bank_id']
                    : null,
            ];
        }

        if (empty($rows)) {
            return Redirect::back()
                ->withErrors(['expenses' => 'Add at least one expense (category and amount required).'])
                ->withInput();
        }

        foreach ($rows as $i => $row) {
            if ($shopId) {
                $validCategory = Expense::where('id', $row['expense_id'])->where('shop_id', $shopId)->exists();
                if (!$validCategory) {
                    return Redirect::back()
                        ->withErrors(['expenses' => "Row " . ($i + 1) . ": The selected expense category is not valid for your shop."])
                        ->withInput();
                }
            }
            if (($row['payment_method'] ?? 'cash') === 'bank') {
                if (empty($row['shop_bank_id'])) {
                    return Redirect::back()
                        ->withErrors(['expenses' => "Row " . ($i + 1) . ": Please select a bank when payment method is Bank."])
                        ->withInput();
                }
                $belongsToShop = DB::table('bank_shop')->where('id', $row['shop_bank_id'])->where('shop_id', $shopId)->exists();
                if (!$belongsToShop) {
                    return Redirect::back()
                        ->withErrors(['expenses' => "Row " . ($i + 1) . ": The selected bank is not valid for your shop."])
                        ->withInput();
                }
            }
        }

        $date = $request->input('bulk_expense_date');

        $count = DB::transaction(function () use ($rows, $date, $shopId) {
            $count = 0;
            foreach ($rows as $row) {
                $activity = Activity::create([
                    'expense_id' => $row['expense_id'],
                    'description' => $row['description'],
                    'date' => $date,
                    'activity_cost' => $row['activity_cost'],
                    'payment_method' => $row['payment_method'],
                    'shop_bank_id' => $row['payment_method'] === 'bank' ? $row['shop_bank_id'] : null,
                    'customer_id' => null,
                    'shop_id' => $shopId,
                    'images' => json_encode([]),
                ]);
                $this->expenseLedgerService->recordExpense($activity);
                $count++;
            }
            return $count;
        });

        return Redirect::route('expenses.index')
            ->with('success', $count . ' expenses have been created!');
    }

    /**
     * Store a newly created expense in storage.
     * Wraps creation and ledger entry in a DB transaction; expense is always paid (cash or bank).
     */
    public function store(Request $request)
    {
        $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'activity_cost' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|in:cash,bank',
            'shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
            'image_1' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image_2' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser ? $authUser->shop_id : null;
        $paymentMethod = $request->input('payment_method', 'cash');
        $shopBankId = $paymentMethod === 'bank' ? $request->input('shop_bank_id') : null;

        // Ensure selected expense category belongs to user's shop (when user has shop)
        if ($shopId) {
            $validCategory = Expense::where('id', $request->input('expense_id'))->where('shop_id', $shopId)->exists();
            if (!$validCategory) {
                return back()->withErrors(['expense_id' => 'The selected expense category is not valid for your shop.'])->withInput();
            }
        }

        if ($paymentMethod === 'bank' && !$shopBankId) {
            return back()->withErrors(['shop_bank_id' => 'Please select a bank when payment method is Bank.'])->withInput();
        }
        if ($shopBankId && $shopId) {
            $belongsToShop = DB::table('bank_shop')->where('id', $shopBankId)->where('shop_id', $shopId)->exists();
            if (!$belongsToShop) {
                return back()->withErrors(['shop_bank_id' => 'The selected bank is not valid for your shop.'])->withInput();
            }
        }

        $images = [];
        if ($request->hasFile('image_1')) {
            $images[] = $request->file('image_1')->store('activities', 'public');
        }
        if ($request->hasFile('image_2')) {
            $images[] = $request->file('image_2')->store('activities', 'public');
        }

        $expense = DB::transaction(function () use ($request, $authUser, $shopId, $paymentMethod, $shopBankId, $images) {
            $expense = Activity::create([
                'expense_id' => $request->input('expense_id'),
                'description' => $request->filled('description') ? $request->input('description') : null,
                'date' => $request->input('date'),
                'activity_cost' => $request->input('activity_cost'),
                'payment_method' => $paymentMethod,
                'shop_bank_id' => $shopBankId,
                'customer_id' => null,
                'shop_id' => $shopId,
                'images' => json_encode($images),
            ]);

            $this->expenseLedgerService->recordExpense($expense);

            return $expense;
        });

        return Redirect::route('expenses.index')->with('success', 'Expense has been created!');
    }

    /**
     * Display the specified expense.
     */
    public function show(Activity $expense)
    {
        $this->ensureShopAccess($expense);
        $expense->load('expense');
        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified expense.
     */
    public function edit(Activity $expense)
    {
        $this->ensureShopAccess($expense);
        $this->rejectSystemExpenseModification($expense, 'edit');

        $shopId = auth()->user()?->shop_id;
        $expenseCategories = Expense::query()
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->orderBy('expense_title')
            ->get(['id', 'expense_title']);

        return view('expenses.edit', [
            'expense' => $expense,
            'expenseCategories' => $expenseCategories,
        ]);
    }

    /**
     * Update the specified expense in storage.
     */
    public function update(Request $request, Activity $expense)
    {
        $this->ensureShopAccess($expense);
        $this->rejectSystemExpenseModification($expense, 'edit');

        $request->validate([
            'expense_id' => 'required|exists:expenses,id',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'activity_cost' => 'required|numeric',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $shopId = auth()->user()?->shop_id;
        if ($shopId) {
            $validCategory = Expense::where('id', $request->input('expense_id'))->where('shop_id', $shopId)->exists();
            if (!$validCategory) {
                return back()->withErrors(['expense_id' => 'The selected expense category is not valid for your shop.'])->withInput();
            }
        }

        $images = is_array($expense->images) ? $expense->images : [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('activities', 'public');
            }
        }

        $expense->update([
            'expense_id' => $request->input('expense_id'),
            'description' => $request->filled('description') ? $request->input('description') : null,
            'date' => $request->input('date'),
            'activity_cost' => $request->input('activity_cost'),
            'customer_id' => null,
            'images' => $images, // Store as an array, not JSON
        ]);

        return Redirect::route('expenses.index')->with('success', 'Expense has been updated!');
    }


    /**
     * Soft delete the expense (activity) and its related account_transactions.
     */
    public function destroy(Activity $expense)
    {
        $this->ensureShopAccess($expense);
        $this->rejectSystemExpenseModification($expense, 'delete');

        DB::transaction(function () use ($expense) {
            // Soft delete related account_transactions (source_type = expense, source_id = activity id)
            AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_EXPENSE)
                ->where('source_id', $expense->id)
                ->delete();
            $expense->delete();
        });

        return Redirect::route('expenses.index')->with('success', 'Expense has been deleted!');
    }
    
    public function expenseSearch(Request $request)
    {
        $searchTerm = $request->get('search');
        $authUser = auth()->user();

        $expensesQuery = Activity::query()
            ->with('expense')
            ->where(function ($query) use ($searchTerm) {
                $query->where('description', 'like', "%{$searchTerm}%")
                    ->orWhere('date', 'like', "%{$searchTerm}%")
                    ->orWhere('activity_cost', 'like', "%{$searchTerm}%")
                    ->orWhereHas('expense', fn ($q) => $q->where('expense_title', 'like', "%{$searchTerm}%"));
            });

        // Apply shop filtering - super admin can see all expenses, others only their shop
        if ($authUser && $authUser->shop_id) {
            $expensesQuery->where('shop_id', $authUser->shop_id);
        }
        // Super admin (no shop_id) can see all expenses, no filtering needed

        $expenses = $expensesQuery->paginate(10);

        if ($request->ajax()) {
            return response()->json(['expenses' => $expenses]);
        }

        return view('expenses.index', compact('expenses'));
    }

    /**
     * Ensure the current user has access to the expense based on shop.
     */
    protected function ensureShopAccess(Activity $expense): void
    {
        $authUser = auth()->user();

        // If user is not authenticated, deny access
        if (!$authUser) {
            abort(403, 'You must be authenticated to access this expense.');
        }

        // Super admin can access all expenses
        if (!$authUser->shop_id) {
            return;
        }

        // Users with shop_id can only access expenses from their shop
        if ($expense->shop_id !== $authUser->shop_id) {
            abort(403, 'You do not have access to this expense.');
        }
    }

    /**
     * System expenses (e.g. inventory loss) are read-only; user must not edit or delete.
     */
    protected function rejectSystemExpenseModification(Activity $expense, string $action): void
    {
        if ($expense->is_system ?? false) {
            abort(403, 'System expenses cannot be ' . $action . 'd.');
        }
    }
}
