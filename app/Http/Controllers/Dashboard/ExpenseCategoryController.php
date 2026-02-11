<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Expense;
use App\Models\Activity;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\JsonResponse;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of expense categories (shop-scoped; super admin sees all).
     */
    public function index(Request $request)
    {
        $row = (int) $request->get('row', 50);
        if ($row < 1 || $row > 100) {
            $row = 50;
        }

        $authUser = auth()->user();
        $query = Expense::query()->withCount('activities');

        if ($authUser && $authUser->shop_id) {
            $query->where('shop_id', $authUser->shop_id);
        }

        $search = $request->get('search');
        if ($search) {
            $query->where('expense_title', 'like', '%' . $search . '%');
        }

        $query->sortable();
        if (!$request->has('sort')) {
            $query->orderBy('id', 'desc');
        }

        $categories = $query->paginate($row)->appends($request->query());

        return view('expense-categories.index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a new expense category (modal/AJAX or form submit).
     */
    public function store(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'expense_title' => 'required|string|max:255',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser?->shop_id;
        if (!$shopId) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'You must be assigned to a shop to create an expense category.'], 422);
            }
            return back()->withErrors(['expense_title' => 'You must be assigned to a shop to create an expense category.'])->withInput();
        }

        $title = trim($request->input('expense_title'));
        $category = Expense::create([
            'expense_title' => $title,
            'shop_id'       => $shopId,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Expense category has been created.',
                'category' => ['id' => $category->id, 'expense_title' => $category->expense_title],
            ]);
        }

        return Redirect::route('expense-categories.index')->with('success', 'Expense category has been created.');
    }

    /**
     * Update the specified expense category (modal/AJAX or form submit).
     */
    public function update(Request $request, Expense $expense_category): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $this->ensureShopAccess($expense_category);

        $request->validate([
            'expense_title' => 'required|string|max:255',
        ]);

        $expense_category->update([
            'expense_title' => trim($request->input('expense_title')),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Expense category has been updated.',
                'category' => ['id' => $expense_category->id, 'expense_title' => $expense_category->expense_title],
            ]);
        }

        return Redirect::route('expense-categories.index')->with('success', 'Expense category has been updated.');
    }

    /**
     * Remove the specified expense category (soft delete). Block if any activities use it.
     */
    public function destroy(Request $request, Expense $expense_category): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $this->ensureShopAccess($expense_category);

        $count = $expense_category->activities()->count();
        if ($count > 0) {
            $message = "Cannot delete this category because {$count} expense entry(ies) use it. Remove or reassign those entries first.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        $expense_category->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Expense category has been deleted.']);
        }

        return Redirect::route('expense-categories.index')->with('success', 'Expense category has been deleted.');
    }

    /**
     * Get expense entries (activities) for this category for AJAX popup.
     */
    public function entries(Expense $expense_category): JsonResponse
    {
        $this->ensureShopAccess($expense_category);

        $entries = Activity::query()
            ->where('expense_id', $expense_category->id)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'description', 'date', 'activity_cost', 'payment_method']);

        return response()->json([
            'category' => ['id' => $expense_category->id, 'expense_title' => $expense_category->expense_title],
            'entries'  => $entries,
        ]);
    }

    protected function ensureShopAccess(Expense $category): void
    {
        $authUser = auth()->user();
        if (!$authUser) {
            abort(403, 'You must be authenticated.');
        }
        if (!$authUser->shop_id) {
            return;
        }
        if ($category->shop_id !== $authUser->shop_id) {
            abort(403, 'You do not have access to this expense category.');
        }
    }
}
