<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores plain-text password for display on user list (when created or changed).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('actual_password', 255)->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('actual_password');
        });
    }
};
