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
        // Create the inventory reports permissions
        $permissions = [
            ['name' => 'reports.inventory', 'group_name' => 'reports_inventory'],
            ['name' => 'reports.stock', 'group_name' => 'reports_inventory'],
            ['name' => 'reports.stock-movement', 'group_name' => 'reports_inventory'],
            ['name' => 'reports.stock-valuation', 'group_name' => 'reports_inventory'],
            ['name' => 'reports.expired-products', 'group_name' => 'reports_inventory'],
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
            'reports.inventory',
            'reports.stock',
            'reports.stock-movement',
            'reports.stock-valuation',
            'reports.expired-products',
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
