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
        // Create the purchase reports permissions
        $permissions = [
            ['name' => 'reports.purchases', 'group_name' => 'reports_purchases'],
            ['name' => 'reports.purchase-summary', 'group_name' => 'reports_purchases'],
            ['name' => 'reports.supplier-purchase', 'group_name' => 'reports_purchases'],
            ['name' => 'reports.product-purchase', 'group_name' => 'reports_purchases'],
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
            'reports.purchases',
            'reports.purchase-summary',
            'reports.supplier-purchase',
            'reports.product-purchase',
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
