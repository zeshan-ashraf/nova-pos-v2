<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => 'transfer_returns.view', 'guard_name' => 'web'],
            ['group_name' => 'transfer_returns']
        );

        // Keep SuperAdmin access by default
        $superAdminRole = Role::where('name', 'SuperAdmin')->where('guard_name', 'web')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('transfer_returns.view')) {
            $superAdminRole->givePermissionTo('transfer_returns.view');
        }
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'transfer_returns.view')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            $roles = Role::whereHas('permissions', function ($query) {
                $query->where('name', 'transfer_returns.view')->where('guard_name', 'web');
            })->get();

            foreach ($roles as $role) {
                $role->revokePermissionTo('transfer_returns.view', 'web');
            }

            $permission->delete();
        }
    }
};

