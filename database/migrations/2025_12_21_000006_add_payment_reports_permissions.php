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
        // Create the payment reports permissions
        $permissions = [
            ['name' => 'reports.payment', 'group_name' => 'reports_payment'],
            ['name' => 'reports.payment-collection', 'group_name' => 'reports_payment'],
            ['name' => 'reports.payment-disbursement', 'group_name' => 'reports_payment'],
            ['name' => 'reports.payment-summary', 'group_name' => 'reports_payment'],
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
            'reports.payment',
            'reports.payment-collection',
            'reports.payment-disbursement',
            'reports.payment-summary',
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
