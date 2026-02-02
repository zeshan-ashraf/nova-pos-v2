<?php

namespace App\Services\SystemReset;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * System Reset Service
 * 
 * Orchestrates the complete system reset process while preserving the admin user.
 * This service ensures all operations happen within a transaction and follows
 * strict foreign key constraints without disabling them.
 */
class SystemResetService
{
    private ResetValidator $validator;
    private ResetPlan $resetPlan;
    private ResetLogger $logger;
    private ?User $preservedAdmin = null;

    public function __construct(
        ResetValidator $validator,
        ResetPlan $resetPlan,
        ResetLogger $logger
    ) {
        $this->validator = $validator;
        $this->resetPlan = $resetPlan;
        $this->logger = $logger;
    }

    /**
     * Execute the complete system reset
     * 
     * @param User $triggeredBy The user requesting the reset
     * @param string $password Password confirmation
     * @param string $confirmationText Must be "RESET SYSTEM"
     * @param string|null $ipAddress IP address of the requester
     * @return array Result with success status and message
     */
    public function execute(
        User $triggeredBy,
        string $password,
        string $confirmationText,
        ?string $ipAddress = null
    ): array {
        $startTime = microtime(true);
        
        try {
            // Step 1: Validate the reset request
            $this->validator->validate($triggeredBy, $password, $confirmationText);
            
            // Step 2: Identify and cache the admin user to preserve
            $this->preservedAdmin = $this->validator->getPreservedAdmin();
            
            Log::info('System reset initiated', [
                'triggered_by' => $triggeredBy->id,
                'triggered_by_email' => $triggeredBy->email,
                'preserved_admin_id' => $this->preservedAdmin->id,
                'ip_address' => $ipAddress,
            ]);

            // Step 3: Enable maintenance mode
            $this->enableMaintenanceMode();

            // Step 4: Execute the reset within a transaction
            DB::beginTransaction();

            try {
                // Step 5: Execute deletions in FK-safe order
                $this->executeDeletions();

                // Step 6: Reset auto-increment IDs where safe
                $this->resetAutoIncrements();

                // Step 7: Recreate base system data
                $this->recreateBaseData();

                // Step 8: Ensure admin has no shop assignment
                $this->ensureAdminShopIdNull();

                // Commit the transaction
                DB::commit();

                // Step 9: Disable maintenance mode
                $this->disableMaintenanceMode();

                $duration = microtime(true) - $startTime;

                // Log success
                $this->logger->logSuccess(
                    $triggeredBy->id,
                    $triggeredBy->email,
                    $ipAddress,
                    $duration
                );

                Log::info('System reset completed successfully', [
                    'duration_seconds' => $duration,
                ]);

                return [
                    'success' => true,
                    'message' => 'System has been reset successfully. All data has been cleared except the admin user.',
                ];

            } catch (Exception $e) {
                // Rollback transaction on any error
                DB::rollBack();
                
                // Ensure maintenance mode is disabled even on failure
                $this->disableMaintenanceMode();

                throw $e;
            }

        } catch (Exception $e) {
            // Log failure
            $this->logger->logFailure(
                $triggeredBy->id,
                $triggeredBy->email,
                $ipAddress,
                $e->getMessage()
            );

            Log::error('System reset failed', [
                'triggered_by' => $triggeredBy->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'System reset failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Execute deletions in strict FK-safe order
     * All child records must be deleted before parent records
     */
    private function executeDeletions(): void
    {
        Log::info('Starting deletions in FK-safe order');

        // Get the deletion plan (ordered list of tables and their delete strategies)
        $deletionSteps = $this->resetPlan->getDeletionPlan($this->preservedAdmin->id);

        foreach ($deletionSteps as $step) {
            $tableName = $step['table'];
            $excludeCondition = $step['exclude'] ?? null;

            Log::info("Deleting from: {$tableName}", [
                'exclude_condition' => $excludeCondition,
            ]);

            // Execute deletion with optional exclusion
            if ($excludeCondition) {
                // Delete all except excluded records
                DB::table($tableName)
                    ->whereRaw($excludeCondition)
                    ->delete();
            } else {
                // Delete all records
                DB::table($tableName)->delete();
            }

            $remainingCount = DB::table($tableName)->count();
            Log::info("Deleted from {$tableName}", [
                'remaining_records' => $remainingCount,
            ]);
        }

        Log::info('All deletions completed');
    }

    /**
     * Reset auto-increment IDs where safe
     * Only reset tables that are completely empty or preserve specific records
     */
    private function resetAutoIncrements(): void
    {
        Log::info('Resetting auto-increment IDs');

        $tablesToReset = $this->resetPlan->getAutoIncrementResetTables();

        foreach ($tablesToReset as $table) {
            $count = DB::table($table)->count();
            
            // Only reset if table is empty or we're preserving specific records
            if ($table === 'users') {
                // For users table, set next ID after the preserved admin
                $maxId = $this->preservedAdmin->id;
                DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($maxId + 1));
                Log::info("Reset auto-increment for {$table}", ['next_id' => $maxId + 1]);
            } elseif ($count === 0) {
                // For empty tables, reset to 1
                DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                Log::info("Reset auto-increment for {$table}", ['next_id' => 1]);
            }
        }

        Log::info('Auto-increment reset completed');
    }

    /**
     * Recreate base system data required for operation
     * This includes default units, taxes, settings, etc.
     */
    private function recreateBaseData(): void
    {
        Log::info('Recreating base system data');

        $baseDataOperations = $this->resetPlan->getBaseDataRecreationSteps();

        foreach ($baseDataOperations as $operation) {
            Log::info("Executing base data recreation: {$operation['description']}");
            
            // Execute the closure that recreates the base data
            $operation['execute']();
        }

        Log::info('Base system data recreation completed');
    }

    /**
     * Ensure the preserved admin user has shop_id = NULL
     * This is critical to prevent orphaned relationships
     */
    private function ensureAdminShopIdNull(): void
    {
        Log::info('Ensuring admin shop_id is NULL', [
            'admin_id' => $this->preservedAdmin->id,
        ]);

        DB::table('users')
            ->where('id', $this->preservedAdmin->id)
            ->update(['shop_id' => null]);

        Log::info('Admin shop_id set to NULL');
    }

    /**
     * Enable Laravel maintenance mode
     */
    private function enableMaintenanceMode(): void
    {
        try {
            Artisan::call('down', [
                '--secret' => config('app.maintenance_secret', 'system-reset-in-progress'),
            ]);
            Log::info('Maintenance mode enabled');
        } catch (Exception $e) {
            Log::warning('Could not enable maintenance mode', [
                'error' => $e->getMessage(),
            ]);
            // Don't fail the reset if maintenance mode fails
        }
    }

    /**
     * Disable Laravel maintenance mode
     */
    private function disableMaintenanceMode(): void
    {
        try {
            Artisan::call('up');
            Log::info('Maintenance mode disabled');
        } catch (Exception $e) {
            Log::warning('Could not disable maintenance mode', [
                'error' => $e->getMessage(),
            ]);
            // Don't fail the reset if maintenance mode fails
        }
    }
}
