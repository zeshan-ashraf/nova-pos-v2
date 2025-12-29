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
        // Create the sale-returns.menu permission
        $permission = Permission::firstOrCreate(
            [
                'name' => 'sale-returns.menu',
                'guard_name' => 'web'
            ],
            [
                'group_name' => 'sale_returns'
            ]
        );

        // Assign to SuperAdmin role
        $superAdminRole = Role::where('name', 'SuperAdmin')->where('guard_name', 'web')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('sale-returns.menu')) {
            $superAdminRole->givePermissionTo('sale-returns.menu');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'sale-returns.menu')
            ->where('guard_name', 'web')
            ->first();
        if ($permission) {
            // Remove from all roles
            $roles = Role::whereHas('permissions', function($query) {
                $query->where('name', 'sale-returns.menu')
                      ->where('guard_name', 'web');
            })->get();
            
            foreach ($roles as $role) {
                $role->revokePermissionTo('sale-returns.menu', 'web');
            }
            
            $permission->delete();
        }
    }
};
