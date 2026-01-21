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
        // Update permission name and group_name
        $permission = Permission::where('name', 'activity.menu')
            ->where('group_name', 'activities')
            ->first();
        
        if ($permission) {
            $permission->name = 'expense.menu';
            $permission->group_name = 'expenses';
            $permission->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert permission name and group_name
        $permission = Permission::where('name', 'expense.menu')
            ->where('group_name', 'expenses')
            ->first();
        
        if ($permission) {
            $permission->name = 'activity.menu';
            $permission->group_name = 'activities';
            $permission->save();
        }
    }
};
