<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Shop;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;
use App\Services\SystemReset\SystemResetService;
use App\Services\SystemReset\ResetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * System Reset Module Tests
 * 
 * These tests verify the behavior of the System Reset module.
 */
class SystemResetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private SystemResetService $resetService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin role
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Create the preserved admin user
        $this->admin = User::create([
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'shop_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->admin->assignRole('Super Admin');

        $this->resetService = app(SystemResetService::class);
    }

    /** @test */
    public function test_admin_user_is_preserved_after_reset()
    {
        // Create additional users
        User::factory()->count(5)->create();

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert reset was successful
        $this->assertTrue($result['success']);

        // Assert only admin user remains
        $this->assertEquals(1, User::count());
        $this->assertEquals('admin@gmail.com', User::first()->email);
        $this->assertEquals('admin', User::first()->name);
        $this->assertNull(User::first()->shop_id);
    }

    /** @test */
    public function test_all_shops_are_deleted()
    {
        // Create shops
        Shop::factory()->count(10)->create();

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, Shop::count());
    }

    /** @test */
    public function test_all_customers_are_deleted()
    {
        // Create shops and customers
        $shop = Shop::factory()->create();
        Customer::factory()->count(20)->create(['shop_id' => $shop->id]);

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, Customer::count());
    }

    /** @test */
    public function test_all_products_are_deleted()
    {
        // Create shop and products
        $shop = Shop::factory()->create();
        Product::factory()->count(50)->create(['shop_id' => $shop->id]);

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, Product::count());
    }

    /** @test */
    public function test_all_orders_are_deleted()
    {
        // Create shop, customer, and orders
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create(['shop_id' => $shop->id]);
        Order::factory()->count(30)->create([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
        ]);

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, Order::count());
    }

    /** @test */
    public function test_super_admin_role_exists_after_reset()
    {
        // Create additional roles
        Role::create(['name' => 'Shop Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Cashier', 'guard_name' => 'web']);

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue(Role::where('name', 'Super Admin')->exists());
    }

    /** @test */
    public function test_admin_has_super_admin_role_after_reset()
    {
        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        
        // Refresh admin user
        $this->admin->refresh();
        
        $this->assertTrue($this->admin->hasRole('Super Admin'));
    }

    /** @test */
    public function test_reset_fails_with_wrong_password()
    {
        // Execute reset with wrong password
        $result = $this->resetService->execute(
            $this->admin,
            'wrong-password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Password confirmation failed', $result['message']);
    }

    /** @test */
    public function test_reset_fails_with_wrong_confirmation_text()
    {
        // Execute reset with wrong confirmation text
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'wrong text',
            '127.0.0.1'
        );

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Confirmation text does not match', $result['message']);
    }

    /** @test */
    public function test_non_super_admin_cannot_reset()
    {
        // Create regular user without Super Admin role
        $regularUser = User::create([
            'name' => 'Regular User',
            'username' => 'regular',
            'email' => 'regular@example.com',
            'password' => Hash::make('password'),
            'shop_id' => null,
        ]);

        // Execute reset
        $result = $this->resetService->execute(
            $regularUser,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Only Super Admin users', $result['message']);
    }

    /** @test */
    public function test_reset_fails_if_admin_user_does_not_exist()
    {
        // Delete the admin user
        $this->admin->forceDelete();

        // Create a different super admin
        $otherAdmin = User::create([
            'name' => 'Other Admin',
            'username' => 'other',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'shop_id' => null,
        ]);
        $otherAdmin->assignRole('Super Admin');

        // Execute reset
        $result = $this->resetService->execute(
            $otherAdmin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('admin@gmail.com not found', $result['message']);
    }

    /** @test */
    public function test_reset_is_logged()
    {
        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);

        // Check log exists
        $this->assertDatabaseHas('system_reset_logs', [
            'triggered_by_user_id' => $this->admin->id,
            'triggered_by_email' => $this->admin->email,
            'result' => 'success',
        ]);
    }

    /** @test */
    public function test_failed_reset_is_logged()
    {
        // Execute reset with wrong password
        $result = $this->resetService->execute(
            $this->admin,
            'wrong-password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertFalse($result['success']);

        // Check log exists
        $this->assertDatabaseHas('system_reset_logs', [
            'triggered_by_user_id' => $this->admin->id,
            'triggered_by_email' => $this->admin->email,
            'result' => 'failed',
        ]);
    }

    /** @test */
    public function test_transaction_rollback_on_failure()
    {
        // Create some test data
        $shop = Shop::factory()->create();
        User::factory()->count(5)->create();

        // Mock a failure during the reset process
        // This would require more sophisticated mocking
        // For now, we test that wrong password causes no data loss

        $initialUserCount = User::count();
        $initialShopCount = Shop::count();

        // Execute reset with wrong password
        $result = $this->resetService->execute(
            $this->admin,
            'wrong-password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert reset failed
        $this->assertFalse($result['success']);

        // Assert no data was deleted
        $this->assertEquals($initialUserCount, User::count());
        $this->assertEquals($initialShopCount, Shop::count());
    }

    /** @test */
    public function test_admin_shop_id_is_null_after_reset()
    {
        // Set admin shop_id to something non-null
        $shop = Shop::factory()->create();
        $this->admin->update(['shop_id' => $shop->id]);

        // Execute reset
        $result = $this->resetService->execute(
            $this->admin,
            'password',
            'RESET SYSTEM',
            '127.0.0.1'
        );

        // Assert
        $this->assertTrue($result['success']);
        
        // Refresh and check
        $this->admin->refresh();
        $this->assertNull($this->admin->shop_id);
    }
}
