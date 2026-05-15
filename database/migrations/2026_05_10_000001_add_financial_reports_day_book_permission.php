<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'financial_reports.day_book', 'guard_name' => 'web'],
            ['group_name' => 'reports_financial']
        );

        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo($permission->name)) {
            $superAdminRole->givePermissionTo($permission->name);
        }
    }

    public function down(): void
    {
        $name = 'financial_reports.day_book';
        $permission = Permission::where('name', $name)->first();
        if (!$permission) {
            return;
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', $name))->get();
        foreach ($roles as $role) {
            $role->revokePermissionTo($name);
        }
        $permission->delete();
    }
};
