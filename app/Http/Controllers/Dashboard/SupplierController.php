<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\PurchasePaymentLog;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\ActiveShop;

class SupplierController extends Controller
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

        $suppliersQuery = Supplier::query()
            ->filter(request(['search']))
            ->sortable();

        return view('suppliers.index', [
            'suppliers' => $suppliersQuery->paginate($row)->appends(request()->query()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('suppliers.create', [
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'photo' => 'image|file|max:1024',
            // 'name' => 'required|string|max:50', // Removed from UI - will be set from shopname
            // 'email' => 'required|email|max:50|unique:suppliers,email', // Removed from validation - may be needed in future
            'phone' => 'required|string|max:15|unique:suppliers,phone',
            'shopname' => 'required|string|max:50',
            'type' => 'required|string|max:25',
            'account_holder' => 'max:50',
            // 'account_number' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_name' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_branch' => 'max:50', // Removed from UI - may be needed in future
            // 'city' => 'required|string|max:50', // Removed from UI - may be needed in future
            'address' => 'required|string|max:100',
        ];

        $validatedData = $request->validate($rules);
        
        // Copy shopname to name field for backward compatibility
        $validatedData['name'] = $validatedData['shopname'];
        
        // Set email to null if not provided or empty
        $validatedData['email'] = $request->filled('email') && !empty($request->email) ? $request->email : null;
        
        // Default numeric credit fields when missing
        $validatedData['credit_amount'] = $request->input('credit_amount', 0);
        $validatedData['credit_limit'] = $request->input('credit_limit', 0);
        $validatedData['credit_days'] = $request->input('credit_days', 0);

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/suppliers/';

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        $supplier = Supplier::create($validatedData);

        // If AJAX request, return JSON response
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Supplier has been created successfully!',
                'supplier' => [
                    'id' => $supplier->id,
                    'shopname' => $supplier->shopname,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone,
                    'credit_limit' => $supplier->credit_limit ?? 0,
                    'credit_amount' => $supplier->credit_amount ?? 0,
                ]
            ]);
        }

        return Redirect::route('suppliers.index')->with('success', 'Supplier has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        $this->ensureShopAccess($supplier);

        return view('suppliers.show', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        $this->ensureShopAccess($supplier);

        return view('suppliers.edit', [
            'supplier' => $supplier
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $this->ensureShopAccess($supplier);

        $rules = [
            'photo' => 'image|file|max:1024',
            // 'name' => 'required|string|max:50', // Removed from UI - will be set from shopname
            // 'email' => 'required|email|max:50|unique:suppliers,email,'.$supplier->id, // Removed from validation - may be needed in future
            'phone' => 'required|string|max:15|unique:suppliers,phone,'.$supplier->id,
            'shopname' => 'required|string|max:50',
            'type' => 'required|string|max:25',
            'account_holder' => 'max:50',
            // 'account_number' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_name' => 'max:25', // Removed from UI - may be needed in future
            // 'bank_branch' => 'max:50', // Removed from UI - may be needed in future
            // 'city' => 'required|string|max:50', // Removed from UI - may be needed in future
            'address' => 'required|string|max:100',
        ];

        $validatedData = $request->validate($rules);
        
        // Copy shopname to name field for backward compatibility
        $validatedData['name'] = $validatedData['shopname'];
        
        // Set email to null if not provided or empty
        $validatedData['email'] = $request->filled('email') && !empty($request->email) ? $request->email : null;

        // Set shop_id from logged-in user's shop_id (SuperAdmin can have null shop_id)
        $authUser = auth()->user();
        if ($authUser->shop_id) {
            $validatedData['shop_id'] = $authUser->shop_id;
        }

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/suppliers/';

            /**
             * Delete photo if exists.
             */
            if($supplier->photo){
                Storage::delete($path . $supplier->photo);
            }

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        Supplier::where('id', $supplier->id)->update($validatedData);

        return Redirect::route('suppliers.index')->with('success', 'Supplier has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $this->ensureShopAccess($supplier);

        /**
         * Delete photo if exists.
         */
        if($supplier->photo){
            Storage::delete('public/suppliers/' . $supplier->photo);
        }

        Supplier::destroy($supplier->id);

        return Redirect::route('suppliers.index')->with('success', 'Supplier has been deleted!');
    }

    /**
     * Display supplier ledger with all transactions.
     */
    public function ledger(Supplier $supplier, Request $request)
    {
        $this->ensureShopAccess($supplier);

        // Get date filter parameters
        $dateFilter = $request->get('date_filter', 'current_month');
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
            $startDate = Carbon::parse($startDate)->format('Y-m-d');
            $endDate = Carbon::parse($endDate)->format('Y-m-d');
        } else {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
            $dateFilter = 'current_month';
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Get all purchases for this supplier (exclude deleted purchases)
        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all payments for this supplier's purchases (exclude deleted payments)
        $payments = PurchasePaymentLog::with(['purchase'])
            ->whereHas('purchase', function ($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id);
            })
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'desc')
            ->get();

        // Standalone supplier payments (from Payments → Supplier Payment) in date range
        $supplierPayments = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SUPPLIER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->where('account_ref_id', $supplier->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Calculate opening balance (credit amount before start date)
        $openingBalance = $this->calculateOpeningBalance($supplier, $startDateTime);

        // Build transactions array
        $transactions = collect();

        // Add purchases
        foreach ($purchases as $purchase) {
            $transactions->push([
                'date' => $purchase->purchase_date,
                'datetime' => $purchase->created_at,
                'type' => 'Purchase',
                'type_badge' => $purchase->purchase_status === 'complete' ? 'badge-success' : 'badge-warning',
                'purchase_no' => $purchase->purchase_no,
                'purchase_id' => $purchase->id,
                'description' => 'Purchase - ' . $purchase->purchase_no,
                'purchase_status' => $purchase->purchase_status,
                'payment_status' => $purchase->payment_status,
                'total' => $purchase->total ?? 0,
                'paid' => $purchase->pay ?? 0,
                'due' => $purchase->due ?? 0,
                'debit' => $purchase->due ?? 0, // Credit added (debit from supplier perspective)
                'credit' => 0,
                'is_purchase' => true,
            ]);
        }

        // Add purchase payments (payments against a specific purchase)
        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->created_at->format('Y-m-d'),
                'datetime' => $payment->created_at,
                'type' => 'Payment',
                'type_badge' => 'badge-info',
                'purchase_no' => optional($payment->purchase)->purchase_no,
                'purchase_id' => optional($payment->purchase)->id,
                'description' => 'Payment for ' . (optional($payment->purchase)->purchase_no ?? 'Purchase #' . optional($payment->purchase)->id),
                'purchase_status' => optional($payment->purchase)->purchase_status,
                'payment_status' => optional($payment->purchase)->payment_status,
                'total' => optional($payment->purchase)->total ?? 0,
                'paid' => $payment->amount_paid,
                'due' => 0,
                'debit' => 0,
                'credit' => $payment->amount_paid,
                'is_purchase' => false,
                'is_supplier_payment' => false,
                'payment_transaction_id' => null,
            ]);
        }

        // Add standalone supplier payments (Payments → Supplier Payment)
        foreach ($supplierPayments as $sp) {
            $transactions->push([
                'date' => $sp->transaction_date->format('Y-m-d'),
                'datetime' => $sp->transaction_date,
                'type' => 'Supplier Payment',
                'type_badge' => 'badge-info',
                'purchase_no' => null,
                'purchase_id' => null,
                'description' => $sp->description ?? 'Supplier payment',
                'purchase_status' => null,
                'payment_status' => null,
                'total' => 0,
                'paid' => (float) $sp->amount,
                'due' => 0,
                'debit' => 0,
                'credit' => (float) $sp->amount,
                'is_purchase' => false,
                'is_supplier_payment' => true,
                'payment_transaction_id' => $sp->id,
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

        // Calculate summary totals (include standalone supplier payments in payment totals)
        $supplierPaymentAmount = $supplierPayments->sum('amount');
        $summary = [
            'total_purchases' => $purchases->count(),
            'total_purchase_amount' => $purchases->sum('total'),
            'total_paid' => $purchases->sum('pay'),
            'total_due' => $purchases->sum('due'),
            'total_payments' => $payments->count() + $supplierPayments->count(),
            'total_payment_amount' => $payments->sum('amount_paid') + (float) $supplierPaymentAmount,
        ];

        return view('suppliers.ledger', [
            'supplier' => $supplier,
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
    public function ledgerPdf(Supplier $supplier, Request $request)
    {
        $this->ensureShopAccess($supplier);

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

        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->whereBetween('purchase_date', [$startDate, $endDate])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = PurchasePaymentLog::with(['purchase'])
            ->whereHas('purchase', function ($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id);
            })
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->orderBy('created_at', 'desc')
            ->get();

        $supplierPaymentsPdf = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SUPPLIER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->where('account_ref_id', $supplier->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $openingBalance = $this->calculateOpeningBalance($supplier, $startDateTime);

        $transactions = collect();
        foreach ($purchases as $purchase) {
            $transactions->push([
                'date' => $purchase->purchase_date,
                'datetime' => $purchase->created_at,
                'type' => 'Purchase',
                'purchase_no' => $purchase->purchase_no,
                'purchase_id' => $purchase->id,
                'description' => 'Purchase - ' . $purchase->purchase_no,
                'purchase_status' => $purchase->purchase_status,
                'payment_status' => $purchase->payment_status,
                'total' => $purchase->total ?? 0,
                'paid' => $purchase->pay ?? 0,
                'due' => $purchase->due ?? 0,
                'debit' => $purchase->due ?? 0,
                'credit' => 0,
                'is_purchase' => true,
            ]);
        }

        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->created_at->format('Y-m-d'),
                'datetime' => $payment->created_at,
                'type' => 'Payment',
                'purchase_no' => optional($payment->purchase)->purchase_no,
                'purchase_id' => optional($payment->purchase)->id,
                'description' => 'Payment for ' . (optional($payment->purchase)->purchase_no ?? 'Purchase #' . optional($payment->purchase)->id),
                'purchase_status' => optional($payment->purchase)->purchase_status,
                'payment_status' => optional($payment->purchase)->payment_status,
                'total' => optional($payment->purchase)->total ?? 0,
                'paid' => $payment->amount_paid,
                'due' => 0,
                'debit' => 0,
                'credit' => $payment->amount_paid,
                'is_purchase' => false,
            ]);
        }

        foreach ($supplierPaymentsPdf as $sp) {
            $transactions->push([
                'date' => $sp->transaction_date->format('Y-m-d'),
                'datetime' => $sp->transaction_date,
                'type' => 'Supplier Payment',
                'purchase_no' => null,
                'purchase_id' => null,
                'description' => $sp->description ?? 'Supplier payment',
                'purchase_status' => null,
                'payment_status' => null,
                'total' => 0,
                'paid' => (float) $sp->amount,
                'due' => 0,
                'debit' => 0,
                'credit' => (float) $sp->amount,
                'is_purchase' => false,
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
        $supplierPaymentAmountPdf = (float) $supplierPaymentsPdf->sum('amount');
        $summary = [
            'total_purchases' => $purchases->count(),
            'total_purchase_amount' => $purchases->sum('total'),
            'total_paid' => $purchases->sum('pay'),
            'total_due' => $purchases->sum('due'),
            'total_payments' => $payments->count() + $supplierPaymentsPdf->count(),
            'total_payment_amount' => $payments->sum('amount_paid') + $supplierPaymentAmountPdf,
        ];

        $pdf = Pdf::loadView('suppliers.ledger-pdf', [
            'supplier' => $supplier,
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'summary' => $summary,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $pdf->download('supplier-ledger-' . $supplier->id . '-' . $startDate . '-to-' . $endDate . '.pdf');
    }

    /**
     * Calculate opening balance (credit amount before start date).
     * Includes: dues from purchases, minus purchase payments, minus standalone supplier payments.
     */
    protected function calculateOpeningBalance(Supplier $supplier, Carbon $startDate): float
    {
        // Get all purchases before start date (exclude deleted purchases)
        $purchasesBefore = Purchase::where('supplier_id', $supplier->id)
            ->where('purchase_date', '<', $startDate->format('Y-m-d'))
            ->get();

        // Get all purchase payments before start date (exclude deleted)
        $paymentsBefore = PurchasePaymentLog::whereHas('purchase', function ($query) use ($supplier) {
                $query->where('supplier_id', $supplier->id);
            })
            ->where('created_at', '<', $startDate)
            ->get();

        // Standalone supplier payments before start date
        $supplierPaymentsBefore = (float) AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SUPPLIER_PAYMENT)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->where('account_ref_id', $supplier->id)
            ->where('transaction_date', '<', $startDate->format('Y-m-d'))
            ->sum('amount');

        $totalDue = $purchasesBefore->sum('due');
        $totalPaid = $paymentsBefore->sum('amount_paid');

        return max(0, $totalDue - $totalPaid - $supplierPaymentsBefore);
    }

    /**
     * Ensure the current user has access to the supplier based on shop.
     */
    protected function ensureShopAccess(Supplier $supplier): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            if ($supplier->shop_id && !$visibleShopIds->contains($supplier->shop_id)) {
                abort(403, 'You do not have access to this supplier.');
            }
        }
        // Super admin can access all suppliers
    }
}
