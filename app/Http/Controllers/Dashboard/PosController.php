<?php

namespace App\Http\Controllers\Dashboard;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Gloudemans\Shoppingcart\Facades\Cart;
use App\Support\ActiveShop;

class PosController extends Controller
{
    public function index()
    {
        $todayDate = Carbon::now();
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $productsQuery = Product::where('status', 'active')
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

        Cart::add([
            'id' => $validatedData['id'],
            'name' => $validatedData['name'],
            'qty' => 1,
            'price' => $validatedData['price'],
            'options' => ['size' => 'large']
        ]);

        return Redirect::back()->with('success', 'Product has been added!');
    }

    public function updateCart(Request $request, $rowId)
    {
        $rules = [
            'qty' => 'required|numeric',
        ];

        $validatedData = $request->validate($rules);

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

        return view('pos.create-invoice', [
            'customer' => $customer,
            'content' => $content
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
