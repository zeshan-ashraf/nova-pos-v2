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
        // Create the reports.menu permission
        $permission = Permission::firstOrCreate(
            ['name' => 'reports.menu'],
            ['group_name' => 'reports']
        );

        // Assign to SuperAdmin role
        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('reports.menu')) {
            $superAdminRole->givePermissionTo('reports.menu');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'reports.menu')->first();
        if ($permission) {
            // Remove from all roles
            $roles = Role::whereHas('permissions', function($query) {
                $query->where('name', 'reports.menu');
            })->get();
            
            foreach ($roles as $role) {
                $role->revokePermissionTo('reports.menu');
            }
            
            $permission->delete();
        }
    }
};
