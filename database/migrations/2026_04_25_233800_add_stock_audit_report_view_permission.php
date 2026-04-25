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
        Permission::firstOrCreate([
            'name' => 'stock_audit_report_view',
            'guard_name' => 'web',
            'group_name' => 'reports_inventory',
        ]);

        $superAdminRole = Role::where('name', 'SuperAdmin')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo('stock_audit_report_view')) {
            $superAdminRole->givePermissionTo('stock_audit_report_view');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::query()
            ->where('name', 'stock_audit_report_view')
            ->where('guard_name', 'web')
            ->delete();
    }
};

