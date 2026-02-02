<?php

namespace App\Services\SystemReset;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Reset Validator
 * 
 * Handles all security and safety validations before allowing a system reset.
 * Implements multiple layers of protection to prevent accidental or unauthorized resets.
 */
class ResetValidator
{
    private const ADMIN_EMAIL = 'admin@gmail.com';
    private const REQUIRED_CONFIRMATION_TEXT = 'RESET SYSTEM';
    private const LOCK_KEY = 'system_reset_in_progress';
    private const LOCK_DURATION = 3600; // 1 hour in seconds

    private ?User $preservedAdmin = null;

    /**
     * Validate the reset request
     * 
     * @param User $triggeredBy The user requesting the reset
     * @param string $password Password confirmation
     * @param string $confirmationText Must match REQUIRED_CONFIRMATION_TEXT exactly
     * @throws Exception if validation fails
     */
    public function validate(
        User $triggeredBy,
        string $password,
        string $confirmationText
    ): void {
        // Rule 1: Prevent concurrent resets
        $this->ensureNotRunningConcurrently();

        // Rule 2: Verify the user is a super admin
        $this->validateUserIsSuperAdmin($triggeredBy);

        // Rule 3: Verify password
        $this->validatePassword($triggeredBy, $password);

        // Rule 4: Verify confirmation text
        $this->validateConfirmationText($confirmationText);

        // Rule 5: Verify admin user exists
        $this->validateAdminUserExists();

        // Rule 6: Additional safety checks
        $this->performAdditionalSafetyChecks();

        // If all validations pass, acquire the lock
        $this->acquireLock();
    }

    /**
     * Get the preserved admin user
     * 
     * @return User
     * @throws Exception if admin not found
     */
    public function getPreservedAdmin(): User
    {
        if (!$this->preservedAdmin) {
            throw new Exception('Admin user not validated yet');
        }

        return $this->preservedAdmin;
    }

    /**
     * Ensure no other reset is currently running
     * 
     * @throws Exception if another reset is in progress
     */
    private function ensureNotRunningConcurrently(): void
    {
        if (Cache::has(self::LOCK_KEY)) {
            throw new Exception(
                'A system reset is already in progress. Please wait for it to complete.'
            );
        }
    }

    /**
     * Validate that the user has super admin privileges
     * 
     * @param User $user
     * @throws Exception if user is not super admin
     */
    private function validateUserIsSuperAdmin(User $user): void
    {
        // Check if user has Super Admin role
        if (!$user->hasRole('Super Admin')) {
            throw new Exception(
                'Access denied. Only Super Admin users can perform system reset.'
            );
        }

        // Additional check: user must not be associated with a shop
        if ($user->shop_id !== null) {
            throw new Exception(
                'Access denied. System reset can only be performed by the root admin user.'
            );
        }
    }

    /**
     * Validate the password matches the user's password
     * 
     * @param User $user
     * @param string $password
     * @throws Exception if password is incorrect
     */
    private function validatePassword(User $user, string $password): void
    {
        if (!Hash::check($password, $user->password)) {
            throw new Exception('Password confirmation failed. Incorrect password.');
        }
    }

    /**
     * Validate the confirmation text matches exactly
     * 
     * @param string $confirmationText
     * @throws Exception if confirmation text doesn't match
     */
    private function validateConfirmationText(string $confirmationText): void
    {
        if ($confirmationText !== self::REQUIRED_CONFIRMATION_TEXT) {
            throw new Exception(
                'Confirmation text does not match. You must type "' . 
                self::REQUIRED_CONFIRMATION_TEXT . '" exactly (without quotes).'
            );
        }
    }

    /**
     * Validate that the admin user exists and is properly configured
     * 
     * @throws Exception if admin user doesn't exist or is misconfigured
     */
    private function validateAdminUserExists(): void
    {
        $admin = User::where('email', self::ADMIN_EMAIL)->first();

        if (!$admin) {
            throw new Exception(
                'Admin user with email ' . self::ADMIN_EMAIL . ' not found. ' .
                'System reset cannot proceed without a valid admin user to preserve.'
            );
        }

        // Verify admin user name
        if ($admin->name !== 'admin') {
            throw new Exception(
                'Admin user found but name is not "admin". ' .
                'Expected name: "admin", Found: "' . $admin->name . '"'
            );
        }

        // Store the admin for later use
        $this->preservedAdmin = $admin;
    }

    /**
     * Perform additional safety checks
     * 
     * @throws Exception if safety checks fail
     */
    private function performAdditionalSafetyChecks(): void
    {
        // Check if database connection is active
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            throw new Exception('Database connection check failed: ' . $e->getMessage());
        }

        // Verify we're not in production if environment check is enabled
        if (config('app.prevent_reset_in_production', false)) {
            if (app()->environment('production')) {
                throw new Exception(
                    'System reset is disabled in production environment for safety.'
                );
            }
        }

        // Check if migrations are up to date
        // This ensures the database schema is in the expected state
        try {
            $pending = \DB::table('migrations')->count();
            if ($pending === 0) {
                throw new Exception('No migrations found. Database may not be properly initialized.');
            }
        } catch (\Exception $e) {
            // If migrations table doesn't exist, that's a critical issue
            if (str_contains($e->getMessage(), 'migrations')) {
                throw new Exception('Migrations table not found. Database integrity check failed.');
            }
        }
    }

    /**
     * Acquire a lock to prevent concurrent resets
     */
    private function acquireLock(): void
    {
        Cache::put(self::LOCK_KEY, [
            'started_at' => now()->toDateTimeString(),
            'admin_id' => $this->preservedAdmin->id,
        ], self::LOCK_DURATION);
    }

    /**
     * Release the lock (called after reset completes or fails)
     */
    public static function releaseLock(): void
    {
        Cache::forget(self::LOCK_KEY);
    }
}
