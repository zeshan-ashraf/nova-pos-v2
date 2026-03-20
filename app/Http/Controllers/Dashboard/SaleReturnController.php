<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Support\ActiveShop;
use App\Services\SaleReturnService;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class SaleReturnController extends Controller
{
    public function __construct(
        private SaleReturnService $saleReturnService,
    ) {}
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
        $order = Order::with(['orderDetails.product.parent', 'customer'])
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
                'product_name' => $orderDetail->product->resolved_name ?? 'N/A',
                'product_code' => $orderDetail->product->resolved_code ?? 'N/A',
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
            // Nested product fields are intentionally nullable here.
            // Only rows with a positive quantity are treated as "checked" return lines;
            // those are validated by the SaleReturnService::validateReturnQuantities method.
            'products.*.order_detail_id' => 'nullable|numeric|exists:order_details,id',
            'products.*.product_id' => 'nullable|numeric|exists:products,id',
            'products.*.quantity' => 'nullable|numeric|min:0',
            'products.*.unit_price' => 'nullable|numeric|min:0',
            'products.*.item_discount' => 'nullable|numeric|min:0',
            'products.*.total' => 'nullable|numeric|min:0',
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

        // Generate return number
        $return_no = IdGenerator::generate([
            'table' => 'sale_returns',
            'field' => 'return_no',
            'length' => 10,
            'prefix' => 'RET-'
        ]);

        // Validate per-line return quantities before creating any records.
        // Ensures we never insert sale_return_details that exceed sold quantity.
        $this->saleReturnService->validateReturnQuantities($order, $validatedData['products']);

        // Calculate totals (only for selected products)
        $subtotal = 0;
        $totalProducts = 0;
        $selectedProducts = array_filter($validatedData['products'], function ($product) {
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
            DB::transaction(function () use (&$return_id, $returnData, $validatedData, $order, $customer, $authUser) {
                // 1. Create sale return header using existing insert structure
                $saleReturn = SaleReturn::create($returnData);
                $return_id = $saleReturn->id;

                // 2. Create return details and restore inventory for each selected item
                $selectedProducts = array_filter($validatedData['products'], function ($product) {
                    return !empty($product['product_id']) && !empty($product['quantity']) && $product['quantity'] > 0;
                });

                if (empty($selectedProducts)) {
                    throw new \RuntimeException('Please select at least one item to return.');
                }

                foreach ($selectedProducts as $product) {
                    $this->saleReturnService->createReturnDetailAndRestoreInventory(
                        $saleReturn,
                        $order,
                        $product,
                        $authUser->shop_id
                    );
                }

                // 3. Apply financial impact (inventory-safe and accounting-safe)
                $this->saleReturnService->applyFinancialImpact($saleReturn, $order, $customer);
            });

            return Redirect::route('sale-returns.index')->with('success', 'Sale return has been created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
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
        $saleReturn = SaleReturn::with(['customer', 'order', 'returnDetails.product.parent', 'shop'])
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

        try {
            DB::transaction(function () use ($saleReturn) {
                $this->saleReturnService->reverseAndDelete($saleReturn);
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
