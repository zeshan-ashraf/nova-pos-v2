<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    use ReportTrait;

    public function stock(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        $productsQuery = Product::with(['category', 'shop.parent']);

        $this->applyShopFilter($productsQuery, $shopFilter['shop_ids']);

        // Filter by stock status if provided
        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            if ($status === 'low') {
                $productsQuery->whereColumn('product_store', '<=', 'low_stock_warning');
            } elseif ($status === 'out') {
                $productsQuery->where('product_store', '<=', 0);
            }
        }

        $products = $productsQuery->orderBy('product_store')->paginate($row)->appends($request->query());

        // Summary
        $totalProducts = (clone $productsQuery)->count();
        $lowStockCount = (clone $productsQuery)->whereColumn('product_store', '<=', 'low_stock_warning')->count();
        $outOfStockCount = (clone $productsQuery)->where('product_store', '<=', 0)->count();
        $totalStockValue = (clone $productsQuery)->sum(DB::raw('product_store * buying_price'));

        return view('reports.inventory.stock', compact(
            'shopFilter', 'products', 'totalProducts', 'lowStockCount', 
            'outOfStockCount', 'totalStockValue', 'row'
        ));
    }

    public function stockMovement(Request $request)
    {
        return view('reports.inventory.stock-movement');
    }

    public function stockValuation(Request $request)
    {
        return view('reports.inventory.stock-valuation');
    }

    public function expiredProducts(Request $request)
    {
        return view('reports.inventory.expired-products');
    }
}
