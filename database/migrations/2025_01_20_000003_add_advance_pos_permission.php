<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the advance.pos.menu permission
        $permission = Permission::firstOrCreate(
            ['name' => 'advance.pos.menu'],
            ['group_name' => 'advance_pos']
        );

        // Assign to SuperAdmin role
        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('advance.pos.menu')) {
            $superAdminRole->givePermissionTo('advance.pos.menu');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::where('name', 'advance.pos.menu')->first();
        if ($permission) {
            // Remove from all roles
            $roles = Role::whereHas('permissions', function($query) {
                $query->where('name', 'advance.pos.menu');
            })->get();
            
            foreach ($roles as $role) {
                $role->revokePermissionTo('advance.pos.menu');
            }
            
            $permission->delete();
        }
    }
};
