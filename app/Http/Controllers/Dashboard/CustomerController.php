<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;
use Carbon\Carbon;
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

        return view('customers.index', [
            'customers' => $customersQuery->paginate($row)->appends(request()->query()),
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
            // 'name' => 'required|string|max:50', // Removed from UI - will be set from shopname
            // 'email' => 'required|email|max:50|unique:customers,email', // Removed from validation - may be needed in future
            'phone' => 'required|string|max:15|unique:customers,phone',
            'shopname' => 'required|string|max:50',
            'account_holder' => 'max:50',
            // 'account_number' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_name' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_branch' => 'max:50', // Removed from UI - may be needed in future
            // 'city' => 'required|string|max:50', // Removed from UI - may be needed in future
            'address' => 'required|string|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'credit_days' => 'required|integer|min:0',
        ];

        $validatedData = $request->validate($rules);
        
        // Copy shopname to name field for backward compatibility
        $validatedData['name'] = $validatedData['shopname'];

        // Default numeric credit fields when missing
        $validatedData['credit_amount'] = $request->input('credit_amount', 0);
        $validatedData['credit_limit'] = $request->input('credit_limit', 0);
        $validatedData['credit_days'] = $request->input('credit_days', 0);
        
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
            // 'name' => 'required|string|max:50', // Removed from UI - will be set from shopname
            // 'email' => 'required|email|max:50|unique:customers,email,'.$customer->id, // Removed from validation - may be needed in future
            'phone' => 'required|string|max:15|unique:customers,phone,'.$customer->id,
            'shopname' => 'required|string|max:50',
            'account_holder' => 'max:50',
            // 'account_number' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_name' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_branch' => 'max:50', // Removed from UI - may be needed in future
            // 'city' => 'required|string|max:50', // Removed from UI - may be needed in future
            'address' => 'required|string|max:100',
            'credit_limit' => 'required|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
            'credit_days' => 'required|integer|min:0',
        ];

        $validatedData = $request->validate($rules);
        
        // Copy shopname to name field for backward compatibility
        $validatedData['name'] = $validatedData['shopname'];

        // Default numeric credit fields when missing
        $validatedData['credit_amount'] = $request->input('credit_amount', 0);
        $validatedData['credit_limit'] = $request->input('credit_limit', 0);
        $validatedData['credit_days'] = $request->input('credit_days', 0);
        
        // Set email to null if not provided or empty
        $validatedData['email'] = $request->filled('email') && !empty($request->email) ? $request->email : null;

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/customers/';

            /**
             * Delete photo if exists.
             */
            if($customer->photo){
                Storage::delete($path . $customer->photo);
            }

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        Customer::where('id', $customer->id)->update($validatedData);

        return Redirect::route('customers.index')->with('success', 'Customer has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $this->ensureShopAccess($customer);
        
        /**
         * Delete photo if exists.
         */
        if($customer->photo){
            Storage::delete('public/customers/' . $customer->photo);
        }

        Customer::destroy($customer->id);

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

        // Opening balance: only when date filter is not "all"
        $openingBalance = 0.0;
        if ($dateFilter !== 'all' && $fromDateTime) {
            $openingBalance = (float) (clone $baseQuery())
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

        // Closing balance
        $closingBalance = $dateFilter === 'all'
            ? ($totalDebits - $totalCredits)
            : ($openingBalance + $totalDebits - $totalCredits);

        // Ledger rows: same date filter (all = no date range; else between from_date and to_date)
        $rowsQuery = $baseQuery();
        if ($dateFilter !== 'all' && $fromDateTime && $toDateTime) {
            $rowsQuery->whereBetween('transaction_date', [$fromDateTime, $toDateTime]);
        }
        $rows = $rowsQuery->orderBy('transaction_date')->orderBy('id')
            ->get(['id', 'source_type', 'source_id', 'direction', 'amount', 'transaction_date', 'description']);

        // Resolve invoice numbers for sale entries (one query)
        $saleSourceIds = $rows->where('source_type', AccountTransaction::SOURCE_SALE)->pluck('source_id')->unique()->filter()->values()->all();
        $invoiceNos = [];
        if (!empty($saleSourceIds)) {
            $invoiceNos = Order::query()->whereIn('id', $saleSourceIds)->pluck('invoice_no', 'id')->all();
        }

        $transactions = [];
        $runningBalance = $openingBalance;

        foreach ($rows as $r) {
            $debit = $r->direction === AccountTransaction::DIRECTION_DEBIT ? (float) $r->amount : 0.0;
            $credit = $r->direction === AccountTransaction::DIRECTION_CREDIT ? (float) $r->amount : 0.0;
            $runningBalance += $debit - $credit;

            if ($r->source_type === AccountTransaction::SOURCE_SALE) {
                $reference = $invoiceNos[$r->source_id] ?? ('#' . $r->source_id);
            } elseif ($r->source_type === AccountTransaction::SOURCE_CUSTOMER_PAYMENT) {
                $reference = 'Payment';
            } else {
                $reference = $r->description ?? '—';
            }

            $transactions[] = [
                'date' => $r->transaction_date->format('Y-m-d'),
                'reference' => $reference,
                'description' => $r->description ?? '—',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
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
