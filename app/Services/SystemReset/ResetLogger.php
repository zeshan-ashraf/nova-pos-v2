<?php

namespace App\Services\SystemReset;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reset Logger
 * 
 * Handles audit logging for system reset operations.
 * Logs are stored in a dedicated table that survives the reset process.
 * 
 * IMPORTANT: The system_reset_logs table must NOT be included in the deletion plan.
 */
class ResetLogger
{
    private const LOG_TABLE = 'system_reset_logs';

    /**
     * Ensure the logging table exists
     * Creates the table if it doesn't exist
     */
    public function ensureLogTableExists(): void
    {
        if (!$this->logTableExists()) {
            $this->createLogTable();
        }
    }

    /**
     * Log a successful reset operation
     * 
     * @param int $userId
     * @param string $email
     * @param string|null $ipAddress
     * @param float $duration Duration in seconds
     */
    public function logSuccess(
        int $userId,
        string $email,
        ?string $ipAddress,
        float $duration
    ): void {
        $this->ensureLogTableExists();

        DB::table(self::LOG_TABLE)->insert([
            'triggered_by_user_id' => $userId,
            'triggered_by_email' => $email,
            'ip_address' => $ipAddress,
            'result' => 'success',
            'duration_seconds' => round($duration, 2),
            'error_message' => null,
            'created_at' => now(),
        ]);

        Log::info('System reset success logged to database', [
            'user_id' => $userId,
            'duration' => $duration,
        ]);
    }

    /**
     * Log a failed reset operation
     * 
     * @param int $userId
     * @param string $email
     * @param string|null $ipAddress
     * @param string $errorMessage
     */
    public function logFailure(
        int $userId,
        string $email,
        ?string $ipAddress,
        string $errorMessage
    ): void {
        $this->ensureLogTableExists();

        DB::table(self::LOG_TABLE)->insert([
            'triggered_by_user_id' => $userId,
            'triggered_by_email' => $email,
            'ip_address' => $ipAddress,
            'result' => 'failed',
            'duration_seconds' => null,
            'error_message' => $errorMessage,
            'created_at' => now(),
        ]);

        Log::error('System reset failure logged to database', [
            'user_id' => $userId,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Get recent reset logs
     * 
     * @param int $limit
     * @return array
     */
    public function getRecentLogs(int $limit = 10): array
    {
        if (!$this->logTableExists()) {
            return [];
        }

        return DB::table(self::LOG_TABLE)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Check if log table exists
     * 
     * @return bool
     */
    private function logTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable(self::LOG_TABLE);
        } catch (\Exception $e) {
            Log::error('Error checking log table existence', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create the log table
     * 
     * This table is designed to survive system resets
     */
    private function createLogTable(): void
    {
        try {
            DB::statement("
                CREATE TABLE IF NOT EXISTS " . self::LOG_TABLE . " (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    triggered_by_user_id BIGINT UNSIGNED NOT NULL,
                    triggered_by_email VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    result ENUM('success', 'failed') NOT NULL,
                    duration_seconds DECIMAL(10, 2) NULL,
                    error_message TEXT NULL,
                    created_at TIMESTAMP NOT NULL,
                    INDEX idx_created_at (created_at),
                    INDEX idx_result (result),
                    INDEX idx_user (triggered_by_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            Log::info('System reset logs table created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create system reset logs table', [
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception - logging failure shouldn't break the reset
        }
    }
}
