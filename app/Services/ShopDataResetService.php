<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\Shop;
use App\Models\StockLog;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class ShopDataResetService
{
    /**
     * Reset all data for a child shop (excludes users, walk-in customers, shop row).
     * Stock is reversed to mother shop before products are deleted.
     *
     * @param int $shopId
     * @param bool $dryRun If true, no changes are made; returns counts of what would be deleted
     * @return array{success: bool, message: string, dry_run?: array}
     */
    public function resetShopData(int $shopId, bool $dryRun = false): array
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return ['success' => false, 'message' => "Shop with ID {$shopId} not found."];
        }

        if ($shop->is_parent || !$shop->parent_shop_id) {
            return ['success' => false, 'message' => 'This command only supports child shops (shop must have parent_shop_id).'];
        }

        $motherShopId = $shop->parent_shop_id;

        if ($dryRun) {
            $counts = $this->gatherCounts($shopId);
            return [
                'success' => true,
                'message' => 'Dry run complete.',
                'dry_run' => $counts,
                'shop' => $shop->name,
                'mother_shop_id' => $motherShopId,
            ];
        }

        try {
            DB::transaction(function () use ($shopId, $motherShopId) {
                // 1. Stock reversal: add child product_store to mother for matching product_code
                $this->reverseStockToMother($shopId, $motherShopId);

                // 2. Get order IDs for this shop (before deleting orders)
                $orderIds = Order::where('shop_id', $shopId)->pluck('id')->all();

                // 3. Sale return details (before sale_returns, order_details)
                $returnIds = SaleReturn::where('shop_id', $shopId)->pluck('id')->all();
                if (!empty($returnIds)) {
                    SaleReturnDetail::whereIn('return_id', $returnIds)->forceDelete();
                }

                // 4. Sale returns
                SaleReturn::where('shop_id', $shopId)->forceDelete();

                // 5. Payment logs (order payments)
                if (!empty($orderIds)) {
                    PaymentLog::whereIn('order_id', $orderIds)->forceDelete();
                }

                // 6. Order details
                if (!empty($orderIds)) {
                    OrderDetails::whereIn('order_id', $orderIds)->forceDelete();
                }

                // 7. Orders
                Order::where('shop_id', $shopId)->forceDelete();

                // 8. Purchase payment logs and purchase details, then purchases
                $purchaseIds = Purchase::where('shop_id', $shopId)->pluck('id')->all();
                if (!empty($purchaseIds)) {
                    PurchasePaymentLog::whereIn('purchase_id', $purchaseIds)->forceDelete();
                    PurchaseDetail::whereIn('purchase_id', $purchaseIds)->forceDelete();
                }
                Purchase::where('shop_id', $shopId)->forceDelete();

                // 9. Stock logs
                StockLog::where('shop_id', $shopId)->forceDelete();

                // 10. Account transactions
                AccountTransaction::where('shop_id', $shopId)->forceDelete();

                // 11. Activities (linked to expenses; delete by shop_id first)
                Activity::where('shop_id', $shopId)->delete();

                // 12. Expenses
                Expense::where('shop_id', $shopId)->forceDelete();

                // 13. Bank-shop pivot
                DB::table('bank_shop')->where('shop_id', $shopId)->delete();

                // 14. Products
                Product::where('shop_id', $shopId)->delete();

                // 15. Categories
                Category::where('shop_id', $shopId)->delete();

                // 16. Suppliers
                Supplier::where('shop_id', $shopId)->delete();

                // 17. Non-walk-in customers (keep walk-in with shop_id)
                Customer::where('shop_id', $shopId)
                    ->where(function ($q) {
                        $q->where('is_walkin', false)
                            ->orWhere('is_walkin', 0)
                            ->orWhereNull('is_walkin');
                    })
                    ->delete();
            });

            return ['success' => true, 'message' => "Shop data reset successfully for shop ID {$shopId}."];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error during reset: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * Add child shop's product_store to mother shop's products (matched by product_code).
     * Only update mother products that exist; ignore child products with no mother match.
     */
    protected function reverseStockToMother(int $childShopId, int $motherShopId): void
    {
        $childProducts = Product::where('shop_id', $childShopId)->get(['id', 'product_code', 'product_store']);
        $motherProductsByCode = Product::where('shop_id', $motherShopId)
            ->whereIn('product_code', $childProducts->pluck('product_code')->filter()->unique()->values())
            ->get()
            ->keyBy('product_code');

        foreach ($childProducts as $child) {
            if (empty($child->product_code)) {
                continue;
            }
            $mother = $motherProductsByCode->get($child->product_code);
            if (!$mother) {
                continue;
            }
            $qty = (int) ($child->product_store ?? 0);
            if ($qty <= 0) {
                continue;
            }
            Product::where('id', $mother->id)->increment('product_store', $qty);
        }
    }

    /**
     * Gather counts for dry-run report.
     */
    protected function gatherCounts(int $shopId): array
    {
        $orderIds = Order::where('shop_id', $shopId)->pluck('id')->all();
        $returnIds = SaleReturn::where('shop_id', $shopId)->pluck('id')->all();
        $purchaseIds = Purchase::where('shop_id', $shopId)->pluck('id')->all();

        return [
            'orders' => count($orderIds),
            'order_details' => !empty($orderIds) ? OrderDetails::whereIn('order_id', $orderIds)->count() : 0,
            'payment_logs' => !empty($orderIds) ? PaymentLog::whereIn('order_id', $orderIds)->count() : 0,
            'sale_returns' => count($returnIds),
            'sale_return_details' => !empty($returnIds) ? SaleReturnDetail::whereIn('return_id', $returnIds)->count() : 0,
            'purchases' => count($purchaseIds),
            'purchase_details' => !empty($purchaseIds) ? PurchaseDetail::whereIn('purchase_id', $purchaseIds)->count() : 0,
            'purchase_payment_logs' => !empty($purchaseIds) ? PurchasePaymentLog::whereIn('purchase_id', $purchaseIds)->count() : 0,
            'stock_logs' => StockLog::where('shop_id', $shopId)->count(),
            'account_transactions' => AccountTransaction::where('shop_id', $shopId)->count(),
            'activities' => Activity::where('shop_id', $shopId)->count(),
            'expenses' => Expense::where('shop_id', $shopId)->count(),
            'bank_shop' => DB::table('bank_shop')->where('shop_id', $shopId)->count(),
            'products' => Product::where('shop_id', $shopId)->count(),
            'categories' => Category::where('shop_id', $shopId)->count(),
            'suppliers' => Supplier::where('shop_id', $shopId)->count(),
            'customers_non_walkin' => Customer::where('shop_id', $shopId)
                ->where(function ($q) {
                    $q->where('is_walkin', false)
                        ->orWhere('is_walkin', 0)
                        ->orWhereNull('is_walkin');
                })
                ->count(),
            'customers_walkin_kept' => Customer::where('shop_id', $shopId)
                ->where(function ($q) {
                    $q->where('is_walkin', true)->orWhere('is_walkin', 1);
                })
                ->count(),
        ];
    }
}
