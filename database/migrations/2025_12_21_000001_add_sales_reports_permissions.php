<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the sales reports permissions
        $permissions = [
            ['name' => 'reports.sales', 'group_name' => 'reports_sales'],
            ['name' => 'reports.sales-summary', 'group_name' => 'reports_sales'],
            ['name' => 'reports.daily-sales', 'group_name' => 'reports_sales'],
            ['name' => 'reports.customer-sales', 'group_name' => 'reports_sales'],
            ['name' => 'reports.product-sales', 'group_name' => 'reports_sales'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }

        // Assign to SuperAdmin role
        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole) {
            foreach ($permissions as $permission) {
                if (!$superAdminRole->hasPermissionTo($permission['name'])) {
                    $superAdminRole->givePermissionTo($permission['name']);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionNames = [
            'reports.sales',
            'reports.sales-summary',
            'reports.daily-sales',
            'reports.customer-sales',
            'reports.product-sales',
        ];
        
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                // Remove from all roles
                $roles = Role::whereHas('permissions', function($query) use ($permissionName) {
                    $query->where('name', $permissionName);
                })->get();
                
                foreach ($roles as $role) {
                    $role->revokePermissionTo($permissionName);
                }
                
                $permission->delete();
            }
        }
    }
};
