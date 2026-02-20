<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\OrderDetails;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Gloudemans\Shoppingcart\Facades\Cart;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Models\PaymentLog;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Support\ActiveShop;
use App\Services\CustomerCreditService;
use App\Services\Ledger\LedgerBalanceService;
use App\Services\Ledger\SaleLedgerService;
use App\Services\SalePaymentLedgerService;
use App\Services\Stock\StockService;
use App\Services\SupplierCreditService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderController extends Controller
{
    use ReportTrait;

    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Display a listing of the resource.
     * Filters: date (default All), invoice no (partial), total min/max, customer (Select2), search. Apply button.
     */
    public function index(Request $request)
    {
        $row = (int) $request->input('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        // Date filter: default "all" (no date filter)
        $dateFilter = $request->input('date_filter', 'all');
        if ($dateFilter !== 'all') {
            $dateRange = $this->getDateRange($request);
        } else {
            $dateRange = [
                'date_filter' => 'all',
                'start_date' => '',
                'end_date' => '',
                'start_datetime' => null,
                'end_datetime' => null,
            ];
        }

        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->sortable();

        // Date filter (when not "all")
        if ($dateFilter !== 'all' && isset($dateRange['start_datetime'], $dateRange['end_datetime'])) {
            $ordersQuery->whereBetween('order_date', [
                $dateRange['start_datetime']->format('Y-m-d'),
                $dateRange['end_datetime']->format('Y-m-d'),
            ]);
        }

        // Invoice no (partial match)
        if ($request->filled('invoice_no')) {
            $ordersQuery->where('invoice_no', 'like', '%' . $request->input('invoice_no') . '%');
        }

        // Invoice total range (min / max)
        if ($request->filled('total_min') && is_numeric($request->input('total_min'))) {
            $ordersQuery->where('total', '>=', (float) $request->input('total_min'));
        }
        if ($request->filled('total_max') && is_numeric($request->input('total_max'))) {
            $ordersQuery->where('total', '<=', (float) $request->input('total_max'));
        }

        // Customer (exact match when selected)
        if ($request->filled('customer_id')) {
            $ordersQuery->where('customer_id', $request->input('customer_id'));
        }

        // General search (existing behavior)
        $search = $request->input('search');
        if ($search !== null && $search !== '') {
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('invoice_no', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhere('order_date', 'like', '%' . $search . '%')
                    ->orWhere('pay', 'like', '%' . $search . '%')
                    ->orWhere('payment_status', 'like', '%' . $search . '%');
            });
        }

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

        if (!request()->has('sort')) {
            $ordersQuery->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        // Customers for dropdown (visible shops only)
        $customers = Customer::query()
            ->when($visibleShopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $visibleShopIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('orders.index', [
            'orders' => $ordersQuery->paginate($row)->withQueryString(),
            'dateRange' => $dateRange,
            'customers' => $customers,
        ]);
    }

    public function pendingOrders()
    {
        $row = (int) request('row', 50);

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

        // Apply default ordering by created_at DESC, id DESC if no sort is specified
        if (!request()->has('sort')) {
            $ordersQuery->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        return view('orders.pending-orders', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query())
        ]);
    }

    public function completeOrders()
    {
        $row = (int) request('row', 50);

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

        // Apply default ordering by created_at DESC, id DESC if no sort is specified
        if (!request()->has('sort')) {
            $ordersQuery->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        return view('orders.complete-orders', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query())
        ]);
    }

    public function stockManage()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $products = Product::with(['supplier'])
                ->filter(request(['search']))
                ->sortable()
                ->paginate($row)
                ->appends(request()->query());
        Product::eagerLoadSameShopCategory($products->getCollection());

        return view('stock.index', [
            'products' => $products,
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
        $validatedData['order_status'] = 'complete';
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
            // Use create() instead of insertGetId() to properly handle SoftDeletes
            $order = Order::create($validatedData);
            $order_id = $order->id;

            app(SaleLedgerService::class)->recordInvoiceCustomerDebit($order);

            // Increase customer credit by pending amount (if any)
            $creditService->addPending($customer, $validatedData['due']);
        });

        // Create Order Details
        $contents = Cart::content();
        $oDetails = array();

        foreach ($contents as $content) {
            $product = Product::find($content->id);
            $oDetails['order_id'] = $order_id;
            $oDetails['product_id'] = $content->id;
            $oDetails['quantity'] = $content->qty;
            $oDetails['unitcost'] = $content->price;
            $oDetails['cost_per_unit'] = $product ? (float) ($product->buying_price ?? 0) : 0;
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

        $paymentBankName = $this->getPaymentBankNameForOrder($order);
        $salePayments = $this->getSalePaymentsFromAccountTransactions($order);

        $orderDetails = OrderDetails::with('product')
                        ->where('order_id', $order_id)
                        ->orderBy('id', 'DESC')
                        ->get();

        return view('orders.view-invoice', [
            'order' => $order,
            'orderDetails' => $orderDetails,
            'paymentBankName' => $paymentBankName,
            'salePayments' => $salePayments,
        ]);
    }

    /**
     * Return invoice detail content as HTML for modal (e.g. customer ledger).
     * Uses the same partial as order details page so changes are in one place.
     */
    public function orderDetailsContent(Int $order_id)
    {
        $order = Order::with(['customer', 'shop.banks'])->findOrFail($order_id);
        $this->ensureShopAccess($order);

        $paymentBankName = $this->getPaymentBankNameForOrder($order);
        $salePayments = $this->getSalePaymentsFromAccountTransactions($order);

        $orderDetails = OrderDetails::with('product')
            ->where('order_id', $order_id)
            ->orderBy('id', 'DESC')
            ->get();

        $html = view('orders.partials.invoice-detail-content', [
            'order' => $order,
            'orderDetails' => $orderDetails,
            'paymentBankName' => $paymentBankName,
            'salePayments' => $salePayments,
            'in_modal' => true,
        ])->render();

        return response()->json(['html' => $html]);
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

        // Check if request came from order details page
        $referer = $request->headers->get('referer');
        if ($referer && strpos($referer, '/orders/details/') !== false) {
            return Redirect::route('order.orderDetails', $order->id)->with('success', 'Order has been completed!');
        }
        
        // Check if request came from pending due page
        if ($referer && strpos($referer, '/pending/due') !== false) {
            return Redirect::route('order.pendingDue')->with('success', 'Order has been completed!');
        }

        return Redirect::route('order.pendingOrders')->with('success', 'Order has been completed!');
    }

    public function invoiceDownload(Int $order_id)
    {
        $order = Order::with(['customer', 'shop.banks'])->findOrFail($order_id);
        $this->ensureShopAccess($order);

        $paymentBankName = $this->getPaymentBankNameForOrder($order);
        $salePayments = $this->getSalePaymentsFromAccountTransactions($order);

        $customerBalance = null;
        if ($order->customer_id && $order->shop_id) {
            $customerBalance = app(LedgerBalanceService::class)->getCustomerBalance(
                (int) $order->customer_id,
                $order->shop_id
            );
        }

        $orderDetails = OrderDetails::with('product')
                        ->where('order_id', $order_id)
                        ->orderBy('id', 'DESC')
                        ->get();

        // Clear print session flags if they exist
        $shouldPrint = request('print') == '1' || session('print_order_id') == $order_id;
        if (session('print_order_id') == $order_id) {
            session()->forget(['print_order_id', 'open_print_tab']);
        }

        return view('orders.invoice-order', [
            'order' => $order,
            'orderDetails' => $orderDetails,
            'shouldPrint' => $shouldPrint,
            'paymentBankName' => $paymentBankName,
            'salePayments' => $salePayments,
            'customerBalance' => $customerBalance,
        ]);
    }

    /**
     * Get sale payment entries from account_transactions (bank/cash debits for this order).
     * Returns collection of { account_type, amount, bank_name }.
     */
    private function getSalePaymentsFromAccountTransactions(Order $order): \Illuminate\Support\Collection
    {
        $paymentLogIds = PaymentLog::where('order_id', $order->id)->pluck('id');
        if ($paymentLogIds->isEmpty()) {
            return collect();
        }

        $transactions = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->whereIn('source_id', $paymentLogIds->all())
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_BANK, AccountTransaction::ACCOUNT_TYPE_CASH])
            ->where('direction', AccountTransaction::DIRECTION_DEBIT)
            ->orderBy('id')
            ->get(['id', 'account_type', 'account_ref_id', 'amount']);

        $bankShopIds = $transactions
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_BANK)
            ->pluck('account_ref_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $bankNames = collect();
        if (!empty($bankShopIds)) {
            $bankNames = DB::table('bank_shop')
                ->whereIn('bank_shop.id', $bankShopIds)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->pluck('banks.name', 'bank_shop.id');
        }

        return $transactions->map(function ($t) use ($bankNames) {
            return (object) [
                'account_type' => $t->account_type,
                'amount' => (float) $t->amount,
                'bank_name' => $t->account_type === AccountTransaction::ACCOUNT_TYPE_BANK
                    ? ($bankNames->get($t->account_ref_id) ?? 'Bank')
                    : 'Cash',
            ];
        });
    }

    /**
     * Get the bank name used for this order's payment (from payment_logs.shop_bank_id).
     * Returns null if payment method is not bank/cheque or no bank was recorded.
     */
    private function getPaymentBankNameForOrder(Order $order): ?string
    {
        if (!in_array(strtolower($order->payment_status ?? ''), ['bank', 'cheque'])) {
            return null;
        }

        $log = PaymentLog::where('order_id', $order->id)
            ->whereNotNull('shop_bank_id')
            ->first();

        if (!$log || !$log->shop_bank_id) {
            return null;
        }

        return DB::table('bank_shop')
            ->where('bank_shop.id', $log->shop_bank_id)
            ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
            ->value('banks.name');
    }

    public function pendingDue()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $ordersQuery = Order::with(['customer', 'shop.parent'])
            ->where('order_status', '!=', 'complete')
            ->where(function($query) {
                $query->where('due', '>', '0')  // Unpaid or partially paid
                      ->orWhere('due', '=', '0'); // Fully paid but not completed
            })
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

        // Apply default ordering by created_at DESC, id DESC if no sort is specified
        if (!request()->has('sort')) {
            $ordersQuery->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        $shopBanks = $this->getShopBanksByShopId($authUser->shop_id);

        return view('orders.pending-due', [
            'orders' => $ordersQuery->paginate($row)->appends(request()->query()),
            'shopBanks' => $shopBanks,
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
            'payment_method' => 'required|string|in:cash,bank,cheque',
            'due' => 'required|numeric|min:1',
            'shop_bank_id' => 'nullable|numeric|exists:bank_shop,id',
        ];

        $customMessages = [
            'due.min' => 'The due amount cannot be 0 or less.',
        ];

        $validatedData = $request->validate($rules, $customMessages);

        $authUser = auth()->user();
        if (in_array($validatedData['payment_method'], ['bank', 'cheque'])) {
            if (empty($validatedData['shop_bank_id'])) {
                return back()->withErrors(['shop_bank_id' => 'Please select a bank for this payment method.'])
                    ->withInput();
            }
            $belongsToShop = DB::table('bank_shop')
                ->where('id', $validatedData['shop_bank_id'])
                ->where('shop_id', $authUser->shop_id)
                ->exists();
            if (!$belongsToShop) {
                return back()->withErrors(['shop_bank_id' => 'The selected bank is not valid for your shop.'])
                    ->withInput();
            }
        }

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
        $shopBankId = isset($validatedData['shop_bank_id']) && $validatedData['shop_bank_id'] ? $validatedData['shop_bank_id'] : null;

        DB::transaction(function () use ($order, $paid_due, $paid_pay, $validatedData, $creditService, $customer, $shopBankId) {
            $order->update([
                'due' => $paid_due,
                'pay' => $paid_pay,
            ]);

            $paymentLogData = [
                'order_id' => $order->id,
                'amount_paid' => $validatedData['due'],
                'type' => 'payment',
                'payment_method' => $validatedData['payment_method'],
            ];
            if ($shopBankId !== null) {
                $paymentLogData['shop_bank_id'] = $shopBankId;
            }
            $paymentLog = PaymentLog::create($paymentLogData);
            app(SalePaymentLedgerService::class)->createFromPaymentLog($paymentLog->id);

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
                'customer_name' => $order->customer?->name ?? $order->customer?->shopname ?? 'N/A',
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
     * Delete (soft delete) an order and reverse stock. No hard deletes; no reversal ledger entries.
     * Related records (order_details, account_transactions, payment_logs, stock_logs) are soft deleted.
     * Ledger balance auto-adjusts (excluded from sums). customers.credit_amount is synced from ledger.
     */
    public function destroy(int $order_id, LedgerBalanceService $balanceService)
    {
        $order = Order::with(['customer', 'orderDetails.product', 'paymentLogs'])
            ->findOrFail($order_id);
        $this->ensureShopAccess($order);

        try {
            DB::transaction(function () use ($order, $balanceService) {
                // 1. Lock invoice (with relations for stock reversal and payment log ids)
                $order = Order::with(['orderDetails.product', 'paymentLogs'])
                    ->lockForUpdate()
                    ->findOrFail($order->id);
                if ($order->trashed()) {
                    throw new \RuntimeException('This invoice has already been deleted.');
                }

                // 1b. If this is a mother sale with a linked child purchase, delete the child side first (inter-branch cascade).
                $childPurchase = Purchase::with(['purchaseDetails.product', 'paymentLogs'])
                    ->where('source_sale_id', $order->id)
                    ->first();

                if ($childPurchase && !$childPurchase->trashed()) {
                    $childPurchase = Purchase::with(['purchaseDetails.product', 'paymentLogs'])
                        ->lockForUpdate()
                        ->find($childPurchase->id);
                    if ($childPurchase && !$childPurchase->trashed()) {
                        // 2A: Reverse child stock (decrement product_store for purchased quantity).
                        // Use explicit query for purchase_details so we always have rows; update products directly to avoid any shop scope.
                        $childDetails = PurchaseDetail::where('purchase_id', $childPurchase->id)->get();
                        foreach ($childDetails as $purchaseDetail) {
                            if ((int) $purchaseDetail->quantity <= 0) {
                                continue;
                            }
                            $productId = (int) $purchaseDetail->product_id;
                            $qty = (int) $purchaseDetail->quantity;
                            $current = (int) DB::table('products')->where('id', $productId)->value('product_store');
                            if ($current < $qty) {
                                $productName = DB::table('products')->where('id', $productId)->value('product_name');
                                throw new \RuntimeException(
                                    'Cannot delete: linked child purchase would make product "' . ($productName ?? $productId) . '" negative.'
                                );
                            }
                            DB::table('products')->where('id', $productId)->decrement('product_store', $qty);
                        }
                        // 2B: Soft delete child account_transactions (purchase + purchase_payment).
                        $childPaymentLogIds = $childPurchase->paymentLogs()->pluck('id')->toArray();
                        AccountTransaction::query()
                            ->where(function ($q) use ($childPurchase, $childPaymentLogIds) {
                                $q->where('source_type', AccountTransaction::SOURCE_PURCHASE)
                                    ->where('source_id', $childPurchase->id);
                                if (count($childPaymentLogIds) > 0) {
                                    $q->orWhere(function ($q2) use ($childPaymentLogIds) {
                                        $q2->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
                                            ->whereIn('source_id', $childPaymentLogIds);
                                    });
                                }
                                // System-generated child purchase uses source_id = purchase_id for payment entry
                                $q->orWhere(function ($q2) use ($childPurchase) {
                                    $q2->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
                                        ->where('source_id', $childPurchase->id);
                                });
                            })
                            ->delete();
                        // 2C: Soft delete child purchase_details, payment_logs, stock_logs, then purchase.
                        PurchaseDetail::where('purchase_id', $childPurchase->id)->delete();
                        PurchasePaymentLog::where('purchase_id', $childPurchase->id)->delete();
                        StockLog::query()
                            ->where('source_type', 'purchase')
                            ->where('source_id', (string) $childPurchase->id)
                            ->delete();
                        $childPurchase->delete();
                    }
                }

                // 2. Reverse stock: increase product_store by sold quantity (sale invoice)
                foreach ($order->orderDetails as $orderDetail) {
                    $product = Product::withoutGlobalScope('shop')
                        ->where('id', $orderDetail->product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product && $orderDetail->quantity > 0) {
                        $product->increment('product_store', $orderDetail->quantity);
                    }
                }

                // 3. Soft delete related: order_details
                OrderDetails::where('order_id', $order->id)->delete();

                // 4. Soft delete account_transactions related to this invoice (sale + payment entries)
                $paymentLogIds = $order->paymentLogs()->pluck('id')->toArray();
                AccountTransaction::query()
                    ->where('source_type', AccountTransaction::SOURCE_SALE)
                    ->where(function ($q) use ($order, $paymentLogIds) {
                        $q->where('source_id', $order->id);
                        if (count($paymentLogIds) > 0) {
                            $q->orWhereIn('source_id', $paymentLogIds);
                        }
                    })
                    ->delete();

                // 5. Soft delete payment_logs
                PaymentLog::where('order_id', $order->id)->delete();

                // 6. Soft delete stock_logs for this sale
                StockLog::query()
                    ->where('source_type', 'sale')
                    ->where('source_id', (string) $order->id)
                    ->delete();

                // 7. Soft delete order
                $order->delete();

                // 8. Sync customer balance in customers table (ledger already excludes soft-deleted transactions)
                if ($order->customer_id) {
                    $balance = $balanceService->getCustomerBalance((int) $order->customer_id, $order->shop_id);
                    Customer::where('id', $order->customer_id)->update(['credit_amount' => $balance]);
                }
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

        // Filter customers by shop, exclude system customers (is_system = 1)
        // For invoice/create page only: show customers from user's shop_id only (exclude child shops)
        $customersQuery = Customer::query()
            ->where(function($query) {
                $query->where('is_system', false)
                      ->orWhere('is_system', 0)
                      ->orWhereNull('is_system');
            });
        if ($authUser->shop_id) {
            // Only show customers from the user's specific shop_id, not child shops
            $customersQuery->where('shop_id', $authUser->shop_id);
        } else {
            // SuperAdmin: show no customers (or show all if needed - adjust as per requirement)
            $customersQuery->whereRaw('1 = 0'); // Always false condition - no customers for SuperAdmin
        }

        // Filter products by shop for dropdown - only user's specific shop_id (exclude child shops)
        // For invoice/create page only: show products from user's shop_id only
        $targetShopId = $authUser->shop_id;
        
        // Only show products with status='active' and selling_price IS NOT NULL
        $productsQuery = Product::where('status', 'active')
            ->whereNotNull('selling_price')
            ->where('selling_price', '>', 0);

        if ($targetShopId) {
            // Only show products from the user's specific shop_id, not child shops
            $productsQuery->where('shop_id', $targetShopId);
        } else {
            // If no shop_id available, show no products
            $productsQuery->whereRaw('1 = 0'); // Always false condition
        }

        // Get child shops if user belongs to a parent shop
        $childShops = collect();
        if ($authUser->shop_id) {
            $userShop = Shop::find($authUser->shop_id);
            if ($userShop && $userShop->is_parent) {
                $childShops = Shop::where('parent_shop_id', $userShop->id)
                    ->where('status', true)
                    ->orderBy('name')
                    ->get();
            }
        }

        // Shop banks for payment method bank/cheque (auth user's shop_id only)
        $shopBanks = $this->getShopBanksByShopId($authUser->shop_id);

        return view('orders.create-invoice', [
            'customers' => $customersQuery->orderBy('shopname')->get(),
            'products' => $productsQuery->orderBy('product_name')->get(),
            'childShops' => $childShops,
            'categories' => Category::orderBy('name')->get(),
            'shopBanks' => $shopBanks,
        ]);
    }

    /**
     * Get bank_shop options (id, bank name) for a shop. Uses auth user's shop_id only.
     *
     * @param int|null $shopId
     * @return \Illuminate\Support\Collection
     */
    private function getShopBanksByShopId($shopId)
    {
        if (!$shopId) {
            return collect();
        }
        return DB::table('bank_shop')
            ->where('bank_shop.shop_id', $shopId)
            ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
            ->select('bank_shop.id', 'banks.name')
            ->orderBy('banks.name')
            ->get();
    }

    /**
     * Get customer details via AJAX for invoice creation.
     */
    public function getCustomerDetails(Request $request, $customerId)
    {
        $authUser = auth()->user();
        
        $customer = Customer::findOrFail($customerId);
        
        // Ensure shop access
        if ($authUser->shop_id && $customer->shop_id !== $authUser->shop_id) {
            return response()->json(['error' => 'You do not have access to this customer.'], 403);
        }
        
        // Get last payment date
        $lastPayment = PaymentLog::whereHas('order', function($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->orderBy('created_at', 'desc')
            ->first();
        
        $lastPaymentDate = $lastPayment ? $lastPayment->created_at->format('Y-m-d H:i:s') : null;
        
        // Calculate available credit
        $creditLimit = $customer->credit_limit ?? 0;
        $creditAmount = $customer->credit_amount ?? 0;
        $availableCredit = max(0, $creditLimit - $creditAmount);
        
        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name ?? '',
                'shopname' => $customer->shopname ?? '',
                'phone' => $customer->phone ?? '',
                'address' => $customer->address ?? '',
                'email' => $customer->email ?? '',
                'credit_limit' => $creditLimit,
                'credit_amount' => $creditAmount,
                'credit_days' => $customer->credit_days ?? 0,
                'available_credit' => $availableCredit,
                'last_payment_date' => $lastPaymentDate,
                'is_walkin' => $customer->is_walkin ?? 0,
            ]
        ]);
    }

    /**
     * Search products for autocomplete (filtered by shop).
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $authUser = auth()->user();
        
        // For invoice/create page only: show products from user's shop_id only (exclude child shops)
        $targetShopId = $authUser->shop_id;

        // Build base query with status and shop filtering (no selling_price restriction; null → 0 as unit price)
        $productsQuery = Product::where('status', 'active');
        
        // Apply shop filtering - only user's specific shop_id (exclude child shops)
        if ($targetShopId) {
            // Only show products from the user's specific shop_id, not child shops
            $productsQuery->where('shop_id', $targetShopId);
        } else {
            // If no shop_id available, show no products
            $productsQuery->whereRaw('1 = 0'); // Always false condition
        }
        
        // Apply search filter (only if search term is provided)
        if (!empty($search)) {
            $productsQuery->where(function($query) use ($search) {
                $query->where('product_name', 'like', '%' . $search . '%')
                      ->orWhere('product_code', 'like', '%' . $search . '%');
            });
        }

        // Get total count for pagination (clone query to avoid affecting the main query)
        $totalCount = (clone $productsQuery)->count();
        
        // Apply pagination (50 items per page)
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $products = $productsQuery->orderBy('product_code', 'asc')
            ->orderBy('product_name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($product) {
                $productCode = $product->product_code ?? '';
                $displayText = $productCode ? $productCode . ' - ' . $product->product_name : $product->product_name;
                // When selling_price is null, populate 0 as unit price for selection
                $unitPrice = $product->selling_price !== null && $product->selling_price !== '' ? (float) $product->selling_price : 0;
                return [
                    'id' => $product->id,
                    'text' => $displayText,
                    'name' => $product->product_name,
                    'price' => $unitPrice,
                    'stock' => $product->product_store ?? 0,
                    'code' => $productCode,
                ];
            });

        return response()->json([
            'results' => $products,
            'pagination' => [
                'more' => ($page * $perPage) < $totalCount
            ]
        ]);
    }

    /**
     * Get all categories for dropdown (API endpoint).
    */
    public function getCategories()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        
        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Create payment_log row and corresponding ledger entry (one-to-one).
     * Must be called inside a DB transaction. If ledger creation fails, transaction rolls back.
     *
     * @param int $orderId
     * @param float $amountPaid
     * @param string $paymentMethod
     * @param int|null $shopBankId
     * @return void
     */
    private function createPaymentLog($orderId, $amountPaid, $paymentMethod, $shopBankId = null): void
    {
        if ($amountPaid <= 0 || $amountPaid === null) {
            return;
        }

        $data = [
            'order_id' => $orderId,
            'amount_paid' => $amountPaid,
            'type' => 'payment',
            'payment_method' => $paymentMethod,
        ];
        if ($shopBankId !== null) {
            $data['shop_bank_id'] = $shopBankId;
        }

        $paymentLog = PaymentLog::create($data);
        app(SalePaymentLedgerService::class)->createFromPaymentLog($paymentLog->id);
    }

    /**
     * Store a newly created invoice.
     */
    public function storeInvoice(Request $request, CustomerCreditService $creditService, SupplierCreditService $supplierCreditService)
    {
        // Log the request for debugging
        \Log::info('Invoice creation request received', [
            'customer_id' => $request->input('customer_id'),
            'shop_id' => $request->input('shop_id'),
            'products_count' => count($request->input('products', [])),
            'payment_method_1' => $request->input('payment_method_1'),
        ]);

        $authUser = auth()->user();

        $rules = [
            'customer_id' => 'required_without:shop_id|nullable|numeric',
            'shop_id' => 'required_without:customer_id|nullable|numeric|exists:shops,id',
            'order_date' => 'required|date',
            'payment_method_1' => 'required|string|in:cash,bank,cheque,credit',
            'pay_1' => 'required|numeric|min:0',
            'shop_bank_id_1' => 'nullable|numeric|exists:bank_shop,id',
            'payment_method_2' => 'nullable|string|in:cash,bank,cheque,credit',
            'pay_2' => 'nullable|numeric|min:0',
            'shop_bank_id_2' => 'nullable|numeric|exists:bank_shop,id',
            'pay' => 'nullable|numeric|min:0',
            'due' => 'nullable|numeric|min:0',
            'vat' => 'numeric|nullable|min:0',
            'invoice_discount' => 'numeric|nullable|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|numeric',
            'products.*.quantity' => 'required|numeric|min:1',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.total' => 'required|numeric|min:0',
            'products.*.item_discount' => 'nullable|numeric|min:0',
        ];

        try {
            $validatedData = $request->validate($rules);

            \Log::info('Invoice validation passed', ['products_count' => count($validatedData['products'])]);

            // Filter out empty product entries (where product_id is empty or 0)
            $validatedData['products'] = array_filter($validatedData['products'], function ($product) {
                return !empty($product['product_id']) && $product['product_id'] > 0;
            });

            // Re-index array after filtering
            $validatedData['products'] = array_values($validatedData['products']);

            // Validate that at least one product remains after filtering
            if (empty($validatedData['products'])) {
                return back()->withErrors(['products' => 'Please add at least one product to the invoice.'])
                    ->withInput();
            }

            // Payment 1: bank/cheque requires shop_bank_id_1 and must belong to user's shop
            if (in_array($validatedData['payment_method_1'], ['bank', 'cheque'])) {
                if (empty($validatedData['shop_bank_id_1'])) {
                    return back()->withErrors(['shop_bank_id_1' => 'Please select a bank for Payment 1.'])
                        ->withInput();
                }
                if (!DB::table('bank_shop')->where('id', $validatedData['shop_bank_id_1'])->where('shop_id', $authUser->shop_id)->exists()) {
                    return back()->withErrors(['shop_bank_id_1' => 'The selected bank is not valid for your shop.'])
                        ->withInput();
                }
            }

            // Payment 2: when pay_2 > 0 and method is bank/cheque, require shop_bank_id_2
            $pay2 = (float) ($validatedData['pay_2'] ?? 0);
            if ($pay2 > 0 && in_array($validatedData['payment_method_2'] ?? '', ['bank', 'cheque'])) {
                if (empty($validatedData['shop_bank_id_2'])) {
                    return back()->withErrors(['shop_bank_id_2' => 'Please select a bank for Payment 2.'])
                        ->withInput();
                }
                if (!DB::table('bank_shop')->where('id', $validatedData['shop_bank_id_2'])->where('shop_id', $authUser->shop_id)->exists()) {
                    return back()->withErrors(['shop_bank_id_2' => 'The selected bank is not valid for your shop.'])
                        ->withInput();
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $pay1 = (float) ($validatedData['pay_1'] ?? 0);
        $pay2 = (float) ($validatedData['pay_2'] ?? 0);
        $payAmount = $pay1 + $pay2;
        $paymentMethod1 = $validatedData['payment_method_1'];
        $paymentMethod2 = $validatedData['payment_method_2'] ?? null;
        $shopBankId1 = !empty($validatedData['shop_bank_id_1']) ? (int) $validatedData['shop_bank_id_1'] : null;
        $shopBankId2 = !empty($validatedData['shop_bank_id_2']) ? (int) $validatedData['shop_bank_id_2'] : null;

        // Route logic: Customer flow OR Shop transfer flow
        if (isset($validatedData['customer_id']) && $validatedData['customer_id']) {
            // ========== EXISTING CUSTOMER FLOW ==========
            $customer = Customer::findOrFail($validatedData['customer_id']);
            
            if ($authUser->shop_id) {
                if ($customer->shop_id !== $authUser->shop_id) {
                    return back()->withErrors(['customer_id' => 'The selected customer does not belong to your shop.'])
                        ->withInput();
                }
            } else {
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

            // When both payments are cash/bank/cheque (no credit), sum must equal invoice total
            $method1NonCredit = in_array($paymentMethod1, ['cash', 'bank', 'cheque']);
            $method2NonCredit = $paymentMethod2 && in_array($paymentMethod2, ['cash', 'bank', 'cheque']);
            if ($method1NonCredit && $method2NonCredit && $pay2 > 0 && abs($pay - $total) > 0.01) {
                return back()->withErrors(['pay_1' => 'When paying by Cash, Bank or Cheque only, the total of both amounts must equal the invoice total.'])
                    ->withInput();
            }
            if ($method1NonCredit && $pay2 <= 0 && abs($pay1 - $total) > 0.01) {
                return back()->withErrors(['pay_1' => 'Payment amount must equal the invoice total when using Cash, Bank or Cheque only.'])
                    ->withInput();
            }

            // Sales invoices are always complete (no pending)
            $orderStatus = 'complete';

            $orderData = [
                'customer_id' => $validatedData['customer_id'],
                'shop_id' => $authUser->shop_id,
                'order_date' => Carbon::parse($validatedData['order_date'])->format('Y-m-d H:i:s'),
                'order_status' => $orderStatus,
                'total_products' => $totalProducts,
                'sub_total' => $subtotal,
                'invoice_discount' => $invoiceDiscount,
                'vat' => $vat,
                'invoice_no' => $invoice_no,
                'total' => $total,
                'payment_status' => $paymentMethod1,
                'pay' => $pay,
                'due' => $due,
                'comment' => $request->input('comment'),
            ];

            $order_id = null;
            try {
                DB::transaction(function () use (&$order_id, $orderData, $validatedData, $creditService, $customer, $due, $pay1, $pay2, $paymentMethod1, $paymentMethod2, $shopBankId1, $shopBankId2, $authUser) {
                    $order = Order::create($orderData);
                    $order_id = $order->id;

                    app(SaleLedgerService::class)->recordInvoiceCustomerDebit($order);

                    if ($due > 0) {
                        $creditService->addPending($customer, $due);
                    }

                    if ($pay1 > 0) {
                        $this->createPaymentLog($order_id, $pay1, $paymentMethod1, $shopBankId1);
                    }
                    if ($pay2 > 0 && $paymentMethod2) {
                        $this->createPaymentLog($order_id, $pay2, $paymentMethod2, $shopBankId2);
                    }

                    // Order details and stock (ledger-safe: StockService only)
                    foreach ($validatedData['products'] as $product) {
                        if (empty($product['product_id'])) {
                            continue;
                        }

                        $productModel = Product::findOrFail($product['product_id']);

                        // Commented out for now: require active status and valid selling_price
                        // if ($productModel->status !== 'active' || empty($productModel->selling_price) || $productModel->selling_price <= 0) {
                        //     throw new \Exception("Product {$productModel->product_name} is not available for sale.");
                        // }

                        if ($authUser->shop_id && $productModel->shop_id !== $authUser->shop_id) {
                            throw new \Exception("Product {$productModel->product_name} does not belong to your shop.");
                        }

                        $orderDetailData = [
                            'order_id' => $order_id,
                            'product_id' => $product['product_id'],
                            'quantity' => $product['quantity'],
                            'unitcost' => $product['unit_price'],
                            'cost_per_unit' => (float) ($productModel->buying_price ?? 0),
                            'item_discount' => $product['item_discount'] ?? 0,
                            'total' => $product['total'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                        OrderDetails::insert($orderDetailData);

                        $this->stockService->sellStock(
                            $productModel,
                            (int) $product['quantity'],
                            (float) ($product['unit_price'] ?? $productModel->selling_price ?? 0),
                            $order_id,
                            (float) ($productModel->buying_price ?? 0)
                        );
                    }
                });
            } catch (InvalidArgumentException $e) {
                return back()->withErrors(['products' => $e->getMessage()])->withInput();
            } catch (\Exception $e) {
                return back()->withErrors(['products' => $e->getMessage()])->withInput();
            }

            $warning = null;
            if ($creditService->exceedsLimit($customer, $due)) {
                $warning = 'Credit limit exceeded for this customer. Invoice saved on credit.';
            }

            // Check if print was requested
            if ($request->input('print_after_create') == '1') {
                // Store order_id in session for opening print tab
                session(['print_order_id' => $order_id]);
                
                return Redirect::route('order.index')->with([
                    'success' => 'Invoice has been created successfully!',
                    'warning' => $warning,
                    'open_print_tab' => true, // Flag to open print tab
                ]);
            }

            return Redirect::route('order.index')->with([
                'success' => 'Invoice has been created successfully!',
                'warning' => $warning,
            ]);

        } else if (isset($validatedData['shop_id']) && $validatedData['shop_id']) {
            // ========== SHOP TRANSFER FLOW ==========
            // Validate user belongs to parent shop
            if (!$authUser->shop_id) {
                return back()->withErrors(['shop_id' => 'You must belong to a parent shop to transfer stock.'])
                    ->withInput();
            }

            $motherShop = Shop::findOrFail($authUser->shop_id);
            if (!$motherShop->is_parent) {
                return back()->withErrors(['shop_id' => 'You must belong to a parent shop to transfer stock.'])
                    ->withInput();
            }

            // Validate child shop
            $childShop = Shop::findOrFail($validatedData['shop_id']);
            if ($childShop->parent_shop_id !== $motherShop->id) {
                return back()->withErrors(['shop_id' => 'The selected shop is not a child of your shop.'])
                    ->withInput();
            }

            // Ensure mother shop exists as supplier for child shop (query child shop, so bypass shop scope)
            $supplier = Supplier::withoutGlobalScope('shop')
                ->where('shop_id', $childShop->id)
                ->where('mother_shop_id', $motherShop->id)
                ->first();

            if (!$supplier) {
                // Create supplier for child shop (mother shop as supplier)
                // Use child shop ID to ensure phone uniqueness
                $supplierPhone = $motherShop->phone . '-SUP-' . $childShop->id;
                $supplier = Supplier::create([
                    'shop_id' => $childShop->id,
                    'mother_shop_id' => $motherShop->id,
                    'shopname' => $motherShop->name,
                    'name' => $motherShop->name,
                    'phone' => $supplierPhone,
                    'email' => null,
                    'address' => $motherShop->address,
                    'type' => 'Internal',
                ]);
            }

            // Get or create system customer representing child shop (linked by child_shop_id); customer belongs to mother shop so it appears in mother shop's lists (global scope)
            $systemCustomer = Customer::withoutGlobalScope('shop')
                ->where('shop_id', $motherShop->id)
                ->where('child_shop_id', $childShop->id)
                ->where('is_system', true)
                ->first();

            if (!$systemCustomer) {
                $systemCustomer = Customer::create([
                    'shop_id' => $motherShop->id,
                    'child_shop_id' => $childShop->id,
                    'shopname' => $childShop->name,
                    'name' => $childShop->name,
                    'phone' => $childShop->phone . '-SYS-' . $childShop->id,
                    'email' => null,
                    'is_system' => true,
                    'address' => $childShop->address,
                ]);
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

            $method1NonCredit = in_array($paymentMethod1, ['cash', 'bank', 'cheque']);
            $method2NonCredit = $paymentMethod2 && in_array($paymentMethod2, ['cash', 'bank', 'cheque']);
            if ($method1NonCredit && $method2NonCredit && $pay2 > 0 && abs($pay - $total) > 0.01) {
                return back()->withErrors(['pay_1' => 'When paying by Cash, Bank or Cheque only, the total of both amounts must equal the invoice total.'])
                    ->withInput();
            }
            if ($method1NonCredit && $pay2 <= 0 && abs($pay1 - $total) > 0.01) {
                return back()->withErrors(['pay_1' => 'Payment amount must equal the invoice total when using Cash, Bank or Cheque only.'])
                    ->withInput();
            }

            $orderStatus = 'complete';

            $order_id = null;
            $purchase_id = null;

            try {
                DB::transaction(function () use (
                    &$order_id, &$purchase_id, $validatedData, $motherShop, $childShop, $supplier,
                    $systemCustomer, $invoice_no, $subtotal, $totalProducts, $vat, $invoiceDiscount,
                    $total, $pay, $due, $authUser, $request, $creditService, $supplierCreditService, $orderStatus,
                    $paymentMethod1, $paymentMethod2, $pay1, $pay2, $shopBankId1, $shopBankId2
                ) {
                    $orderData = [
                        'customer_id' => $systemCustomer->id,
                        'shop_id' => $motherShop->id,
                        'order_date' => Carbon::parse($validatedData['order_date'])->format('Y-m-d H:i:s'),
                        'order_status' => $orderStatus,
                        'total_products' => $totalProducts,
                        'sub_total' => $subtotal,
                        'invoice_discount' => $invoiceDiscount,
                        'vat' => $vat,
                        'invoice_no' => $invoice_no,
                        'total' => $total,
                        'payment_status' => $paymentMethod1,
                        'pay' => $pay,
                        'due' => $due,
                        'comment' => $request->input('comment'),
                    ];

                    $order = Order::create($orderData);
                    $order_id = $order->id;

                    app(SaleLedgerService::class)->recordInvoiceCustomerDebit($order);

                    if ($pay1 > 0) {
                        $this->createPaymentLog($order_id, $pay1, $paymentMethod1, $shopBankId1);
                    }
                    if ($pay2 > 0 && $paymentMethod2) {
                        $this->createPaymentLog($order_id, $pay2, $paymentMethod2, $shopBankId2);
                    }

                    // 2. Process products: Reduce mother shop stock, add/update child shop products
                    foreach ($validatedData['products'] as $product) {
                        if (empty($product['product_id'])) {
                            continue;
                        }

                        $motherProduct = Product::findOrFail($product['product_id']);

                        // Commented out for now: require active status and valid selling_price
                        // if ($motherProduct->status !== 'active' || empty($motherProduct->selling_price) || $motherProduct->selling_price <= 0) {
                        //     throw new \Exception("Product {$motherProduct->product_name} is not available for sale.");
                        // }

                        // Validate product belongs to mother shop
                        if ($motherProduct->shop_id !== $motherShop->id) {
                            throw new \Exception("Product {$motherProduct->product_name} does not belong to your shop.");
                        }

                        // Validate stock
                        if ($motherProduct->product_store < $product['quantity']) {
                            throw new \Exception("Insufficient stock for product: " . ($motherProduct->product_code ?? $motherProduct->product_name) . ". Available: {$motherProduct->product_store}");
                        }

                        // Create order detail
                        OrderDetails::insert([
                            'order_id' => $order_id,
                            'product_id' => $product['product_id'],
                            'quantity' => $product['quantity'],
                            'unitcost' => $product['unit_price'],
                            'cost_per_unit' => (float) ($motherProduct->buying_price ?? 0),
                            'item_discount' => $product['item_discount'] ?? 0,
                            'total' => $product['total'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);

                        // Reduce mother shop stock
                        Product::where('id', $product['product_id'])
                            ->update(['product_store' => DB::raw('product_store - ' . $product['quantity'])]);

                        // Check if child shop has this product (query child shop, so bypass shop scope)
                        $childProduct = Product::withoutGlobalScope('shop')
                            ->where('shop_id', $childShop->id)
                            ->where('product_name', $motherProduct->product_name)
                            ->first();

                        if ($childProduct) {
                            // Update stock only (keep existing attributes; child product is in child shop)
                            Product::withoutGlobalScope('shop')
                                ->where('id', $childProduct->id)
                                ->update(['product_store' => DB::raw('product_store + ' . $product['quantity'])]);
                        } else {
                            // Resolve category for child shop: find by name or create (query child shop, so bypass shop scope)
                            $motherCategory = Category::withoutGlobalScope('shop')->find($motherProduct->category_id);
                            $categoryName = $motherCategory ? trim($motherCategory->name) : 'Uncategorized';
                            $childCategory = Category::withoutGlobalScope('shop')
                                ->where('shop_id', $childShop->id)
                                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($categoryName)])
                                ->first();
                            if (!$childCategory) {
                                $childCategory = Category::create([
                                    'name'   => $categoryName,
                                    'shop_id' => $childShop->id,
                                    'slug'   => Str::slug($categoryName),
                                ]);
                            }
                            $childCategoryId = $childCategory->id;

                            // Create new product for child shop (same product code as mother shop; unique per shop)
                            Product::create([
                                'product_name' => $motherProduct->product_name,
                                'category_id' => $childCategoryId,
                                'supplier_id' => $supplier->id,
                                'shop_id' => $childShop->id,
                                'product_code' => $motherProduct->product_code,
                                'product_garage' => $motherProduct->product_garage,
                                'product_image' => $motherProduct->product_image,
                                'product_store' => $product['quantity'],
                                'low_stock_warning' => $motherProduct->low_stock_warning,
                                'buying_date' => $motherProduct->buying_date,
                                'expire_date' => $motherProduct->expire_date,
                                'buying_price' => $product['unit_price'], // Invoice price
                                'selling_price' => $product['unit_price'], // Same as buying_price
                                'status' => $motherProduct->status,
                            ]);
                        }
                    }

                    // 3. Generate purchase invoice number
                    $purchase_no = IdGenerator::generate([
                        'table' => 'purchases',
                        'field' => 'purchase_no',
                        'length' => 10,
                        'prefix' => 'PUR-'
                    ]);

                    // 4. Create Purchase (Purchase Invoice) — link to mother sale for cascade delete
                    $purchaseData = [
                        'supplier_id' => $supplier->id,
                        'shop_id' => $childShop->id,
                        'source_sale_id' => $order_id,
                        'is_system_generated' => true,
                        'purchase_date' => Carbon::parse($validatedData['order_date'])->format('Y-m-d'),
                        'purchase_status' => 'pending',
                        'total_products' => $totalProducts,
                        'sub_total' => $subtotal,
                        'invoice_discount' => $invoiceDiscount,
                        'vat' => $vat,
                        'purchase_no' => $purchase_no,
                        'total' => $total,
                        'payment_status' => $paymentMethod1,
                        'pay' => $pay,
                        'due' => $due,
                        'comment' => $request->input('comment'),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    $purchase = Purchase::create($purchaseData);
                    $purchase_id = $purchase->id;

                    // 4b. Accounting entries for system-generated child purchase (prevent duplicates)
                    if (!AccountTransaction::where('source_type', AccountTransaction::SOURCE_PURCHASE)->where('source_id', $purchase_id)->exists()) {
                        $transactionDate = Carbon::parse($validatedData['order_date'])->format('Y-m-d');
                        $descPurchase = 'Purchase ' . $purchase_no;

                        AccountTransaction::create([
                            'shop_id' => $childShop->id,
                            'account_type' => AccountTransaction::ACCOUNT_TYPE_PURCHASE,
                            'account_ref_id' => null,
                            'direction' => AccountTransaction::DIRECTION_DEBIT,
                            'amount' => $total,
                            'source_type' => AccountTransaction::SOURCE_PURCHASE,
                            'source_id' => $purchase_id,
                            'description' => $descPurchase,
                            'transaction_date' => $transactionDate,
                        ]);

                        AccountTransaction::create([
                            'shop_id' => $childShop->id,
                            'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
                            'account_ref_id' => $supplier->id,
                            'direction' => AccountTransaction::DIRECTION_CREDIT,
                            'amount' => $total,
                            'source_type' => AccountTransaction::SOURCE_PURCHASE,
                            'source_id' => $purchase_id,
                            'description' => $descPurchase,
                            'transaction_date' => $transactionDate,
                        ]);

                        if ($pay > 0) {
                            $accountType = $paymentMethod1 === 'bank' ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
                            AccountTransaction::create([
                                'shop_id' => $childShop->id,
                                'account_type' => $accountType,
                                'account_ref_id' => null,
                                'direction' => AccountTransaction::DIRECTION_CREDIT,
                                'amount' => $pay,
                                'source_type' => AccountTransaction::SOURCE_PURCHASE_PAYMENT,
                                'source_id' => $purchase_id,
                                'description' => 'Purchase Payment ' . $purchase_no,
                                'transaction_date' => $transactionDate,
                            ]);
                        }
                    }

                    // 5. Create Purchase Details
                    foreach ($validatedData['products'] as $product) {
                        if (empty($product['product_id'])) {
                            continue;
                        }

                        // Find child shop's product (already created/updated above; query child shop, so bypass shop scope)
                        $motherProduct = Product::findOrFail($product['product_id']);
                        $childProduct = Product::withoutGlobalScope('shop')
                            ->where('shop_id', $childShop->id)
                            ->where('product_name', $motherProduct->product_name)
                            ->firstOrFail();

                        PurchaseDetail::insert([
                            'purchase_id' => $purchase_id,
                            'product_id' => $childProduct->id,
                            'quantity' => $product['quantity'],
                            'unitcost' => $product['unit_price'], // Invoice unit_price as purchase unitcost
                            'item_discount' => $product['item_discount'] ?? 0,
                            'total' => $product['total'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }

                    // 6. Create purchase payment log if payment was made
                    if ($pay > 0) {
                        PurchasePaymentLog::create([
                            'purchase_id' => $purchase_id,
                            'amount_paid' => $pay,
                            'type' => 'payment',
                        ]);
                    }

                    // 7. Handle supplier credit (if due > 0)
                    if ($due > 0) {
                        $supplierCreditService->addPending($supplier, $due);
                    }

                    // 8. Handle customer credit (system customer - but should be 0 usually)
                    if ($due > 0) {
                        $creditService->addPending($systemCustomer, $due);
                    }
                });

                return Redirect::route('order.index')->with([
                    'success' => 'Stock transfer invoice has been created successfully! Purchase invoice has been auto-generated.',
                ]);

            } catch (\Exception $e) {
                // Rollback on error
                if ($order_id) {
                    Order::where('id', $order_id)->delete();
                }
                if ($purchase_id) {
                    Purchase::where('id', $purchase_id)->delete();
                }
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }
        }

        // Should not reach here
        return back()->withErrors(['error' => 'Please select either a customer or a shop.'])->withInput();
    }

}
