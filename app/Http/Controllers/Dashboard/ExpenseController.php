<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Expense;
use App\Services\Ledger\ExpenseLedgerService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseLedgerService $expenseLedgerService
    ) {}

    /**
     * Display a listing of the expenses.
     */
    public function index()
    {
        // Get the number of rows per page, default to 10
        $row = (int) request('row', 50);

        // Validate that the 'row' is between 1 and 100
        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $expensesQuery = Expense::query();

        // Apply shop filtering - super admin can see all expenses, others only their shop
        if ($authUser && $authUser->shop_id) {
            $expensesQuery->where('shop_id', $authUser->shop_id);
        }
        // Super admin (no shop_id) can see all expenses, no filtering needed

        // Paginate expenses with the specified number of rows per page
        $expenses = $expensesQuery->paginate($row);

        return view('expenses.index', [
            'expenses' => $expenses,
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

        return view('expenses.create', ['shopBanks' => $shopBanks]);
    }

    /**
     * Store a newly created expense in storage.
     * Wraps creation and ledger entry in a DB transaction; expense is always paid (cash or bank).
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
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
            $expense = Expense::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
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
    public function show(Expense $expense)
    {
        $this->ensureShopAccess($expense);
        return view('expenses.show', compact('expense'));
    }

    /**
     * Show the form for editing the specified expense.
     */
    public function edit(Expense $expense)
    {
        $this->ensureShopAccess($expense);
        $this->rejectSystemExpenseModification($expense, 'edit');
        return view('expenses.edit', compact('expense'));
    }

    /**
     * Update the specified expense in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $this->ensureShopAccess($expense);
        $this->rejectSystemExpenseModification($expense, 'edit');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'activity_cost' => 'required|numeric',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $images = is_array($expense->images) ? $expense->images : [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('activities', 'public');
            }
        }

        $expense->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'date' => $request->input('date'),
            'activity_cost' => $request->input('activity_cost'),
            'customer_id' => null,
            'images' => $images, // Store as an array, not JSON
        ]);

        return Redirect::route('expenses.index')->with('success', 'Expense has been updated!');
    }


    /**
     * Remove the specified expense from storage.
     */
    public function destroy(Expense $expense)
    {
        $this->ensureShopAccess($expense);
        $this->rejectSystemExpenseModification($expense, 'delete');
        $expense->delete();
        return Redirect::route('expenses.index')->with('success', 'Expense has been deleted!');
    }
    
    public function expenseSearch(Request $request)
    {
        $searchTerm = $request->get('search');
        $authUser = auth()->user();

        $expensesQuery = Expense::where(function ($query) use ($searchTerm) {
            $query->where('title', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%")
                ->orWhere('date', 'like', "%{$searchTerm}%")
                ->orWhere('activity_cost', 'like', "%{$searchTerm}%");
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
    protected function ensureShopAccess(Expense $expense): void
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
    protected function rejectSystemExpenseModification(Expense $expense, string $action): void
    {
        if ($expense->is_system ?? false) {
            abort(403, 'System expenses cannot be ' . $action . 'd.');
        }
    }
}
