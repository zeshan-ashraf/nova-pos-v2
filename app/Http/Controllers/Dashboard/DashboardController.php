<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Support\ActiveShop;

class DashboardController extends Controller
{
    public function index(){
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $ordersQuery = Order::query();
        $productsQuery = Product::query();

        // Apply shop filtering for orders
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

        // Apply shop filtering for products
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

        return view('dashboard.index', [
            'total_paid' => (clone $ordersQuery)->sum('pay'),
            'total_due' => (clone $ordersQuery)->sum('due'),
            'complete_orders' => (clone $ordersQuery)->where('order_status', 'complete')->get(),
            'products' => (clone $productsQuery)->orderBy('product_store')->take(5)->get(),
            'new_products' => (clone $productsQuery)->orderBy('buying_date')->take(2)->get(),
        ]);
    }
}
