<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Services\Ledger\LedgerBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class SupplierPaymentController extends Controller
{
    /**
     * Show the form for recording a supplier payment (reduces supplier balance).
     */
    public function create(LedgerBalanceService $balanceService)
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $suppliers = Supplier::query()
            ->orderBy('shopname')
            ->get(['id', 'name', 'shopname', 'shop_id']);

        $shopId = $authUser->shop_id ?? ActiveShop::current()?->id;
        $shopBanks = collect();
        if ($shopId) {
            $shopBanks = DB::table('bank_shop')
                ->where('bank_shop.shop_id', $shopId)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->select('bank_shop.id', 'banks.name')
                ->orderBy('banks.name')
                ->get();
        }

        // All supplier payments: rows with source_type = supplier_payment and account_type = supplier (one per payment)
        $paymentRowsQuery = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SUPPLIER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');
        $paymentRows = $paymentRowsQuery->paginate(request('row', 15), ['*'], 'page');

        $supplierIds = $paymentRows->pluck('account_ref_id')->unique()->filter()->values()->all();
        $suppliersById = $supplierIds ? Supplier::whereIn('id', $supplierIds)->get()->keyBy('id') : collect();

        // Payment method from sibling cash/bank row (same shop, date, amount, description)
        $paymentMethodByKey = [];
        if ($paymentRows->isNotEmpty()) {
            $cashBankQuery = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_SUPPLIER_PAYMENT)
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
                ->whereIn('transaction_date', $paymentRows->pluck('transaction_date')->unique());
            foreach ($cashBankQuery->get() as $r) {
                $key = $r->shop_id . '|' . $r->transaction_date . '|' . (string) $r->amount . '|' . (string) ($r->description ?? '');
                $paymentMethodByKey[$key] = $r->account_type;
            }
        }

        $payments = $paymentRows->getCollection()->map(function ($row) use ($suppliersById, $paymentMethodByKey) {
            $key = $row->shop_id . '|' . $row->transaction_date . '|' . (string) $row->amount . '|' . (string) ($row->description ?? '');
            return (object) [
                'id' => $row->id,
                'supplier_id' => $row->account_ref_id,
                'supplier_name' => $suppliersById->get($row->account_ref_id)?->shopname ?: $suppliersById->get($row->account_ref_id)?->name ?? '—',
                'transaction_date' => $row->transaction_date,
                'amount' => $row->amount,
                'description' => $row->description,
                'payment_method' => $paymentMethodByKey[$key] ?? '—',
            ];
        });
        $paymentRows->setCollection($payments);

        return view('supplier-payments.create', [
            'suppliers' => $suppliers,
            'shopBanks' => $shopBanks,
            'payments' => $paymentRows,
        ]);
    }

    /**
     * Store supplier payment (double-entry): (1) supplier debit (we owe less), (2) cash/bank credit (money out).
     * Rule: Paying supplier → decrease payable (debit supplier), decrease asset (credit cash/bank).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|numeric|exists:suppliers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank',
            'shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
            'description' => 'nullable|string|max:500',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser->shop_id ?? ActiveShop::current()?->id;
        if (!$shopId) {
            return Redirect::back()->withErrors(['supplier_id' => 'Please select a shop context.'])->withInput();
        }

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        if ($authUser->shop_id && $supplier->shop_id && $supplier->shop_id != $authUser->shop_id) {
            return Redirect::back()->withErrors(['supplier_id' => 'Supplier does not belong to your shop.'])->withInput();
        }

        if ($validated['payment_method'] === 'bank' && empty($validated['shop_bank_id'])) {
            return Redirect::back()->withErrors(['shop_bank_id' => 'Please select a bank when payment method is Bank.'])->withInput();
        }

        $amount = (float) $validated['amount'];
        $transactionDate = Carbon::parse($validated['payment_date'])->toDateString();
        $isBank = $validated['payment_method'] === 'bank';
        $accountRefId = $isBank ? (int) $validated['shop_bank_id'] : null;

        DB::transaction(function () use ($validated, $shopId, $amount, $transactionDate, $isBank, $accountRefId) {
            // Row 1: Supplier DEBIT — payable decrease (we owe less)
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
                'account_ref_id' => $validated['supplier_id'],
                'direction' => AccountTransaction::DIRECTION_DEBIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_SUPPLIER_PAYMENT,
                'source_id' => null,
                'description' => $validated['description'] ?? 'Supplier payment',
                'transaction_date' => $transactionDate,
            ]);

            // Row 2: Cash/Bank CREDIT — money paid out (asset decrease) — money paid out (asset decrease)
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH,
                'account_ref_id' => $accountRefId,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_SUPPLIER_PAYMENT,
                'source_id' => null,
                'description' => $validated['description'] ?? 'Supplier payment',
                'transaction_date' => $transactionDate,
            ]);
        });

        return Redirect::route('supplier-payments.create')->with('success', 'Supplier payment recorded successfully.');
    }
}
