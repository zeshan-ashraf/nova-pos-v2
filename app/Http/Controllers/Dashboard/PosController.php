<?php

namespace App\Http\Controllers\Dashboard;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Gloudemans\Shoppingcart\Facades\Cart;
use App\Support\ActiveShop;
use App\Support\ProductUnitValidator;
use Illuminate\Support\Facades\Redirect;
use InvalidArgumentException;

class PosController extends Controller
{
    public function index()
    {
        $todayDate = Carbon::now();
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        // Only show products with status='active' and selling_price IS NOT NULL
        $productsQuery = Product::where('status', 'active')
            ->whereNotNull('selling_price')
            ->where('selling_price', '>', 0)
            ->filter(request(['search']))
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            // Child shop or parent shop user - only see their allowed shops
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            // Super admin - can see all products
            $productsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

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

        return view('pos.index', [
            'customers' => $customersQuery->orderBy('name')->get(),
            'productItem' => Cart::content(),
            'products' => $productsQuery->paginate($row)->appends(request()->query()),
        ]);
    }

    public function addCart(Request $request)
    {
        $rules = [
            'id' => 'required|numeric',
            'name' => 'required|string',
            'price' => 'required|numeric',
        ];

        $validatedData = $request->validate($rules);

        // Validate product status and selling_price before adding to cart
        $product = Product::find($validatedData['id']);
        
        if (!$product) {
            return Redirect::back()->withErrors(['product' => 'Product not found.']);
        }

        // Commented out for now: require active status and valid selling_price
        // if ($product->status !== 'active' || empty($product->selling_price) || $product->selling_price <= 0) {
        //     return Redirect::back()->withErrors(['product' => 'This product is not available for sale.']);
        // }

        Cart::add([
            'id' => $validatedData['id'],
            'name' => $validatedData['name'],
            'qty' => 1,
            'price' => $validatedData['price'],
            'options' => [
                'size' => 'large',
                'unit' => $product->unit ?: Product::UNIT_PIECE,
            ],
        ]);

        return Redirect::back()->with('success', 'Product has been added!');
    }

    public function updateCart(Request $request, $rowId)
    {
        $rules = [
            'qty' => 'required|numeric',
        ];

        $validatedData = $request->validate($rules);

        $item = Cart::get($rowId);
        if (! $item) {
            return Redirect::back()->withErrors(['qty' => 'Cart item not found.']);
        }

        $product = Product::find($item->id);
        if (! $product) {
            return Redirect::back()->withErrors(['qty' => 'Product not found.']);
        }

        try {
            app(ProductUnitValidator::class)->validateQuantity(
                $validatedData['qty'],
                $product->unit ?: Product::UNIT_PIECE,
                false
            );
        } catch (InvalidArgumentException $e) {
            return Redirect::back()->withErrors(['qty' => $e->getMessage()])->withInput();
        }

        Cart::update($rowId, $validatedData['qty']);

        return Redirect::back()->with('success', 'Cart has been updated!');
    }

    public function deleteCart(String $rowId)
    {
        Cart::remove($rowId);

        return Redirect::back()->with('success', 'Cart has been deleted!');
    }

    public function createInvoice(Request $request)
    {
        $rules = [
            'customer_id' => 'required'
        ];

        $validatedData = $request->validate($rules);
        $customer = Customer::where('id', $validatedData['customer_id'])->first();
        $content = Cart::content();
        $authUser = auth()->user();
        $shopBanks = collect();
        if ($authUser->shop_id) {
            $shopBanks = DB::table('bank_shop')
                ->where('bank_shop.shop_id', $authUser->shop_id)
                ->join('banks', 'bank_shop.bank_id', '=', 'banks.id')
                ->select('bank_shop.id', 'banks.name')
                ->orderBy('banks.name')
                ->get();
        }

        return view('pos.create-invoice', [
            'customer' => $customer,
            'content' => $content,
            'shopBanks' => $shopBanks,
            'cartTotal' => Cart::total(),
        ]);
    }

    public function printInvoice(Request $request)
    {
        $rules = [
            'customer_id' => 'required'
        ];

        $validatedData = $request->validate($rules);
        $customer = Customer::where('id', $validatedData['customer_id'])->first();
        $content = Cart::content();

        return view('pos.print-invoice', [
            'customer' => $customer,
            'content' => $content
        ]);
    }
}
