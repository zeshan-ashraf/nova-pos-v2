<?php

namespace App\Services\SystemReset;

use Illuminate\Support\Facades\DB;

/**
 * Reset Plan
 * 
 * Defines the exact order and strategy for deleting records during system reset.
 * This class ensures FK-safe deletions by respecting child→parent relationships.
 * 
 * CRITICAL: The order of deletions MUST follow foreign key dependencies.
 * Always delete child records before parent records.
 */
class ResetPlan
{
    /**
     * Get the deletion plan with exact order
     * 
     * Returns an array of deletion steps, each containing:
     * - table: The table name
     * - exclude: Optional SQL WHERE condition to exclude specific records
     * 
     * @param int $preservedAdminId The admin user ID to preserve
     * @return array
     */
    public function getDeletionPlan(int $preservedAdminId): array
    {
        return [
            // Level 1: Delete most deeply nested children first
            
            // Sale return details (child of sale_returns)
            ['table' => 'sale_return_details'],
            
            // Order details (child of orders)
            ['table' => 'order_details'],
            
            // Purchase details (child of purchases)
            ['table' => 'purchase_details'],
            
            // Payment logs for sales (child of orders)
            ['table' => 'payment_logs'],
            
            // Payment logs for purchases (child of purchases)
            ['table' => 'purchase_payment_logs'],
            
            // Stock logs (references products)
            ['table' => 'stock_logs'],
            
            // Level 2: Delete intermediate children
            
            // Sale returns (references orders)
            ['table' => 'sale_returns'],
            
            // Orders (references customers and shops)
            ['table' => 'orders'],
            
            // Purchases (references suppliers and shops)
            ['table' => 'purchases'],
            
            // Expenses/Activities (references customers and shops)
            ['table' => 'activities'],
            
            // Level 3: Delete employee-related data
            
            // Pay salaries (references employees)
            ['table' => 'pay_salaries'],
            
            // Advance salaries (references employees)
            ['table' => 'advance_salaries'],
            
            // Attendances (references employees)
            ['table' => 'attendences'],
            
            // Employees (references shops)
            ['table' => 'employees'],
            
            // Level 4: Delete product and inventory data
            
            // Products (references categories, suppliers, shops)
            ['table' => 'products'],
            
            // Categories
            ['table' => 'categories'],
            
            // Level 5: Delete customers and suppliers
            
            // Customers (references shops)
            ['table' => 'customers'],
            
            // Suppliers (references shops)
            ['table' => 'suppliers'],
            
            // Level 6: Delete shop-related data
            
            // Bank-Shop pivot table
            ['table' => 'bank_shop'],
            
            // Banks
            ['table' => 'banks'],
            
            // Shops (parent and child shops)
            ['table' => 'shops'],
            
            // Level 7: Delete permission and role data
            
            // Model has permissions (Spatie)
            ['table' => 'model_has_permissions'],
            
            // Model has roles (Spatie)
            ['table' => 'model_has_roles'],
            
            // Role has permissions (Spatie)
            ['table' => 'role_has_permissions'],
            
            // Permissions (Spatie)
            ['table' => 'permissions'],
            
            // Roles (Spatie)
            ['table' => 'roles'],
            
            // Level 8: Delete users (EXCEPT the preserved admin)
            [
                'table' => 'users',
                'exclude' => "id != {$preservedAdminId}",
            ],
            
            // Level 9: Delete session and token data
            
            // Personal access tokens (Sanctum)
            ['table' => 'personal_access_tokens'],
            
            // Password reset tokens
            ['table' => 'password_reset_tokens'],
            
            // Sessions (if stored in database)
            // Note: Only delete if sessions table exists
            // ['table' => 'sessions'],
            
            // Level 10: Delete cached permissions
            // Spatie permission cache is handled separately
        ];
    }

    /**
     * Get list of tables that should have their auto-increment reset
     * 
     * @return array
     */
    public function getAutoIncrementResetTables(): array
    {
        return [
            'users',                    // Reset to next ID after preserved admin
            'shops',
            'categories',
            'products',
            'customers',
            'suppliers',
            'orders',
            'order_details',
            'purchases',
            'purchase_details',
            'sale_returns',
            'sale_return_details',
            'payment_logs',
            'purchase_payment_logs',
            'stock_logs',
            'activities',
            'employees',
            'advance_salaries',
            'pay_salaries',
            'attendences',
            'banks',
        ];
    }

    /**
     * Get base data recreation steps
     * 
     * Returns an array of operations to recreate essential base data.
     * Each operation has a description and an execute closure.
     * 
     * @return array
     */
    public function getBaseDataRecreationSteps(): array
    {
        return [
            [
                'description' => 'Create Super Admin role',
                'execute' => function () {
                    $this->recreateSuperAdminRole();
                },
            ],
            [
                'description' => 'Assign Super Admin role to preserved admin',
                'execute' => function () {
                    $this->assignSuperAdminRoleToAdmin();
                },
            ],
            [
                'description' => 'Create default permissions',
                'execute' => function () {
                    $this->recreateDefaultPermissions();
                },
            ],
            [
                'description' => 'Clear Spatie permission cache',
                'execute' => function () {
                    $this->clearPermissionCache();
                },
            ],
        ];
    }

    /**
     * Recreate the Super Admin role
     */
    private function recreateSuperAdminRole(): void
    {
        DB::table('roles')->insert([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Assign Super Admin role to the preserved admin user
     */
    private function assignSuperAdminRoleToAdmin(): void
    {
        $adminId = DB::table('users')
            ->where('email', 'admin@gmail.com')
            ->value('id');

        $roleId = DB::table('roles')
            ->where('name', 'Super Admin')
            ->value('id');

        if ($adminId && $roleId) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'App\\Models\\User',
                'model_id' => $adminId,
            ]);
        }
    }

    /**
     * Recreate default permissions required for basic operation
     * 
     * This creates a minimal set of permissions. Additional permissions
     * can be added through the application later.
     */
    private function recreateDefaultPermissions(): void
    {
        $defaultPermissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'group_name' => 'dashboard'],
            
            // Shops
            ['name' => 'shop.view', 'group_name' => 'shop'],
            ['name' => 'shop.create', 'group_name' => 'shop'],
            ['name' => 'shop.edit', 'group_name' => 'shop'],
            ['name' => 'shop.delete', 'group_name' => 'shop'],
            
            // Users
            ['name' => 'user.view', 'group_name' => 'user'],
            ['name' => 'user.create', 'group_name' => 'user'],
            ['name' => 'user.edit', 'group_name' => 'user'],
            ['name' => 'user.delete', 'group_name' => 'user'],
            
            // Roles
            ['name' => 'role.view', 'group_name' => 'role'],
            ['name' => 'role.create', 'group_name' => 'role'],
            ['name' => 'role.edit', 'group_name' => 'role'],
            ['name' => 'role.delete', 'group_name' => 'role'],
            
            // Permissions
            ['name' => 'permission.view', 'group_name' => 'permission'],
            ['name' => 'permission.create', 'group_name' => 'permission'],
            ['name' => 'permission.edit', 'group_name' => 'permission'],
            ['name' => 'permission.delete', 'group_name' => 'permission'],
        ];

        foreach ($defaultPermissions as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission['name'],
                'group_name' => $permission['group_name'],
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign all permissions to Super Admin role
        $roleId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        $permissions = DB::table('permissions')->pluck('id');

        foreach ($permissions as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    /**
     * Clear Spatie permission cache
     */
    private function clearPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
