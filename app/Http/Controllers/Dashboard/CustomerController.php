<?php

namespace App\Http\Controllers\Dashboard;

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
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customersQuery = Customer::with('shop.parent')
            ->filter(request(['search']))
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            // Child shop or parent shop user - only see their allowed shops
            $customersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            // Super admin - can see all customers (including unassigned)
            $customersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

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

        // Set shop_id from logged-in user
        $validatedData['shop_id'] = auth()->user()->shop_id;

        Customer::create($validatedData);

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
     * Display customer ledger with all transactions.
     */
    public function ledger(Customer $customer, Request $request)
    {
        $this->ensureShopAccess($customer);

        // Get date filter parameters
        $dateFilter = $request->get('date_filter', 'current_month'); // current_month, last_30_days, custom
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Set date range based on filter
        if ($dateFilter === 'current_month') {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        } elseif ($dateFilter === 'last_30_days') {
            $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
        } elseif ($dateFilter === 'custom' && $startDate && $endDate) {
            // Use provided dates
            $startDate = Carbon::parse($startDate)->format('Y-m-d');
            $endDate = Carbon::parse($endDate)->format('Y-m-d');
        } else {
            // Default to current month if custom dates not provided
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
            $dateFilter = 'current_month';
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Get all orders for this customer (exclude deleted orders)
        $orders = Order::where('customer_id', $customer->id)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderBy('order_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all payments for this customer's orders (exclude deleted payments)
        $payments = PaymentLog::with(['order'])
            ->whereHas('order', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate opening balance (credit amount before start date)
        $openingBalance = $this->calculateOpeningBalance($customer, $startDateTime);

        // Build transactions array
        $transactions = collect();

        // Add orders
        foreach ($orders as $order) {
            $transactions->push([
                'date' => $order->order_date,
                'datetime' => $order->created_at,
                'type' => 'Order',
                'type_badge' => $order->order_status === 'complete' ? 'badge-success' : 'badge-warning',
                'invoice_no' => $order->invoice_no,
                'order_id' => $order->id,
                'description' => 'Order - ' . $order->invoice_no,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total' => $order->total ?? 0,
                'paid' => $order->pay ?? 0,
                'due' => $order->due ?? 0,
                'debit' => $order->due ?? 0, // Credit added (debit from customer perspective)
                'credit' => 0,
                'is_order' => true,
            ]);
        }

        // Add payments
        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->created_at->format('Y-m-d'),
                'datetime' => $payment->created_at,
                'type' => 'Payment',
                'type_badge' => 'badge-info',
                'invoice_no' => optional($payment->order)->invoice_no,
                'order_id' => optional($payment->order)->id,
                'description' => 'Payment for ' . (optional($payment->order)->invoice_no ?? 'Order #' . optional($payment->order)->id),
                'order_status' => optional($payment->order)->order_status,
                'payment_status' => optional($payment->order)->payment_status,
                'total' => optional($payment->order)->total ?? 0,
                'paid' => $payment->amount_paid,
                'due' => 0,
                'debit' => 0,
                'credit' => $payment->amount_paid, // Payment received (credit from customer perspective)
                'is_order' => false,
            ]);
        }

        // Sort by datetime descending (newest first)
        $transactions = $transactions->sortByDesc('datetime')->values();

        // Calculate running balance
        $runningBalance = $openingBalance;
        $transactions = $transactions->map(function ($transaction) use (&$runningBalance) {
            $runningBalance = $runningBalance + $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $runningBalance;
            return $transaction;
        });

        // Calculate closing balance
        $closingBalance = $runningBalance;

        // Calculate summary totals
        $summary = [
            'total_orders' => $orders->count(),
            'total_order_amount' => $orders->sum('total'),
            'total_paid' => $orders->sum('pay'),
            'total_due' => $orders->sum('due'),
            'total_payments' => $payments->count(),
            'total_payment_amount' => $payments->sum('amount_paid'),
        ];

        return view('customers.ledger', [
            'customer' => $customer,
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'summary' => $summary,
            'date_filter' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Export ledger as PDF.
     */
    public function ledgerPdf(Customer $customer, Request $request)
    {
        $this->ensureShopAccess($customer);

        // Get the same data as ledger view
        $dateFilter = $request->get('date_filter', 'current_month');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($dateFilter === 'current_month') {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        } elseif ($dateFilter === 'last_30_days') {
            $startDate = Carbon::now()->subDays(30)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
        } elseif ($dateFilter === 'custom' && $startDate && $endDate) {
            $startDate = Carbon::parse($startDate)->format('Y-m-d');
            $endDate = Carbon::parse($endDate)->format('Y-m-d');
        } else {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $orders = Order::where('customer_id', $customer->id)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderBy('order_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = PaymentLog::with(['order'])
            ->whereHas('order', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'desc')
            ->get();

        $openingBalance = $this->calculateOpeningBalance($customer, $startDateTime);

        $transactions = collect();
        foreach ($orders as $order) {
            $transactions->push([
                'date' => $order->order_date,
                'datetime' => $order->created_at,
                'type' => 'Order',
                'invoice_no' => $order->invoice_no,
                'order_id' => $order->id,
                'description' => 'Order - ' . $order->invoice_no,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'total' => $order->total ?? 0,
                'paid' => $order->pay ?? 0,
                'due' => $order->due ?? 0,
                'debit' => $order->due ?? 0,
                'credit' => 0,
                'is_order' => true,
            ]);
        }

        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->created_at->format('Y-m-d'),
                'datetime' => $payment->created_at,
                'type' => 'Payment',
                'invoice_no' => optional($payment->order)->invoice_no,
                'order_id' => optional($payment->order)->id,
                'description' => 'Payment for ' . (optional($payment->order)->invoice_no ?? 'Order #' . optional($payment->order)->id),
                'order_status' => optional($payment->order)->order_status,
                'payment_status' => optional($payment->order)->payment_status,
                'total' => optional($payment->order)->total ?? 0,
                'paid' => $payment->amount_paid,
                'due' => 0,
                'debit' => 0,
                'credit' => $payment->amount_paid,
                'is_order' => false,
            ]);
        }

        $transactions = $transactions->sortByDesc('datetime')->values();
        $runningBalance = $openingBalance;
        $transactions = $transactions->map(function ($transaction) use (&$runningBalance) {
            $runningBalance = $runningBalance + $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $runningBalance;
            return $transaction;
        });

        $closingBalance = $runningBalance;
        $summary = [
            'total_orders' => $orders->count(),
            'total_order_amount' => $orders->sum('total'),
            'total_paid' => $orders->sum('pay'),
            'total_due' => $orders->sum('due'),
            'total_payments' => $payments->count(),
            'total_payment_amount' => $payments->sum('amount_paid'),
        ];

        $pdf = Pdf::loadView('customers.ledger-pdf', [
            'customer' => $customer,
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'summary' => $summary,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $pdf->download('customer-ledger-' . $customer->id . '-' . $startDate . '-to-' . $endDate . '.pdf');
    }

    /**
     * Calculate opening balance (credit amount before start date).
     */
    protected function calculateOpeningBalance(Customer $customer, Carbon $startDate): float
    {
        // Get all orders before start date (exclude deleted orders)
        $ordersBefore = Order::where('customer_id', $customer->id)
            ->where('order_date', '<', $startDate->format('Y-m-d'))
            ->get();

        // Get all payments before start date (exclude deleted payments)
        $paymentsBefore = PaymentLog::whereHas('order', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->where('created_at', '<', $startDate)
            ->get();

        // Calculate balance: sum of all dues minus sum of all payments
        $totalDue = $ordersBefore->sum('due');
        $totalPaid = $paymentsBefore->sum('amount_paid');

        return max(0, $totalDue - $totalPaid);
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
