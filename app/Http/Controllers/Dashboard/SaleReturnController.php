<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\PaymentLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use App\Support\ActiveShop;
use App\Services\CustomerCreditService;

class SaleReturnController extends Controller
{
    /**
     * Display a listing of sale returns.
     */
    public function index()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $search = request('search');
        $returnsQuery = SaleReturn::with(['customer', 'order', 'shop.parent'])
            ->sortable()
            ->when($search, function ($query, $search) {
                return $query->where('return_no', 'like', '%' . $search . '%')
                             ->orWhereHas('customer', function($query) use ($search) {
                                 $query->where('shopname', 'like', '%' . $search . '%')
                                       ->orWhere('name', 'like', '%' . $search . '%');
                             })
                             ->orWhereHas('order', function($query) use ($search) {
                                 $query->where('invoice_no', 'like', '%' . $search . '%');
                             })
                             ->orWhere('return_date', 'like', '%' . $search . '%');
            });

        // Apply shop filtering
        if ($authUser->shop_id) {
            $returnsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $returnsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('sale-returns.index', [
            'returns' => $returnsQuery->paginate($row)->appends(request()->query())
        ]);
    }

    /**
     * Show the form for creating a new sale return.
     */
    public function create()
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

        return view('sale-returns.create', [
            'customers' => $customersQuery->orderBy('shopname')->get(),
        ]);
    }

    /**
     * Get orders for a customer (AJAX).
     */
    public function getCustomerOrders($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        
        // Check shop access
        if ($authUser->shop_id) {
            if ($customer->shop_id && !$visibleShopIds->contains($customer->shop_id)) {
                abort(403, 'You do not have access to this customer.');
            }
        }

        $ordersQuery = Order::where('customer_id', $customerId)
            ->orderBy('order_date', 'desc')
            ->orderBy('created_at', 'desc');

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

        $orders = $ordersQuery->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'order_date' => $order->order_date,
                'total' => $order->total,
                'pay' => $order->pay,
                'due' => $order->due,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
            ];
        });

        return response()->json(['orders' => $orders]);
    }

    /**
     * Get order details for return creation.
     */
    public function getOrderDetails($orderId)
    {
        $order = Order::with(['orderDetails.product', 'customer'])
            ->findOrFail($orderId);
        
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        
        // Check shop access
        if ($authUser->shop_id) {
            if ($order->shop_id && !$visibleShopIds->contains($order->shop_id)) {
                abort(403, 'You do not have access to this order.');
            }
        }

        // Calculate already returned quantities for each order detail
        $orderDetails = $order->orderDetails->map(function ($orderDetail) {
            $returnedQty = SaleReturnDetail::where('order_detail_id', $orderDetail->id)
                ->sum('quantity');
            
            return [
                'id' => $orderDetail->id,
                'product_id' => $orderDetail->product_id,
                'product_name' => $orderDetail->product->product_name ?? 'N/A',
                'product_code' => $orderDetail->product->product_code ?? 'N/A',
                'quantity' => $orderDetail->quantity,
                'returned_quantity' => $returnedQty,
                'available_to_return' => $orderDetail->quantity - $returnedQty,
                'unitcost' => $orderDetail->unitcost,
                'item_discount' => $orderDetail->item_discount ?? 0,
                'total' => $orderDetail->total,
            ];
        });

        return response()->json([
            'order' => [
                'id' => $order->id,
                'invoice_no' => $order->invoice_no,
                'order_date' => $order->order_date,
                'total' => $order->total,
                'pay' => $order->pay,
                'due' => $order->due,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
            ],
            'customer' => [
                'id' => $order->customer->id,
                'name' => $order->customer->shopname ?? $order->customer->name,
            ],
            'order_details' => $orderDetails,
        ]);
    }

    /**
     * Store a newly created sale return.
     */
    public function store(Request $request)
    {
        $rules = [
            'order_id' => 'required|numeric|exists:orders,id',
            'return_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.order_detail_id' => 'required_with:products.*.product_id|numeric|exists:order_details,id',
            'products.*.product_id' => 'required_with:products.*.quantity|numeric|exists:products,id',
            'products.*.quantity' => 'required_with:products.*.product_id|numeric|min:1',
            'products.*.unit_price' => 'required_with:products.*.product_id|numeric|min:0',
            'products.*.item_discount' => 'nullable|numeric|min:0',
            'products.*.total' => 'required_with:products.*.product_id|numeric|min:0',
            'vat' => 'nullable|numeric|min:0',
            'invoice_discount' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ];

        $validatedData = $request->validate($rules);

        $authUser = auth()->user();
        $order = Order::with(['customer', 'orderDetails'])->findOrFail($validatedData['order_id']);
        
        // Check shop access
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        if ($authUser->shop_id) {
            if ($order->shop_id && !$visibleShopIds->contains($order->shop_id)) {
                abort(403, 'You do not have access to this order.');
            }
        }

        $customer = $order->customer;
        $creditService = new CustomerCreditService();

        // Generate return number
        $return_no = IdGenerator::generate([
            'table' => 'sale_returns',
            'field' => 'return_no',
            'length' => 10,
            'prefix' => 'RET-'
        ]);

        // Calculate totals (only for selected products)
        $subtotal = 0;
        $totalProducts = 0;
        $selectedProducts = array_filter($validatedData['products'], function($product) {
            return !empty($product['product_id']) && !empty($product['quantity']) && $product['quantity'] > 0;
        });
        
        foreach ($selectedProducts as $product) {
            $subtotal += $product['total'];
            $totalProducts++;
        }

        $vat = $validatedData['vat'] ?? 0;
        $invoiceDiscount = $validatedData['invoice_discount'] ?? 0;
        $total = max(0, $subtotal + $vat - $invoiceDiscount);

        // Prepare return data
        $returnData = [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'shop_id' => $authUser->shop_id ?? $order->shop_id,
            'return_date' => Carbon::parse($validatedData['return_date'])->format('Y-m-d H:i:s'),
            'return_status' => 'completed',
            'return_no' => $return_no,
            'total_products' => $totalProducts,
            'sub_total' => $subtotal,
            'invoice_discount' => $invoiceDiscount,
            'vat' => $vat,
            'total' => $total,
            'reason' => $validatedData['reason'] ?? null,
        ];

        $return_id = null;

        try {
            DB::transaction(function () use (&$return_id, $returnData, $validatedData, $order, $customer, $creditService, $authUser) {
                // 1. Create sale return
                $saleReturn = SaleReturn::create($returnData);
                $return_id = $saleReturn->id;

                // 2. Create return details and adjust stock
                // Filter out empty products (only process selected items)
                $selectedProducts = array_filter($validatedData['products'], function($product) {
                    return !empty($product['product_id']) && !empty($product['quantity']) && $product['quantity'] > 0;
                });

                if (empty($selectedProducts)) {
                    throw new \Exception("Please select at least one item to return.");
                }

                foreach ($selectedProducts as $product) {

                    $orderDetail = OrderDetails::findOrFail($product['order_detail_id']);
                    
                    // Validate that we're not returning more than available
                    $alreadyReturned = SaleReturnDetail::where('order_detail_id', $orderDetail->id)
                        ->sum('quantity');
                    $availableToReturn = $orderDetail->quantity - $alreadyReturned;

                    if ($product['quantity'] > $availableToReturn) {
                        throw new \Exception("Cannot return more than available quantity for product. Available: {$availableToReturn}");
                    }

                    // Validate shop access for product
                    $productModel = Product::findOrFail($product['product_id']);
                    if ($authUser->shop_id && $productModel->shop_id !== $authUser->shop_id) {
                        throw new \Exception("Product does not belong to your shop.");
                    }

                    // Create return detail
                    $returnDetailData = [
                        'return_id' => $return_id,
                        'order_id' => $order->id,
                        'order_detail_id' => $orderDetail->id,
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'unitcost' => $product['unit_price'],
                        'item_discount' => $product['item_discount'] ?? 0,
                        'total' => $product['total'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];

                    SaleReturnDetail::create($returnDetailData);

                    // Increase stock
                    Product::where('id', $product['product_id'])
                        ->update(['product_store' => DB::raw('product_store + ' . $product['quantity'])]);
                }

                // 3. Adjust order paid amount and create refund
                $returnAmount = $total;
                $originalPay = $order->pay ?? 0;
                $originalDue = $order->due ?? 0;
                
                // Reduce paid amount (but not below 0)
                $payReduction = min($returnAmount, $originalPay);
                $newPay = max(0, $originalPay - $payReduction);
                
                // Increase due by the amount that was reduced from paid
                $newDue = $originalDue + $payReduction;

                $order->update([
                    'pay' => $newPay,
                    'due' => $newDue,
                ]);

                // 4. Create refund in payment log (always create refund entry)
                if ($returnAmount > 0) {
                    PaymentLog::create([
                        'order_id' => $order->id,
                        'amount_paid' => -$returnAmount, // Negative amount for refund
                        'type' => 'refund',
                    ]);
                }

                // 5. Adjust customer credit
                if ($customer) {
                    // If order had due amount before return, reduce credit by the return amount
                    // This is because returning items reduces the credit that was added when order was created
                    if ($originalDue > 0) {
                        // Reduce credit by the return amount (up to the original due)
                        $creditReduction = min($returnAmount, $originalDue);
                        if ($creditReduction > 0) {
                            $creditService->removePending($customer, $creditReduction);
                        }
                    }
                    // Note: If order was fully paid (no due), we don't adjust credit
                    // The refund is tracked in payment log with negative amount
                }
            });

            return Redirect::route('sale-returns.index')->with('success', 'Sale return has been created successfully!');
        } catch (\Exception $e) {
            // If return was created, delete it
            if ($return_id) {
                SaleReturn::where('id', $return_id)->delete();
            }
            return back()->withErrors(['error' => 'Failed to create return: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified sale return.
     */
    public function show(int $return_id)
    {
        $saleReturn = SaleReturn::with(['customer', 'order', 'returnDetails.product', 'shop'])
            ->findOrFail($return_id);
        
        $this->ensureShopAccess($saleReturn);

        return view('sale-returns.show', [
            'saleReturn' => $saleReturn,
        ]);
    }

    /**
     * Delete (soft delete) a sale return and reverse all related operations.
     */
    public function destroy(int $return_id)
    {
        $saleReturn = SaleReturn::with(['customer', 'returnDetails.product', 'order'])
            ->findOrFail($return_id);
        $this->ensureShopAccess($saleReturn);

        $creditService = new CustomerCreditService();
        $customer = $saleReturn->customer;
        $order = $saleReturn->order;

        try {
            DB::transaction(function () use ($saleReturn, $customer, $order, $creditService) {
                // 1. Reverse stock for all return details (decrease stock back)
                foreach ($saleReturn->returnDetails as $returnDetail) {
                    $product = $returnDetail->product;
                    
                    if (!$product) {
                        throw new \Exception("Product with ID {$returnDetail->product_id} not found. Cannot reverse stock.");
                    }

                    // Decrease stock (reverse the increase from return)
                    Product::where('id', $returnDetail->product_id)
                        ->update(['product_store' => DB::raw('product_store - ' . $returnDetail->quantity)]);
                }

                // 2. Reverse order paid/due adjustments
                $returnAmount = $saleReturn->total;
                $currentPay = $order->pay ?? 0;
                $currentDue = $order->due ?? 0;
                
                // When return was created, paid was reduced and due was increased
                // Now we need to restore: increase paid back, decrease due back
                $newPay = $currentPay + $returnAmount;
                $newDue = max(0, $currentDue - $returnAmount);

                $order->update([
                    'pay' => $newPay,
                    'due' => $newDue,
                ]);

                // 3. Delete refund payment log entry (find by order_id, type='refund', and amount)
                // We'll find the most recent refund matching the return amount
                $refundLog = PaymentLog::where('order_id', $order->id)
                    ->where('type', 'refund')
                    ->where('amount_paid', -$returnAmount)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($refundLog) {
                    $refundLog->delete();
                }

                // 4. Reverse customer credit adjustment
                if ($customer) {
                    // When return was created, if order had due, credit was reduced
                    // We need to restore it - but we need to check what the due was before return
                    // Since we're increasing due back, we should restore credit proportionally
                    // For simplicity, restore credit by the return amount (if it affects due)
                    if ($currentDue > 0) {
                        $creditRestored = min($returnAmount, $currentDue);
                        if ($creditRestored > 0) {
                            $creditService->addPending($customer, $creditRestored);
                        }
                    }
                }

                // 5. Soft delete return details
                foreach ($saleReturn->returnDetails as $returnDetail) {
                    $returnDetail->delete();
                }

                // 6. Soft delete return
                $saleReturn->delete();
            });

            return Redirect::route('sale-returns.index')->with('success', 'Sale return has been deleted successfully! Stock and payments have been reversed.');
        } catch (\Exception $e) {
            return Redirect::route('sale-returns.index')->with('error', 'Failed to delete return: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the current user has access to the sale return based on shop.
     */
    protected function ensureShopAccess(SaleReturn $saleReturn): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            if ($saleReturn->shop_id && !$visibleShopIds->contains($saleReturn->shop_id)) {
                abort(403, 'You do not have access to this return.');
            }
        }
        // Super admin can access all returns
    }
}
