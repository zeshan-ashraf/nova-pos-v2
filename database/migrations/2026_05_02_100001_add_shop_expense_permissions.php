<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'shop_expense.view',
            'shop_expense.create',
            'shop_expense.edit',
            'shop_expense.delete',
            'shop_expense.export',
        ];

        foreach ($names as $name) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['group_name' => 'shop_expenses']
            );
        }

        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($names);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = [
            'shop_expense.view',
            'shop_expense.create',
            'shop_expense.edit',
            'shop_expense.delete',
            'shop_expense.export',
        ];

        foreach ($names as $name) {
            $permission = Permission::where('name', $name)->first();
            if (!$permission) {
                continue;
            }
            $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', $name))->get();
            foreach ($roles as $role) {
                $role->revokePermissionTo($name);
            }
            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
