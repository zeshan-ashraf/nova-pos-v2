<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            ['name' => 'shop.menu', 'group_name' => 'shop'],
            ['name' => 'shop.create', 'group_name' => 'shop'],
            ['name' => 'shop.update', 'group_name' => 'shop'],
            ['name' => 'shop.delete', 'group_name' => 'shop'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', [
            'shop.menu',
            'shop.create',
            'shop.update',
            'shop.delete',
        ])->delete();
    }
};


