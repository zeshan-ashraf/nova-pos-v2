<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['name' => 'purchase_returns.create', 'group_name' => 'purchase_returns'],
            ['name' => 'purchase_returns.view', 'group_name' => 'purchase_returns'],
            ['name' => 'transfer_returns.approve', 'group_name' => 'transfer_returns'],
            ['name' => 'transfer_returns.reject', 'group_name' => 'transfer_returns'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                ['group_name' => $permission['group_name']]
            );
        }

        // Assign all new permissions to SuperAdmin by default
        $superAdminRole = Role::where('name', 'SuperAdmin')->where('guard_name', 'web')->first();
        if ($superAdminRole) {
            foreach ($permissions as $permission) {
                if (!$superAdminRole->hasPermissionTo($permission['name'])) {
                    $superAdminRole->givePermissionTo($permission['name']);
                }
            }
        }
    }

    public function down(): void
    {
        $names = [
            'purchase_returns.create',
            'purchase_returns.view',
            'transfer_returns.approve',
            'transfer_returns.reject',
        ];

        foreach ($names as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($permission) {
                $roles = Role::whereHas('permissions', function ($query) use ($name) {
                    $query->where('name', $name)->where('guard_name', 'web');
                })->get();

                foreach ($roles as $role) {
                    $role->revokePermissionTo($name, 'web');
                }

                $permission->delete();
            }
        }
    }
};

