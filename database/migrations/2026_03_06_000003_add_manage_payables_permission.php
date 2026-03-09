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
        $permission = Permission::firstOrCreate([
            'name' => 'manage_payables',
            'group_name' => 'payables',
        ]);

        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('manage_payables')) {
            $superAdminRole->givePermissionTo('manage_payables');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'manage_payables')->first();
        if ($permission) {
            $roles = Role::whereHas('permissions', fn ($q) => $q->where('name', 'manage_payables'))->get();
            foreach ($roles as $role) {
                $role->revokePermissionTo('manage_payables');
            }
            $permission->delete();
        }
    }
};
