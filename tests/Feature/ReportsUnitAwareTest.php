<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\Shop;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Dashboard\DashboardDataService;
use App\Services\Reports\StockMovementReportService;
use App\Services\Reports\StockValuationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportsUnitAwareTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Category $category;

    private User $user;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->shop = Shop::create([
            'name' => 'Phase 7 Shop',
            'address' => 'Test address',
            'phone' => '03170000001',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 7 Category',
            'slug' => 'phase-7-category',
            'shop_id' => $this->shop->id,
        ]);

        foreach ([
            'reports.menu' => 'reports',
            'reports.inventory' => 'reports',
            'reports.sales' => 'reports',
            'reports.purchases' => 'reports',
            'reports.financial' => 'reports',
            'stock_audit_report_view' => 'reports',
            'product.menu' => 'product',
            'sale-returns.menu' => 'sale-returns',
            'purchases.menu' => 'purchases',
        ] as $name => $group) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => $group]
            );
        }

        $this->user = User::create([
            'name' => 'Phase 7 Admin',
            'username' => 'phase7admin',
            'email' => 'phase7admin@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->shop->id,
        ]);
        $this->user->givePermissionTo([
            'reports.menu',
            'reports.inventory',
            'reports.sales',
            'reports.purchases',
            'reports.financial',
            'stock_audit_report_view',
            'product.menu',
            'sale-returns.menu',
            'purchases.menu',
        ]);

        $this->customer = Customer::create([
            'name' => 'Phase 7 Customer',
            'email' => 'phase7customer@example.com',
            'phone' => '03170000002',
            'shop_id' => $this->shop->id,
            'is_walkin' => false,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Phase 7 Supplier',
            'phone' => '03170000003',
            'shop_id' => $this->shop->id,
        ]);
    }

    public function test_inventory_report_keeps_kg_decimals_and_piece_whole_numbers(): void
    {
        $sugar = $this->makeProduct([
            'product_name' => 'Sugar',
            'unit' => Product::UNIT_KG,
            'product_store' => '10.000',
            'buying_price' => '210.0000',
            'selling_price' => '250.00',
        ]);
        $fraction = $this->makeProduct([
            'product_name' => 'Rice',
            'unit' => Product::UNIT_KG,
            'product_store' => '0.750',
            'low_stock_warning' => '1.000',
            'buying_price' => '100.0000',
        ]);
        $soap = $this->makeProduct([
            'product_name' => 'Soap',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '3',
            'buying_price' => '80.0000',
            'selling_price' => '120.00',
        ]);

        $page = $this->actingAs($this->user)->get(route('reports.inventory.stock', ['date_filter' => 'all']));
        $page->assertOk();
        $page->assertSee('10.000');
        $page->assertSee('0.750');
        $page->assertSee('kg');
        $page->assertSee('3');
        $page->assertSee('piece');
        $page->assertDontSee('3.000');
        $page->assertDontSee('10.000 pieces');
        $page->assertSee('Low stock');

        $this->assertSame('2100.00', Product::moneyFromQuantity($sugar->product_store, $sugar->buying_price));
        $this->assertSame('3', $soap->formattedQuantity());
        $this->assertSame('0.750', $fraction->formattedQuantity());
    }

    public function test_low_stock_decimal_comparison_and_dashboard_label(): void
    {
        $this->makeProduct([
            'product_name' => 'Sugar Low',
            'unit' => Product::UNIT_KG,
            'product_store' => '0.750',
            'low_stock_warning' => '1.000',
        ]);

        $count = Product::withoutGlobalScopes()
            ->where('shop_id', $this->shop->id)
            ->where('product_name', 'Sugar Low')
            ->whereRaw('COALESCE(product_store, 0) <= COALESCE(low_stock_warning, 10)')
            ->count();
        $this->assertSame(1, $count);

        $alerts = app(DashboardDataService::class);
        $method = new \ReflectionMethod($alerts, 'lowStockAlerts');
        $method->setAccessible(true);
        $rows = $method->invoke($alerts, $this->shop->id);
        $row = collect($rows)->firstWhere('name', 'Sugar Low');
        $this->assertNotNull($row);
        $this->assertSame('0.750', $row['stock']);
        $this->assertSame('1.000', $row['threshold']);
        $this->assertSame('kg', $row['unit']);
    }

    public function test_sales_report_uses_historical_unit_and_does_not_mix_quantities(): void
    {
        $sugar = $this->makeProduct([
            'product_name' => 'Sugar',
            'unit' => Product::UNIT_KG,
            'product_store' => '10.000',
            'selling_price' => '250.00',
        ]);
        $soap = $this->makeProduct([
            'product_name' => 'Soap',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '20',
            'selling_price' => '120.00',
        ]);

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'order_date' => now(),
            'order_status' => 'complete',
            'total_products' => 2,
            'sub_total' => '547.50',
            'invoice_no' => 'INV-P7-1',
            'total' => '547.50',
            'payment_status' => 'paid',
            'pay' => '547.50',
            'due' => '0',
        ]);
        OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $sugar->id,
            'quantity' => '0.750',
            'unit' => Product::UNIT_KG,
            'unitcost' => '250.00',
            'total' => '187.50',
        ]);
        OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $soap->id,
            'quantity' => '3',
            'unit' => Product::UNIT_PIECE,
            'unitcost' => '120.00',
            'total' => '360.00',
        ]);

        DB::table('products')->where('id', $sugar->id)->update(['unit' => Product::UNIT_PIECE]);

        $page = $this->actingAs($this->user)->get(route('reports.sales.product', ['date_filter' => 'today']));
        $page->assertOk();
        $page->assertSee('0.750 kg');
        $page->assertSee('3 pieces');
        $page->assertSee('187.50');
        $page->assertDontSee('3.750');

        $detail = $this->actingAs($this->user)->get(route('reports.financial.profit-loss-line-detail', ['date_filter' => 'all']));
        $detail->assertOk();
        $detail->assertSee('0.750 kg');
        $detail->assertSee('3 pieces');
    }

    public function test_purchase_report_keeps_kg_quantity(): void
    {
        $sugar = $this->makeProduct([
            'product_name' => 'Sugar',
            'unit' => Product::UNIT_KG,
            'product_store' => '0',
        ]);
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'purchase_date' => now(),
            'purchase_status' => 'received',
            'total_products' => 1,
            'sub_total' => '500.00',
            'purchase_no' => 'PO-P7-1',
            'total' => '500.00',
            'pay' => '500.00',
            'due' => '0',
        ]);
        PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $sugar->id,
            'quantity' => '2.500',
            'unit' => Product::UNIT_KG,
            'unitcost' => '200.00',
            'total' => '500.00',
        ]);

        $page = $this->actingAs($this->user)->get(route('reports.purchases.product', ['date_filter' => 'today']));
        $page->assertOk();
        $page->assertSee('2.500 kg');
        $page->assertDontSee('2 kg');
    }

    public function test_return_quantities_stay_decimal(): void
    {
        $sugar = $this->makeProduct(['product_name' => 'Sugar', 'unit' => Product::UNIT_KG]);
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'order_date' => now(),
            'order_status' => 'complete',
            'total_products' => 1,
            'sub_total' => '187.50',
            'invoice_no' => 'INV-P7-R',
            'total' => '187.50',
            'pay' => '187.50',
            'due' => '0',
        ]);
        $orderDetail = OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $sugar->id,
            'quantity' => '0.750',
            'unit' => Product::UNIT_KG,
            'unitcost' => '250.00',
            'total' => '187.50',
        ]);
        $saleReturn = SaleReturn::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'return_date' => now(),
            'return_status' => 'complete',
            'return_no' => 'SR-P7-1',
            'total_products' => 1,
            'sub_total' => '187.50',
            'total' => '187.50',
        ]);
        $saleDetail = SaleReturnDetail::create([
            'return_id' => $saleReturn->id,
            'order_id' => $order->id,
            'order_detail_id' => $orderDetail->id,
            'product_id' => $sugar->id,
            'quantity' => '0.750',
            'unit' => Product::UNIT_KG,
            'unitcost' => '250.00',
            'total' => '187.50',
        ]);
        $this->assertSame('0.750 kg', $saleDetail->quantityWithUnit());

        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'purchase_date' => now(),
            'purchase_status' => 'received',
            'total_products' => 1,
            'sub_total' => '500.00',
            'purchase_no' => 'PO-P7-R',
            'total' => '500.00',
            'pay' => '0',
            'due' => '500.00',
        ]);
        $purchaseReturn = PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'shop_id' => $this->shop->id,
            'return_no' => 'PR-P7-1',
            'return_date' => now(),
            'total_products' => 1,
            'sub_total' => '500.00',
            'total' => '500.00',
            'status' => 'approved',
        ]);
        $purchaseDetail = PurchaseReturnDetail::create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $sugar->id,
            'quantity' => '2.500',
            'unit' => Product::UNIT_KG,
            'price' => '200.00',
            'total' => '500.00',
        ]);
        $this->assertSame('2.500 kg', $purchaseDetail->quantityWithUnit());

        $show = $this->actingAs($this->user)->get(route('sale-returns.show', $saleReturn->id));
        $show->assertOk();
        $show->assertSee('0.750 kg');
    }

    public function test_stock_movement_signed_qty_is_distinct_from_balance_and_export_keeps_decimals(): void
    {
        $sugar = $this->makeProduct([
            'product_name' => 'Sugar',
            'unit' => Product::UNIT_KG,
            'product_store' => '0.000',
        ]);

        StockLog::create([
            'shop_id' => $this->shop->id,
            'product_id' => $sugar->id,
            'supplier_id' => $this->supplier->id,
            'qty' => '0.750',
            'unit' => Product::UNIT_KG,
            'stock_qty' => '0.750',
            'direction' => 'in',
            'source_type' => 'purchase',
            'price' => '200.00',
            'adjustment_date' => now()->subMinute(),
        ]);
        StockLog::create([
            'shop_id' => $this->shop->id,
            'product_id' => $sugar->id,
            'supplier_id' => $this->supplier->id,
            'qty' => '0.750',
            'unit' => Product::UNIT_KG,
            'stock_qty' => '-0.750',
            'direction' => 'out',
            'source_type' => 'sale',
            'price' => '250.00',
            'adjustment_date' => now(),
        ]);

        $report = app(StockMovementReportService::class)->getReport([
            'product_id' => $sugar->id,
            'shop_id' => $this->shop->id,
            'sort' => 'date',
            'order' => 'asc',
        ]);

        $this->assertCount(2, $report['data']);
        $this->assertSame('0.750', $report['data'][0]['qty_in']);
        $this->assertSame('0.750', $report['data'][0]['signed_qty']);
        $this->assertSame('0.750', $report['data'][0]['balance']);
        $this->assertSame('kg', $report['data'][0]['unit']);
        $this->assertSame('0.750', $report['data'][1]['qty_out']);
        $this->assertSame('-0.750', $report['data'][1]['signed_qty']);
        $this->assertSame('0.000', $report['data'][1]['balance']);
        $this->assertNotSame($report['data'][1]['signed_qty'], $report['data'][1]['balance']);

        $csv = $this->actingAs($this->user)->get(route('reports.inventory.stock-movement', [
            'export' => 'csv',
            'format' => 'csv',
            'date_filter' => 'all',
            'product_id' => $sugar->id,
        ]));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('0.750', $content);
        $this->assertStringContainsString('kg', $content);
        $this->assertStringNotContainsString('0.750,0,0', $content);
    }

    public function test_valuation_filter_and_value_keep_kg_precision(): void
    {
        $this->makeProduct([
            'product_name' => 'Sugar',
            'unit' => Product::UNIT_KG,
            'product_store' => '2.500',
            'buying_price' => '210.0000',
            'selling_price' => '250.00',
        ]);
        $this->makeProduct([
            'product_name' => 'Soap',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
            'buying_price' => '80.0000',
            'selling_price' => '120.00',
        ]);

        $report = app(StockValuationReportService::class)->getReport([
            'shop_id' => $this->shop->id,
            'min_quantity' => '0.750',
        ]);

        $sugar = collect($report['data'])->firstWhere('product_name', 'Sugar');
        $soap = collect($report['data'])->firstWhere('product_name', 'Soap');
        $this->assertNotNull($sugar);
        $this->assertSame('2.500', $sugar['quantity']);
        $this->assertSame('kg', $sugar['unit']);
        $this->assertSame('525.00', $sugar['stock_cost_value']);
        $this->assertNotNull($soap);
        $this->assertSame('10', $soap['quantity']);
        $this->assertSame('piece', $soap['unit']);

        $below = app(StockValuationReportService::class)->getReport([
            'shop_id' => $this->shop->id,
            'max_quantity' => '0.500',
        ]);
        $this->assertNull(collect($below['data'])->firstWhere('product_name', 'Sugar'));
    }

    public function test_piece_product_report_does_not_look_decimal(): void
    {
        $this->makeProduct([
            'product_name' => 'Soap',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '25',
            'selling_price' => '120.00',
        ]);

        $page = $this->actingAs($this->user)->get(route('reports.inventory.stock'));
        $page->assertOk();
        $page->assertSee('25');
        $page->assertSee('piece');
        $page->assertDontSee('25.000');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => 'Phase 7 Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_code' => 'P7-'.(7000 + Product::withoutGlobalScopes()->count()),
            'product_store' => '0',
            'reserved_stock' => '0',
            'low_stock_warning' => '1',
            'buying_price' => '10.0000',
            'selling_price' => '20.00',
            'status' => 'active',
            'unit' => Product::UNIT_PIECE,
        ], $overrides));
    }
}
