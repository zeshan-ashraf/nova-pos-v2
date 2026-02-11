<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'expense-categories.menu'],
            ['group_name' => 'expenses']
        );

        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('expense-categories.menu')) {
            $superAdminRole->givePermissionTo('expense-categories.menu');
        }
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'expense-categories.menu')->first();
        if ($permission) {
            $roles = Role::whereHas('permissions', function ($query) {
                $query->where('name', 'expense-categories.menu');
            })->get();
            foreach ($roles as $role) {
                $role->revokePermissionTo('expense-categories.menu');
            }
            $permission->delete();
        }
    }
};
