<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\OrderDetails;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Gloudemans\Shoppingcart\Facades\Cart;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Models\PaymentLog;
use App\Support\ActiveShop;
use App\Services\CustomerCreditService;

class OrderController extends Controller
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

        $search = request('search');
        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->sortable()
            ->when($search, function ($query, $search) {
                return $query->where('invoice_no', 'like', '%' . $search . '%')
                             ->orWhereHas('customer', function($query) use ($search) {
                                 $query->where('name', 'like', '%' . $search . '%');
                             })
                             ->orWhere('order_date', 'like', '%' . $search . '%')
                             ->orWhere('pay', 'like', '%' . $search . '%')
                             ->orWhere('payment_status', 'like', '%' . $search . '%');
            });

        // Apply shop filtering
        if ($authUser->shop_id) {
            // Child shop or parent shop user - only see their allowed shops
            $ordersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            // Super admin - can see all orders (including unassigned)
            $ordersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('orders.index', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query())
        ]);
    }

    public function pendingOrders()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->where('order_status', 'pending')
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            $ordersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $ordersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('orders.pending-orders', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query())
        ]);
    }

    public function completeOrders()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->where('order_status', 'complete')
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            $ordersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $ordersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('orders.complete-orders', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query())
        ]);
    }

    public function stockManage()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        return view('stock.index', [
            'products' => Product::with(['category', 'supplier'])
                ->filter(request(['search']))
                ->sortable()
                ->paginate($row)
                ->appends(request()->query()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeOrder(Request $request, CustomerCreditService $creditService)
    {
        $rules = [
            'customer_id' => 'required|numeric',
            'payment_status' => 'required|string|in:HandCash,Cheque,Due,Bank',
            'pay' => 'numeric|nullable',
            'due' => 'numeric|nullable',
        ];

        $invoice_no = IdGenerator::generate([
            'table' => 'orders',
            'field' => 'invoice_no',
            'length' => 10,
            'prefix' => 'INV-'
        ]);

        $validatedData = $request->validate($rules);
        $payAmount = $validatedData['pay'] ?? 0;
        
        // Validate that customer belongs to the same shop as logged-in user
        $authUser = auth()->user();
        $customer = Customer::findOrFail($validatedData['customer_id']);
        
        if ($authUser->shop_id) {
            // For users with shop_id, customer must belong to the same shop
            if ($customer->shop_id !== $authUser->shop_id) {
                return back()->withErrors(['customer_id' => 'The selected customer does not belong to your shop.'])
                    ->withInput();
            }
        } else {
            // For SuperAdmin, customer should have a shop_id (not null)
            if (!$customer->shop_id) {
                return back()->withErrors(['customer_id' => 'The selected customer is not assigned to any shop.'])
                    ->withInput();
            }
        }
        
        $validatedData['order_date'] = Carbon::now()->format('Y-m-d');
        $validatedData['order_status'] = 'pending';
        $validatedData['total_products'] = Cart::count();
        $validatedData['sub_total'] = Cart::subtotal();
        $validatedData['vat'] = Cart::tax();
        $validatedData['invoice_no'] = $invoice_no;
        $validatedData['total'] = Cart::total();
        $validatedData['pay'] = $payAmount;
        $validatedData['due'] = Cart::total() - $payAmount;
        $validatedData['shop_id'] = $authUser->shop_id ?: $customer->shop_id;
        $validatedData['created_at'] = Carbon::now();

        $order_id = null;
        // Wrap creation + credit update in a transaction to keep balances consistent
        DB::transaction(function () use (&$order_id, $validatedData, $creditService, $customer) {
            $order_id = Order::insertGetId($validatedData);

            // Increase customer credit by pending amount (if any)
            $creditService->addPending($customer, $validatedData['due']);
        });

        // Create Order Details
        $contents = Cart::content();
        $oDetails = array();

        foreach ($contents as $content) {
            $oDetails['order_id'] = $order_id;
            $oDetails['product_id'] = $content->id;
            $oDetails['quantity'] = $content->qty;
            $oDetails['unitcost'] = $content->price;
            $oDetails['total'] = $content->total;
            $oDetails['created_at'] = Carbon::now();

            // Reduce the stock

            Product::where('id', $content->id)
                ->update(['product_store' => DB::raw('product_store-'.$content->qty)]);

            OrderDetails::insert($oDetails);
        }

        // Delete Cart Sopping History
        Cart::destroy();

        $warning = null;
        if ($creditService->exceedsLimit($customer, $validatedData['due'])) {
            $warning = 'Credit limit exceeded for this customer. Order saved on credit.';
        }

        return Redirect::route('dashboard')->with([
            'success' => 'Order has been created!',
            'warning' => $warning,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function orderDetails(Int $order_id)
    {
        $order = Order::with(['customer', 'shop.banks'])->findOrFail($order_id);
        $this->ensureShopAccess($order);
        
        $orderDetails = OrderDetails::with('product')
                        ->where('order_id', $order_id)
                        ->orderBy('id', 'DESC')
                        ->get();

        return view('orders.details-order', [
            'order' => $order,
            'orderDetails' => $orderDetails,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request)
    {
        $order = Order::findOrFail($request->id);
        $this->ensureShopAccess($order);
        /*
        // Reduce the stock
        $products = OrderDetails::where('order_id', $order_id)->get();

        foreach ($products as $product) {
            Product::where('id', $product->product_id)
                    ->update(['product_store' => DB::raw('product_store-'.$product->quantity)]);
        }*/

        $order->update(['order_status' => 'complete']);

        return Redirect::route('order.pendingOrders')->with('success', 'Order has been completed!');
    }

    public function invoiceDownload(Int $order_id)
    {
        $order = Order::with(['customer', 'shop.banks'])->findOrFail($order_id);
        $this->ensureShopAccess($order);
        
        $orderDetails = OrderDetails::with('product')
                        ->where('order_id', $order_id)
                        ->orderBy('id', 'DESC')
                        ->get();

        // show data (only for debugging)
        return view('orders.invoice-order', [
            'order' => $order,
            'orderDetails' => $orderDetails,
        ]);
    }

    public function pendingDue()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->where('due', '>', '0')
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            $ordersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $ordersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('orders.pending-due', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query())
        ]);
    }

    public function orderDueAjax(Int $id)
    {
        $order = Order::findOrFail($id);
        $this->ensureShopAccess($order);

        return response()->json($order);
    }

    public function updateDue(Request $request, CustomerCreditService $creditService)
    {
        $rules = [
            'order_id' => 'required|numeric',
            'due' => 'required|numeric|min:1',
        ];

        $customMessages = [
            'due.min' => 'The due amount cannot be 0 or less.',
        ];

        $validatedData = $request->validate($rules, $customMessages);

        $order = Order::findOrFail($request->order_id);
        $this->ensureShopAccess($order);
        $customer = $order->customer;
        
        $mainPay = $order->pay;
        $mainDue = $order->due;

        if ($validatedData['due'] > $mainDue) {
            return back()->withErrors(['due' => 'The amount you are trying to pay exceeds the outstanding due.'])
                     ->withInput();
        }

        $paid_due = $mainDue - $validatedData['due'];
        $paid_pay = $mainPay + $validatedData['due'];

        DB::transaction(function () use ($order, $paid_due, $paid_pay, $validatedData, $creditService, $customer) {
            $order->update([
                'due' => $paid_due,
                'pay' => $paid_pay,
            ]);

            PaymentLog::create([
                'order_id' => $order->id,
                'amount_paid' => $validatedData['due'],
            ]);

            // Decrease customer credit by the paid amount
            if ($customer) {
                $creditService->applyPayment($customer, $validatedData['due']);
            }
        });

        return Redirect::route('order.pendingDue')->with('success', 'Due Amount Updated Successfully!');
    }
    public function stockLog(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $stockLogs = StockLog::with(['product', 'supplier'])
        ->where('product_id', $id)
        ->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
        ->paginate(10);

        $stockLogs->getCollection()->transform(function ($stockLog) {
            $stockLog->created_at = $stockLog->created_at->format('Y-m-d');
            return $stockLog;
        });
            return view('products.stock-log', [
                'product' => $product,
                'stockLogs' => $stockLogs
            ]);
    }
    public function search(Request $request, $id)
    {
        $searchTerm = $request->get('search');

        $stockLogs = StockLog::with(['supplier', 'product'])
        ->where('product_id', $id)
        ->where(function ($query) use ($searchTerm) {
            $query->whereHas('supplier', function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%");
            })
            ->orWhereHas('product', function ($query) use ($searchTerm) {
                $query->where('product_name', 'like', "%{$searchTerm}%");
            })
            ->orWhere('stock_qty', 'like', "%{$searchTerm}%")
            ->orWhere('price', 'like', "%{$searchTerm}%");
        })
        ->get();

        $stockLogs->transform(function ($stockLog) {
            $stockLog->created_at = $stockLog->created_at->format('Y-m-d');
            return $stockLog;
        });

        if ($request->ajax()) {
            return response()->json(['stockLogs' => $stockLogs]);
        }

        return view('stocklogs.index', compact('stockLogs'));
    }

    public function paymentLog(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->ensureShopAccess($order);
        
        $paymentLogs = paymentLog::with(['order'])
        ->where('order_id', $id)
        ->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
        ->paginate(10);
        $paymentLogs->getCollection()->transform(function ($paymentLog) {
            $paymentLog->created_at = $paymentLog->created_at->format('Y-m-d');
            return $paymentLog;
        });

        return view('orders.payment-log', [
            'order' => $order,
            'paymentLogs' => $paymentLogs,
        ]);
    }
    public function paymentSearch(Request $request, $id)
    {
        $searchTerm = $request->get('search');

        $paymentLogs = PaymentLog::with(['order.customer'])
            ->where('order_id', $id)
            ->where(function ($query) use ($searchTerm) {
                $query->whereHas('order.customer', function ($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%");
                })
                ->orWhere('created_at', 'like', "%{$searchTerm}%")
                ->orWhere('amount_paid', 'like', "%{$searchTerm}%");
            })
            ->paginate(10);
        $paymentLogs->transform(function ($paymentLog) {
            $paymentLog->created_at = $paymentLog->created_at->format('Y-m-d');
            return $paymentLog;
        });

        if ($request->ajax()) {
            return response()->json(['paymentLogs' => $paymentLogs]);
        }

        return view('orders.payment-log', compact('paymentLogs'));
    }

      public function uploadInvoice(Request $request, $paymentLogId)
    {
        $request->validate([
            'invoice_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $paymentLog = PaymentLog::findOrFail($paymentLogId);
        if ($paymentLog->order) {
            $this->ensureShopAccess($paymentLog->order);
        }
        
        $invoiceImagePath = $request->file('invoice_image')->store('invoices', 'public');
        $paymentLog->invoice_image = $invoiceImagePath;
        $paymentLog->save();

        return back()->with('success', 'Invoice uploaded successfully!');
    }

    /**
     * Get order information for delete confirmation.
     */
    public function getOrderInfoForDelete(int $order_id)
    {
        $order = Order::with(['customer', 'orderDetails.product', 'paymentLogs'])
            ->findOrFail($order_id);
        $this->ensureShopAccess($order);

        $totalStockToReverse = $order->orderDetails->sum('quantity');
        $totalPayments = $order->paymentLogs->count();
        $totalPaymentAmount = $order->paymentLogs->sum('amount_paid');

        return response()->json([
            'order' => [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'customer_name' => $order->customer->name ?? 'N/A',
                'total' => $order->total,
                'due' => $order->due ?? 0,
                'pay' => $order->pay ?? 0,
                'order_date' => $order->order_date,
                'order_status' => $order->order_status,
            ],
            'total_products' => $order->orderDetails->count(),
            'total_stock_to_reverse' => $totalStockToReverse,
            'total_payments' => $totalPayments,
            'total_payment_amount' => $totalPaymentAmount,
        ]);
    }

    /**
     * Delete (soft delete) an order and reverse all related operations.
     */
    public function destroy(int $order_id)
    {
        $order = Order::with(['customer', 'orderDetails.product', 'paymentLogs'])
            ->findOrFail($order_id);
        $this->ensureShopAccess($order);

        $creditService = new CustomerCreditService();
        $customer = $order->customer;

        try {
            DB::transaction(function () use ($order, $customer, $creditService) {
                // 1. Reverse stock for all order details
                foreach ($order->orderDetails as $orderDetail) {
                    $product = $orderDetail->product;
                    
                    if (!$product) {
                        throw new \Exception("Product with ID {$orderDetail->product_id} not found. Cannot reverse stock.");
                    }

                    // Add back the stock quantity
                    Product::where('id', $orderDetail->product_id)
                        ->update(['product_store' => DB::raw('product_store + ' . $orderDetail->quantity)]);
                }

                // 2. Reverse customer credit for pending amount (due)
                if ($customer && $order->due > 0) {
                    $creditService->removePending($customer, $order->due);
                }

                // 3. Reverse customer credit for all payments made
                if ($customer) {
                    foreach ($order->paymentLogs as $paymentLog) {
                        $creditService->reversePayment($customer, $paymentLog->amount_paid);
                    }
                }

                // 4. Soft delete payment logs
                foreach ($order->paymentLogs as $paymentLog) {
                    $paymentLog->delete();
                }

                // 5. Soft delete order details
                foreach ($order->orderDetails as $orderDetail) {
                    $orderDetail->delete();
                }

                // 6. Soft delete order
                $order->delete();
            });

            return Redirect::route('order.index')->with('success', 'Order has been deleted successfully! Stock has been reversed and payments have been removed.');
        } catch (\Exception $e) {
            return Redirect::route('order.index')->with('error', 'Failed to delete order: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the current user has access to the order based on shop.
     */
    protected function ensureShopAccess(Order $order): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            // Child shop or parent shop user - must belong to allowed shops
            if ($order->shop_id && !$visibleShopIds->contains($order->shop_id)) {
                abort(403, 'You do not have access to this order.');
            }
        }
        // Super admin can access all orders
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function createInvoice()
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        // Filter customers by shop
        $customersQuery = Customer::query();
        if ($authUser->shop_id) {
            $customersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $customersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        // Filter products by shop for dropdown
        $productsQuery = Product::where(function($query) {
                $query->where('status', 'valid')
                      ->orWhere('status', 'active');
            });

        if ($authUser->shop_id) {
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $productsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('orders.create-invoice', [
            'customers' => $customersQuery->orderBy('shopname')->get(),
            'products' => $productsQuery->orderBy('product_name')->get(),
        ]);
    }

    /**
     * Search products for autocomplete (filtered by shop).
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', '');
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $productsQuery = Product::where(function($query) {
                $query->where('status', 'valid')
                      ->orWhere('status', 'active');
            })
            ->where('product_name', 'like', '%' . $search . '%');

        // Apply shop filtering
        if ($authUser->shop_id) {
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $productsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        $products = $productsQuery->limit(20)->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'text' => $product->product_name,
                'name' => $product->product_name,
                'price' => $product->selling_price ?? 0,
                'stock' => $product->product_store ?? 0,
                'code' => $product->product_code ?? '',
            ];
        });

        return response()->json(['results' => $products]);
    }

    /**
     * Store a newly created invoice.
     */
    public function storeInvoice(Request $request, CustomerCreditService $creditService)
    {
        $rules = [
            'customer_id' => 'required|numeric',
            'order_date' => 'required|date',
            'payment_status' => 'required|string|in:cash,bank,cheque,credit',
            'pay' => 'numeric|nullable|min:0',
            'vat' => 'numeric|nullable|min:0',
            'invoice_discount' => 'numeric|nullable|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|numeric',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.total' => 'required|numeric|min:0',
            'products.*.item_discount' => 'nullable|numeric|min:0',
        ];

        $validatedData = $request->validate($rules);
        $payAmount = $validatedData['pay'] ?? 0;

        // Validate that customer belongs to the same shop as logged-in user
        $authUser = auth()->user();
        $customer = Customer::findOrFail($validatedData['customer_id']);
        
        if ($authUser->shop_id) {
            // For users with shop_id, customer must belong to the same shop
            if ($customer->shop_id !== $authUser->shop_id) {
                return back()->withErrors(['customer_id' => 'The selected customer does not belong to your shop.'])
                    ->withInput();
            }
        } else {
            // For SuperAdmin, customer should have a shop_id (not null)
            if (!$customer->shop_id) {
                return back()->withErrors(['customer_id' => 'The selected customer is not assigned to any shop.'])
                    ->withInput();
            }
        }

        // Generate invoice number
        $invoice_no = IdGenerator::generate([
            'table' => 'orders',
            'field' => 'invoice_no',
            'length' => 10,
            'prefix' => 'INV-'
        ]);

        // Calculate totals
        $subtotal = 0;
        $totalProducts = 0;
        foreach ($validatedData['products'] as $product) {
            if (!empty($product['product_id'])) {
                $subtotal += $product['total'];
                $totalProducts++;
            }
        }

        $vat = $validatedData['vat'] ?? 0;
        $invoiceDiscount = $validatedData['invoice_discount'] ?? 0;
        $total = max(0, $subtotal + $vat - $invoiceDiscount);
        $pay = $payAmount;
        $due = max(0, $total - $pay);

        // Prepare order data
        $orderData = [
            'customer_id' => $validatedData['customer_id'],
            'shop_id' => $authUser->shop_id,
            'order_date' => Carbon::parse($validatedData['order_date'])->format('Y-m-d H:i:s'),
            'order_status' => 'pending',
            'total_products' => $totalProducts,
            'sub_total' => $subtotal,
            'invoice_discount' => $invoiceDiscount,
            'vat' => $vat,
            'invoice_no' => $invoice_no,
            'total' => $total,
            'payment_status' => $validatedData['payment_status'],
            'pay' => $pay,
            'due' => $due,
            'comment' => $request->input('comment'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $order_id = null;
        // Create order and adjust credit in one transaction
        DB::transaction(function () use (&$order_id, $orderData, $creditService, $customer, $validatedData, $due) {
            $order_id = Order::insertGetId($orderData);

            // Increase customer credit by pending amount (unpaid portion)
            $creditService->addPending($customer, $due);
        });

        // Create order details and reduce stock
        foreach ($validatedData['products'] as $product) {
            if (empty($product['product_id'])) {
                continue;
            }

            $productModel = Product::findOrFail($product['product_id']);
            
            // Validate stock
            if ($productModel->product_store < $product['quantity']) {
                // Rollback order creation
                Order::where('id', $order_id)->delete();
                return back()->withErrors(['products' => "Insufficient stock for product: {$productModel->product_name}. Available: {$productModel->product_store}"])
                    ->withInput();
            }

            // Validate shop access for product
            if ($authUser->shop_id && $productModel->shop_id !== $authUser->shop_id) {
                Order::where('id', $order_id)->delete();
                return back()->withErrors(['products' => "Product {$productModel->product_name} does not belong to your shop."])
                    ->withInput();
            }

            // Create order detail
            $orderDetailData = [
                'order_id' => $order_id,
                'product_id' => $product['product_id'],
                'quantity' => $product['quantity'],
                'unitcost' => $product['unit_price'],
                'item_discount' => $product['item_discount'] ?? 0,
                'total' => $product['total'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            OrderDetails::insert($orderDetailData);

            // Reduce stock
            Product::where('id', $product['product_id'])
                ->update(['product_store' => DB::raw('product_store - ' . $product['quantity'])]);
        }

        $warning = null;
        if ($creditService->exceedsLimit($customer, $due)) {
            $warning = 'Credit limit exceeded for this customer. Invoice saved on credit.';
        }

        return Redirect::route('order.index')->with([
            'success' => 'Invoice has been created successfully!',
            'warning' => $warning,
        ]);
    }

}
