<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\SaleReturn;
use App\Services\CustomerOpeningBalanceService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $customersQuery = Customer::with('shop.parent')
            ->filter(request(['search']))
            ->sortable();

        $customers = $customersQuery->paginate($row)->appends(request()->query());

        $customerIds = $customers->getCollection()->pluck('id')->filter()->values();
        $orderAgg = collect();
        $saleReturnAgg = collect();
        $paymentAgg = collect();
        if ($customerIds->isNotEmpty()) {
            $orderAgg = Order::query()
                ->whereIn('customer_id', $customerIds->all())
                ->selectRaw('customer_id, COALESCE(SUM(total), 0) as total_sales')
                ->groupBy('customer_id')
                ->get()
                ->keyBy('customer_id');

            $saleReturnAgg = SaleReturn::query()
                ->whereIn('customer_id', $customerIds->all())
                ->selectRaw('customer_id, COALESCE(SUM(total), 0) as total_sale_returns')
                ->groupBy('customer_id')
                ->get()
                ->keyBy('customer_id');

            $paymentAgg = AccountTransaction::query()
                ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
                ->whereIn('account_ref_id', $customerIds->all())
                ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
                ->where('direction', AccountTransaction::DIRECTION_CREDIT)
                ->selectRaw('account_ref_id as customer_id, COALESCE(SUM(amount), 0) as total_paid')
                ->groupBy('account_ref_id')
                ->get()
                ->keyBy('customer_id');
        }

        $customers->getCollection()->transform(function ($customer) use ($orderAgg, $saleReturnAgg, $paymentAgg) {
            $orderRow = $orderAgg->get($customer->id);
            $returnRow = $saleReturnAgg->get($customer->id);
            $payRow = $paymentAgg->get($customer->id);
            $customer->total_sales_amount = (float) ($orderRow->total_sales ?? 0);
            $customer->total_sale_return_amount = (float) ($returnRow->total_sale_returns ?? 0);
            $customer->total_paid_amount = (float) ($payRow->total_paid ?? 0);
            return $customer;
        });

        return view('customers.index', [
            'customers' => $customers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('customers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'photo' => 'image|file|max:1024',
            'phone' => 'required|string|max:15|unique:customers,phone',
            'shopname' => 'required|string|max:50',
            'account_holder' => 'max:50',
            'address' => 'required|string|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'credit_days' => 'required|integer|min:0',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_date' => 'nullable|date',
        ];

        $validatedData = $request->validate($rules);

        // Copy shopname to name field for backward compatibility
        $validatedData['name'] = $validatedData['shopname'];

        // Default numeric credit fields when missing
        $validatedData['credit_amount'] = $request->input('credit_amount', 0);
        $validatedData['credit_limit'] = $request->input('credit_limit', 0);
        $validatedData['credit_days'] = $request->input('credit_days', 0);
        $validatedData['opening_balance'] = (float) ($request->input('opening_balance') ?? 0);
        $validatedData['opening_balance_date'] = $request->filled('opening_balance_date')
            ? Carbon::parse($request->input('opening_balance_date'))
            : null;

        // Set email to null if not provided or empty
        $validatedData['email'] = $request->filled('email') && !empty($request->email) ? $request->email : null;

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/customers/';

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        $customer = Customer::create($validatedData);

        // If opening balance is set, post to ledger and sync credit_amount (source of truth)
        if ($customer->shop_id && abs($validatedData['opening_balance']) > 0.001) {
            app(CustomerOpeningBalanceService::class)->postOpeningBalance(
                $customer,
                $validatedData['opening_balance'],
                $validatedData['opening_balance_date'] ?? $customer->created_at
            );
            $customer->refresh();
        }

        // If AJAX request, return JSON response
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer has been created successfully!',
                'customer' => [
                    'id' => $customer->id,
                    'shopname' => $customer->shopname,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'credit_limit' => $customer->credit_limit ?? 0,
                    'credit_amount' => $customer->credit_amount ?? 0,
                ]
            ]);
        }

        return Redirect::route('customers.index')->with('success', 'Customer has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $this->ensureShopAccess($customer);
        
        return view('customers.show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show credit trail for a customer (orders on credit + payments applied).
     */
    public function creditLog(Customer $customer)
    {
        $this->ensureShopAccess($customer);

        $orders = Order::where('customer_id', $customer->id)
            ->where('due', '>', 0)
            ->select('id', 'invoice_no', 'due', 'total', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = PaymentLog::with(['order:id,customer_id,invoice_no,total'])
            ->whereHas('order', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $events = collect();

        foreach ($orders as $order) {
            $events->push([
                'date' => $order->created_at,
                'type' => 'Order Credit',
                'invoice_no' => $order->invoice_no,
                'order_id' => $order->id,
                'total' => $order->total,
                'amount' => $order->due,
                'direction' => 'increase',
            ]);
        }

        foreach ($payments as $payment) {
            $events->push([
                'date' => $payment->created_at,
                'type' => 'Payment',
                'invoice_no' => optional($payment->order)->invoice_no,
                'order_id' => optional($payment->order)->id,
                'total' => optional($payment->order)->total,
                'amount' => $payment->amount_paid,
                'direction' => 'decrease',
            ]);
        }

        $events = $events->sortByDesc('date')->values();

        return view('customers.credit-log', [
            'customer' => $customer,
            'events' => $events,
            'current_credit' => $customer->credit_amount ?? 0,
            'credit_limit' => $customer->credit_limit ?? 0,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $this->ensureShopAccess($customer);
        
        return view('customers.edit', [
            'customer' => $customer
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $this->ensureShopAccess($customer);

        $rules = [
            'photo' => 'image|file|max:1024',
            'phone' => 'required|string|max:15|unique:customers,phone,'.$customer->id,
            'shopname' => 'required|string|max:50',
            'account_holder' => 'max:50',
            'address' => 'required|string|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'credit_days' => 'required|integer|min:0',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_date' => 'nullable|date',
        ];

        $validatedData = $request->validate($rules);

        // Copy shopname to name field for backward compatibility
        $validatedData['name'] = $validatedData['shopname'];

        // Default numeric credit fields when missing
        $validatedData['credit_limit'] = $request->input('credit_limit', 0);
        $validatedData['credit_days'] = $request->input('credit_days', 0);
        $openingBalance = (float) ($request->input('opening_balance') ?? 0);
        $openingBalanceDate = $request->filled('opening_balance_date')
            ? Carbon::parse($request->input('opening_balance_date'))
            : null;

        // Set email to null if not provided or empty
        $validatedData['email'] = $request->filled('email') && !empty($request->email) ? $request->email : null;

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/customers/';

            if ($customer->photo) {
                Storage::delete($path . $customer->photo);
            }

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        // Update non-ledger fields first (credit_amount is synced from ledger below)
        $updateData = Arr::except($validatedData, ['credit_amount', 'opening_balance', 'opening_balance_date']);
        Customer::where('id', $customer->id)->update($updateData);

        // Opening balance: replace ledger entry and sync credit_amount (one active customer_opening per customer)
        if ($customer->shop_id !== null) {
            app(CustomerOpeningBalanceService::class)->updateOpeningBalance(
                $customer->fresh(),
                $openingBalance,
                $openingBalanceDate
            );
        }

        return Redirect::route('customers.index')->with('success', 'Customer has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     * Customer cannot be deleted if they have any orders or payments.
     * Opening balance entries are ignored for the check and soft-deleted with the customer.
     */
    public function destroy(Request $request, Customer $customer)
    {
        $this->ensureShopAccess($customer);

        // Block deletion only if customer has orders or payments (ignore opening balance)
        $hasOrders = Order::where('customer_id', $customer->id)->exists();
        $hasPayments = AccountTransaction::where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('account_ref_id', $customer->id)
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
            ->exists();

        if ($hasOrders || $hasPayments) {
            $message = 'Customer cannot be deleted because it has some record histories (orders or payments).';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return Redirect::route('customers.index')->with('error', $message);
        }

        // Soft-delete opening balance entries for this customer
        AccountTransaction::where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('account_ref_id', $customer->id)
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_OPENING)
            ->delete();

        /**
         * Delete photo if exists.
         */
        if ($customer->photo) {
            Storage::delete('public/customers/' . $customer->photo);
        }

        Customer::destroy($customer->id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer has been deleted!',
                'redirect' => route('customers.index'),
            ]);
        }

        return Redirect::route('customers.index')->with('success', 'Customer has been deleted!');
    }

    /**
     * Display customer ledger from account_transactions only (journal-based).
     * No invoice/order table for calculations; running balance from ledger entries.
     */
    public function ledger(Customer $customer, Request $request)
    {
        $this->ensureShopAccess($customer);
        $ledgerData = $this->buildCustomerLedgerData($customer, $request);

        return view('customers.ledger', array_merge($ledgerData, ['customer' => $customer]));
    }

    /**
     * Build ledger data from account_transactions for a customer.
     * Journal-based: all figures from account_transactions only. Respects date filter and shop_id.
     */
    protected function buildCustomerLedgerData(Customer $customer, Request $request): array
    {
        $dateFilter = $request->get('date_filter', 'today');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $fromDateTime = null;
        $toDateTime = null;
        $startDateStr = null;
        $endDateStr = null;

        if ($dateFilter === 'all') {
            // from_date = null, to_date = null
        } elseif ($dateFilter === 'today') {
            $fromDateTime = Carbon::today()->startOfDay();
            $toDateTime = Carbon::today()->endOfDay();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        } elseif ($dateFilter === 'yesterday') {
            $fromDateTime = Carbon::yesterday()->startOfDay();
            $toDateTime = Carbon::yesterday()->endOfDay();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        } elseif ($dateFilter === 'last_7_days') {
            $fromDateTime = Carbon::today()->subDays(6)->startOfDay();
            $toDateTime = Carbon::today()->endOfDay();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        } elseif ($dateFilter === 'current_month') {
            $fromDateTime = Carbon::now()->startOfMonth();
            $toDateTime = Carbon::now()->endOfMonth();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        } elseif ($dateFilter === 'last_30_days') {
            $fromDateTime = Carbon::today()->subDays(29)->startOfDay();
            $toDateTime = Carbon::today()->endOfDay();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        } elseif ($dateFilter === 'custom' && $startDate && $endDate) {
            $fromDateTime = Carbon::parse($startDate)->startOfDay();
            $toDateTime = Carbon::parse($endDate)->endOfDay();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        } else {
            $dateFilter = 'today';
            $fromDateTime = Carbon::today()->startOfDay();
            $toDateTime = Carbon::today()->endOfDay();
            $startDateStr = $fromDateTime->format('Y-m-d');
            $endDateStr = $toDateTime->format('Y-m-d');
        }

        $shopId = $customer->shop_id;
        $customerId = $customer->id;

        $baseQuery = function () use ($shopId, $customerId) {
            return AccountTransaction::query()
                ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
                ->where('account_ref_id', $customerId)
                ->where('shop_id', $shopId);
        };

        // Calculated opening balance for running balance (sum of all entries before period start)
        $calculatedOpeningBalance = 0.0;
        if ($dateFilter !== 'all' && $fromDateTime) {
            $calculatedOpeningBalance = (float) (clone $baseQuery())
                ->where('transaction_date', '<', $fromDateTime)
                ->selectRaw("SUM(CASE WHEN direction = ? THEN amount WHEN direction = ? THEN -amount ELSE 0 END) as bal", [
                    AccountTransaction::DIRECTION_DEBIT,
                    AccountTransaction::DIRECTION_CREDIT,
                ])
                ->value('bal') ?? 0;
        }

        // Period total debits (aggregated)
        $debitsQuery = (clone $baseQuery())->where('direction', AccountTransaction::DIRECTION_DEBIT);
        if ($dateFilter !== 'all' && $fromDateTime && $toDateTime) {
            $debitsQuery->whereBetween('transaction_date', [$fromDateTime, $toDateTime]);
        }
        $totalDebits = (float) $debitsQuery->sum('amount');

        // Period total credits (aggregated)
        $creditsQuery = (clone $baseQuery())->where('direction', AccountTransaction::DIRECTION_CREDIT);
        if ($dateFilter !== 'all' && $fromDateTime && $toDateTime) {
            $creditsQuery->whereBetween('transaction_date', [$fromDateTime, $toDateTime]);
        }
        $totalCredits = (float) $creditsQuery->sum('amount');

        // Ledger rows: period rows + always include customer_opening entry for this customer
        $rowsQuery = $baseQuery();
        if ($dateFilter !== 'all' && $fromDateTime && $toDateTime) {
            $rowsQuery->whereBetween('transaction_date', [$fromDateTime, $toDateTime]);
        }
        $rows = $rowsQuery->orderBy('transaction_date')->orderBy('id')
            ->get(['id', 'source_type', 'source_id', 'direction', 'amount', 'transaction_date', 'description']);

        // Always include the customer_opening entry so it appears in the ledger table
        $openingEntryIds = $rows->pluck('id')->all();
        $openingRows = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('account_ref_id', $customerId)
            ->where('shop_id', $shopId)
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_OPENING)
            ->get(['id', 'source_type', 'source_id', 'direction', 'amount', 'transaction_date', 'description']);
        $openingRows = $openingRows->filter(fn ($r) => !in_array($r->id, $openingEntryIds, true));
        if ($openingRows->isNotEmpty()) {
            $rows = $rows->merge($openingRows)->sortBy('transaction_date')->sortBy('id')->values();
        }

        // Resolve invoice numbers for sale entries (one query)
        $saleSourceIds = $rows->where('source_type', AccountTransaction::SOURCE_SALE)->pluck('source_id')->unique()->filter()->values()->all();
        $invoiceNos = [];
        if (!empty($saleSourceIds)) {
            $invoiceNos = Order::query()->whereIn('id', $saleSourceIds)->pluck('invoice_no', 'id')->all();
        }

        // Resolve sale return numbers for adjustment entries (sale returns post as SOURCE_ADJUSTMENT with source_id = sale_return.id)
        $adjustmentReturnIds = $rows->where('source_type', AccountTransaction::SOURCE_ADJUSTMENT)->pluck('source_id')->unique()->filter()->values()->all();
        $saleReturnNos = [];
        if (!empty($adjustmentReturnIds)) {
            $saleReturnNos = SaleReturn::query()->whereIn('id', $adjustmentReturnIds)->pluck('return_no', 'id')->all();
        }

        $transactions = [];
        $runningBalance = $calculatedOpeningBalance;

        foreach ($rows as $r) {
            $debit = $r->direction === AccountTransaction::DIRECTION_DEBIT ? (float) $r->amount : 0.0;
            $credit = $r->direction === AccountTransaction::DIRECTION_CREDIT ? (float) $r->amount : 0.0;

            // Injected opening row (before period): already in calculatedOpeningBalance, don't add again
            $isInjectedOpening = $dateFilter !== 'all' && $fromDateTime && $r->source_type === AccountTransaction::SOURCE_CUSTOMER_OPENING && $r->transaction_date < $fromDateTime;
            if ($isInjectedOpening) {
                $rowBalance = $calculatedOpeningBalance;
            } else {
                $runningBalance += $debit - $credit;
                $rowBalance = $runningBalance;
            }

            // Reference text for each row type.
            // - SOURCE_SALE: show invoice number and link to invoice
            // - SOURCE_ADJUSTMENT (sale return): show sale return number and link to sale return
            // - SOURCE_CUSTOMER_PAYMENT: generic Payment reference with payment detail link
            // - SOURCE_CUSTOMER_OPENING: Opening Balance label
            // - Others: use description as-is
            $saleReturnId = null;
            if ($r->source_type === AccountTransaction::SOURCE_SALE) {
                $reference = $invoiceNos[$r->source_id] ?? ('#' . $r->source_id);
            } elseif ($r->source_type === AccountTransaction::SOURCE_ADJUSTMENT && isset($saleReturnNos[$r->source_id])) {
                $reference = $saleReturnNos[$r->source_id];
                $saleReturnId = (int) $r->source_id;
            } elseif ($r->source_type === AccountTransaction::SOURCE_CUSTOMER_PAYMENT) {
                $reference = 'Payment';
            } elseif ($r->source_type === AccountTransaction::SOURCE_CUSTOMER_OPENING) {
                $reference = 'Opening Balance';
            } else {
                $reference = $r->description ?? '—';
            }

            $transactions[] = [
                'date' => $r->transaction_date->format('Y-m-d'),
                'reference' => $reference,
                'description' => $r->description ?? '—',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $rowBalance,
                'order_id' => $r->source_type === AccountTransaction::SOURCE_SALE ? (int) $r->source_id : null,
                'payment_transaction_id' => $r->source_type === AccountTransaction::SOURCE_CUSTOMER_PAYMENT ? (int) $r->id : null,
                'sale_return_id' => $saleReturnId,
                'is_opening' => $r->source_type === AccountTransaction::SOURCE_CUSTOMER_OPENING,
            ];
        }

        // Closing balance = final running balance (so footer and box match last row)
        $closingBalance = $runningBalance;

        // Opening balance box: always show customer's opening_balance from customers table
        $openingBalanceDisplay = (float) ($customer->opening_balance ?? 0);

        return [
            'transactions' => $transactions,
            'opening_balance' => $openingBalanceDisplay,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'closing_balance' => $closingBalance,
            'date_filter' => $dateFilter,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
        ];
    }

    /**
     * Export ledger as PDF (same data as ledger view: account_transactions only).
     */
    public function ledgerPdf(Customer $customer, Request $request)
    {
        $this->ensureShopAccess($customer);
        $ledgerData = $this->buildCustomerLedgerData($customer, $request);

        $pdf = Pdf::loadView('customers.ledger-pdf', array_merge($ledgerData, [
            'customer' => $customer,
        ]));

        $filename = 'customer-ledger-' . $customer->id;
        if (!empty($ledgerData['start_date']) && !empty($ledgerData['end_date'])) {
            $filename .= '-' . $ledgerData['start_date'] . '-to-' . $ledgerData['end_date'];
        } else {
            $filename .= '-all';
        }
        return $pdf->download($filename . '.pdf');
    }

    /**
     * Ensure the current user has access to the customer based on shop.
     */
    protected function ensureShopAccess(Customer $customer): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            // Child shop or parent shop user - must belong to allowed shops
            if ($customer->shop_id && !$visibleShopIds->contains($customer->shop_id)) {
                abort(403, 'You do not have access to this customer.');
            }
        }
        // Super admin can access all customers
    }
}
