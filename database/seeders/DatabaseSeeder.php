<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Haruncpi\LaravelIdGenerator\IdGenerator;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ShopSeeder::class);

        $shop = \App\Models\Shop::where('is_parent', true)->orderBy('id')->first()
            ?? \App\Models\Shop::orderBy('id')->first();

        $password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // password

        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => $password,
                'shop_id' => $shop?->id,
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'name' => 'User',
                'username' => 'user',
                'password' => $password,
                'shop_id' => $shop?->id,
            ]
        );

        // Ensure existing seeded users are linked to a shop
        if ($shop) {
            if (!$admin->shop_id) {
                $admin->update(['shop_id' => $shop->id]);
            }
            if (!$user->shop_id) {
                $user->update(['shop_id' => $shop->id]);
            }
        }

        // Demo data only on first seed (admin was just created)
        if ($admin->wasRecentlyCreated) {
            Employee::factory(5)->create();
            Customer::factory(25)->create();
            Supplier::factory(10)->create();

            for ($i = 0; $i < 10; $i++) {
                Product::factory()->create([
                    'product_code' => IdGenerator::generate([
                        'table' => 'products',
                        'field' => 'product_code',
                        'length' => 4,
                        'prefix' => 'PC'
                    ])
                ]);
            }
            Category::factory(5)->create();
        }

        $permissions = [
            ['name' => 'pos.menu', 'group_name' => 'pos'],
            ['name' => 'advance.pos.menu', 'group_name' => 'advance_pos'],
            ['name' => 'employee.menu', 'group_name' => 'employee'],
            ['name' => 'customer.menu', 'group_name' => 'customer'],
            ['name' => 'supplier.menu', 'group_name' => 'supplier'],
            ['name' => 'salary.menu', 'group_name' => 'salary'],
            ['name' => 'attendence.menu', 'group_name' => 'attendence'],
            ['name' => 'category.menu', 'group_name' => 'category'],
            ['name' => 'product.menu', 'group_name' => 'product'],
            ['name' => 'orders.menu', 'group_name' => 'orders'],
            ['name' => 'expense.menu', 'group_name' => 'expenses'],
            ['name' => 'stock.menu', 'group_name' => 'stock'],
            ['name' => 'roles.menu', 'group_name' => 'roles'],
            ['name' => 'user.menu', 'group_name' => 'user'],
            ['name' => 'database.menu', 'group_name' => 'database'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['group_name' => $permission['group_name']]
            );
        }

        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(['customer.menu', 'user.menu', 'supplier.menu']);

        $accountRole = Role::firstOrCreate(['name' => 'Account', 'guard_name' => 'web']);
        $accountRole->givePermissionTo(['customer.menu', 'user.menu', 'supplier.menu', 'expense.menu']);

        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo(['stock.menu', 'orders.menu', 'product.menu', 'salary.menu', 'employee.menu']);

        $admin->assignRole('SuperAdmin');
        $user->assignRole('Account');

        $this->call(BankSeeder::class);
        $this->call(ExpenseSeeder::class);
    }
}
