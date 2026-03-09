<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payable;
use App\Models\PayableTransaction;
use App\Support\ActiveShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class PayableTransactionController extends Controller
{
    /**
     * Store a borrow or repayment transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payable_id' => 'required|integer|exists:payables,id',
            'date' => 'required|date',
            'type' => 'required|in:borrow,repayment',
            'amount' => 'required|numeric|min:0.01',
            'expected_return_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'redirect_to' => 'nullable|string|in:index',
        ]);

        $payable = Payable::findOrFail($validated['payable_id']);
        $this->ensureShopAccess($payable);

        if ($validated['type'] === 'repayment') {
            $balance = (float) $payable->payableTransactions()->where('type', 'borrow')->sum('amount')
                - (float) $payable->payableTransactions()->where('type', 'repayment')->sum('amount');
            if ($validated['amount'] > $balance) {
                $redirect = ($validated['redirect_to'] ?? '') === 'index'
                    ? Redirect::route('payables.index')
                    : Redirect::route('payables.show', $payable);
                return $redirect->withErrors(['amount' => 'Repayment amount cannot exceed balance (' . number_format($balance, 2) . ').'])->withInput(array_merge($validated, ['type' => 'repayment']));
            }
        }

        PayableTransaction::create([
            'payable_id' => $payable->id,
            'date' => $validated['date'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'expected_return_date' => $validated['expected_return_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if (($validated['redirect_to'] ?? '') === 'index') {
            return Redirect::route('payables.index')->with('success', 'Transaction has been added.');
        }

        return Redirect::route('payables.show', $payable)->with('success', 'Transaction has been added.');
    }

    /**
     * Remove the transaction (soft delete).
     */
    public function destroy($id)
    {
        $transaction = PayableTransaction::findOrFail($id);
        $payable = $transaction->payable;
        $this->ensureShopAccess($payable);

        $transaction->delete();

        return Redirect::route('payables.show', $payable)->with('success', 'Transaction has been deleted.');
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
