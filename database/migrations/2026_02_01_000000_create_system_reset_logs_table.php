<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores audit logs for system reset operations.
     * CRITICAL: This table must NOT be included in the system reset deletion plan.
     */
    public function up(): void
    {
        Schema::create('system_reset_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('triggered_by_user_id');
            $table->string('triggered_by_email');
            $table->string('ip_address', 45)->nullable();
            $table->enum('result', ['success', 'failed']);
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            // Indexes for better query performance
            $table->index('created_at');
            $table->index('result');
            $table->index('triggered_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_reset_logs');
    }
};
