<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\OrderDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Gloudemans\Shoppingcart\Facades\Cart;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Models\PaymentLog;

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

        $search = request('search');
        $orders = Order::sortable()
            ->when($search, function ($query, $search) {
                return $query->where('invoice_no', 'like', '%' . $search . '%')
                             ->orWhereHas('customer', function($query) use ($search) {
                                 $query->where('name', 'like', '%' . $search . '%');
                             })
                             ->orWhere('order_date', 'like', '%' . $search . '%')
                             ->orWhere('pay', 'like', '%' . $search . '%')
                             ->orWhere('payment_status', 'like', '%' . $search . '%');
            })
            ->paginate($row);
        return view('orders.index', [
            'orders' => $orders
        ]);
    }

    public function pendingOrders()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $orders = Order::where('order_status', 'pending')->sortable()->paginate($row);

        return view('orders.pending-orders', [
            'orders' => $orders
        ]);
    }

    public function completeOrders()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $orders = Order::where('order_status', 'complete')->sortable()->paginate($row);

        return view('orders.complete-orders', [
            'orders' => $orders
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
    public function storeOrder(Request $request)
    {
        $rules = [
            'customer_id' => 'required|numeric',
            'payment_status' => 'required|string',
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
        $validatedData['order_date'] = Carbon::now()->format('Y-m-d');
        $validatedData['order_status'] = 'pending';
        $validatedData['total_products'] = Cart::count();
        $validatedData['sub_total'] = Cart::subtotal();
        $validatedData['vat'] = Cart::tax();
        $validatedData['invoice_no'] = $invoice_no;
        $validatedData['total'] = Cart::total();
        $validatedData['due'] = Cart::total() - $validatedData['pay'];
        $validatedData['created_at'] = Carbon::now();

        $order_id = Order::insertGetId($validatedData);

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

        return Redirect::route('dashboard')->with('success', 'Order has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function orderDetails(Int $order_id)
    {
        $order = Order::where('id', $order_id)->first();
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
        $order_id = $request->id;
        /*
        // Reduce the stock
        $products = OrderDetails::where('order_id', $order_id)->get();

        foreach ($products as $product) {
            Product::where('id', $product->product_id)
                    ->update(['product_store' => DB::raw('product_store-'.$product->quantity)]);
        }*/

        Order::findOrFail($order_id)->update(['order_status' => 'complete']);

        return Redirect::route('order.pendingOrders')->with('success', 'Order has been completed!');
    }

    public function invoiceDownload(Int $order_id)
    {
        $order = Order::where('id', $order_id)->first();
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

        $orders = Order::where('due', '>', '0')
            ->sortable()
            ->paginate($row);

        return view('orders.pending-due', [
            'orders' => $orders
        ]);
    }

    public function orderDueAjax(Int $id)
    {
        $order = Order::findOrFail($id);

        return response()->json($order);
    }

    public function updateDue(Request $request)
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
        $mainPay = $order->pay;
        $mainDue = $order->due;

        if ($validatedData['due'] > $mainDue) {
            return back()->withErrors(['due' => 'The amount you are trying to pay exceeds the outstanding due.'])
                     ->withInput();
        }

        $paid_due = $mainDue - $validatedData['due'];
        $paid_pay = $mainPay + $validatedData['due'];

        Order::findOrFail($request->order_id)->update([
            'due' => $paid_due,
            'pay' => $paid_pay,
        ]);

        PaymentLog::create([
            'order_id' => $order->id,
            'amount_paid' => $validatedData['due'],
        ]);

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
        $invoiceImagePath = $request->file('invoice_image')->store('invoices', 'public');
        $paymentLog->invoice_image = $invoiceImagePath;
        $paymentLog->save();

        return back()->with('success', 'Invoice uploaded successfully!');
    }

}
