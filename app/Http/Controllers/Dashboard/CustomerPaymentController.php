<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Services\CustomerCreditService;
use App\Services\Ledger\LedgerBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class CustomerPaymentController extends Controller
{
    /**
     * Show the form for recording a customer payment (reduces customer balance).
     */
    public function create(LedgerBalanceService $balanceService)
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customers = Customer::query()
            ->where(function ($q) {
                $q->where('is_walkin', false)->orWhere('is_walkin', 0)->orWhereNull('is_walkin');
            })
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

        // All customer payments: rows with source_type = customer_payment and account_type = customer (one per payment)
        $paymentRowsQuery = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');
        $paymentRows = $paymentRowsQuery->paginate(request('row', 15), ['*'], 'page');

        $customerIds = $paymentRows->pluck('account_ref_id')->unique()->filter()->values()->all();
        $customersById = $customerIds ? Customer::whereIn('id', $customerIds)->get()->keyBy('id') : collect();

        // Payment method from sibling cash/bank row (same shop, date, amount, description)
        $paymentMethodByKey = [];
        if ($paymentRows->isNotEmpty()) {
            $cashBankQuery = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
                ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
                ->whereIn('transaction_date', $paymentRows->pluck('transaction_date')->unique());
            foreach ($cashBankQuery->get() as $r) {
                $key = $r->shop_id . '|' . $r->transaction_date . '|' . (string) $r->amount . '|' . (string) ($r->description ?? '');
                $paymentMethodByKey[$key] = $r->account_type;
            }
        }

        $payments = $paymentRows->getCollection()->map(function ($row) use ($customersById, $paymentMethodByKey) {
            $key = $row->shop_id . '|' . $row->transaction_date . '|' . (string)$row->amount . '|' . (string)($row->description ?? '');
            return (object)[
                'id' => $row->id,
                'customer_id' => $row->account_ref_id,
                'customer_name' => $customersById->get($row->account_ref_id)?->shopname ?: $customersById->get($row->account_ref_id)?->name ?? '—',
                'transaction_date' => $row->transaction_date,
                'amount' => $row->amount,
                'description' => $row->description,
                'payment_method' => $paymentMethodByKey[$key] ?? '—',
            ];
        });
        $paymentRows->setCollection($payments);

        return view('customer-payments.create', [
            'customers' => $customers,
            'shopBanks' => $shopBanks,
            'payments' => $paymentRows,
        ]);
    }

    /**
     * Store customer payment (double-entry): (1) cash/bank debit (money in), (2) customer credit (AR decrease).
     * Rule: Receiving payment → increase asset (debit cash/bank), decrease receivable (credit customer).
     * Also syncs customers.credit_amount via CustomerCreditService.
     */
    public function store(Request $request, CustomerCreditService $creditService)
    {
        $validated = $request->validate([
            'customer_id' => 'required|numeric|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank',
            'shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
            'description' => 'nullable|string|max:500',
        ]);

        $authUser = auth()->user();
        $shopId = $authUser->shop_id ?? ActiveShop::current()?->id;
        if (!$shopId) {
            return Redirect::back()->withErrors(['customer_id' => 'Please select a shop context.'])->withInput();
        }

        $customer = Customer::findOrFail($validated['customer_id']);
        if ($authUser->shop_id && $customer->shop_id && $customer->shop_id != $authUser->shop_id) {
            return Redirect::back()->withErrors(['customer_id' => 'Customer does not belong to your shop.'])->withInput();
        }

        if ($validated['payment_method'] === 'bank' && empty($validated['shop_bank_id'])) {
            return Redirect::back()->withErrors(['shop_bank_id' => 'Please select a bank when payment method is Bank.'])->withInput();
        }

        $amount = (float) $validated['amount'];
        $transactionDate = Carbon::parse($validated['payment_date'])->toDateString();
        $isBank = $validated['payment_method'] === 'bank';
        $accountRefId = $isBank ? (int) $validated['shop_bank_id'] : null;

        DB::transaction(function () use ($validated, $shopId, $amount, $transactionDate, $isBank, $accountRefId, $customer, $creditService) {
            // Row 1: Cash/Bank DEBIT — money received (asset increase)
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH,
                'account_ref_id' => $accountRefId,
                'direction' => AccountTransaction::DIRECTION_DEBIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_CUSTOMER_PAYMENT,
                'source_id' => null,
                'description' => $validated['description'] ?? 'Customer payment',
                'transaction_date' => $transactionDate,
            ]);

            // Row 2: Customer CREDIT — receivable decrease (customer owes less)
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                'account_ref_id' => $validated['customer_id'],
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_CUSTOMER_PAYMENT,
                'source_id' => null,
                'description' => $validated['description'] ?? 'Customer payment',
                'transaction_date' => $transactionDate,
            ]);

            // Sync customers.credit_amount (decrease by payment amount)
            $creditService->applyPayment($customer, $amount);
        });

        return Redirect::route('customer-payments.create')->with('success', 'Customer payment recorded successfully.');
    }
}
