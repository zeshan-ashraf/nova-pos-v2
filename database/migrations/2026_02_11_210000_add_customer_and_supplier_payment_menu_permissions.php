<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $customerPayment = Permission::firstOrCreate(
            ['name' => 'customer_payment.menu'],
            ['group_name' => 'payments']
        );

        $supplierPayment = Permission::firstOrCreate(
            ['name' => 'supplier_payment.menu'],
            ['group_name' => 'payments']
        );

        // Give customer_payment.menu to roles that have customer.menu
        $rolesWithCustomer = Role::whereHas('permissions', function ($query) {
            $query->where('name', 'customer.menu');
        })->get();
        foreach ($rolesWithCustomer as $role) {
            if (!$role->hasPermissionTo('customer_payment.menu')) {
                $role->givePermissionTo('customer_payment.menu');
            }
        }

        // Give supplier_payment.menu to roles that have supplier.menu
        $rolesWithSupplier = Role::whereHas('permissions', function ($query) {
            $query->where('name', 'supplier.menu');
        })->get();
        foreach ($rolesWithSupplier as $role) {
            if (!$role->hasPermissionTo('supplier_payment.menu')) {
                $role->givePermissionTo('supplier_payment.menu');
            }
        }

        // SuperAdmin gets all permissions (if not already)
        $superAdmin = Role::where('name', 'SuperAdmin')->first();
        if ($superAdmin) {
            if (!$superAdmin->hasPermissionTo('customer_payment.menu')) {
                $superAdmin->givePermissionTo('customer_payment.menu');
            }
            if (!$superAdmin->hasPermissionTo('supplier_payment.menu')) {
                $superAdmin->givePermissionTo('supplier_payment.menu');
            }
        }
    }

    public function down(): void
    {
        foreach (['customer_payment.menu', 'supplier_payment.menu'] as $name) {
            $permission = Permission::where('name', $name)->first();
            if ($permission) {
                $roles = Role::whereHas('permissions', function ($query) use ($name) {
                    $query->where('name', $name);
                })->get();
                foreach ($roles as $role) {
                    $role->revokePermissionTo($name);
                }
                $permission->delete();
            }
        }
    }
};
