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
            ->select('id', 'invoice_no', 'due', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = PaymentLog::with(['order:id,customer_id,invoice_no'])
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
