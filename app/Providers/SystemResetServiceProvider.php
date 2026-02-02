<?php

namespace App\Providers;

use App\Services\SystemReset\ResetLogger;
use App\Services\SystemReset\ResetPlan;
use App\Services\SystemReset\ResetValidator;
use App\Services\SystemReset\SystemResetService;
use Illuminate\Support\ServiceProvider;

/**
 * System Reset Service Provider
 * 
 * Registers all System Reset services in the container.
 */
class SystemResetServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register ResetValidator as singleton
        $this->app->singleton(ResetValidator::class, function ($app) {
            return new ResetValidator();
        });

        // Register ResetPlan as singleton
        $this->app->singleton(ResetPlan::class, function ($app) {
            return new ResetPlan();
        });

        // Register ResetLogger as singleton
        $this->app->singleton(ResetLogger::class, function ($app) {
            return new ResetLogger();
        });

        // Register SystemResetService
        $this->app->singleton(SystemResetService::class, function ($app) {
            return new SystemResetService(
                $app->make(ResetValidator::class),
                $app->make(ResetPlan::class),
                $app->make(ResetLogger::class)
            );
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/system-reset.php',
            'system-reset'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/system-reset.php' => config_path('system-reset.php'),
        ], 'system-reset-config');

        // Publish migration
        $this->publishes([
            __DIR__ . '/../../database/migrations/2026_02_01_000000_create_system_reset_logs_table.php' 
                => database_path('migrations/' . date('Y_m_d_His') . '_create_system_reset_logs_table.php'),
        ], 'system-reset-migration');

        // Publish views
        $this->publishes([
            __DIR__ . '/../../resources/views/system-reset' => resource_path('views/system-reset'),
        ], 'system-reset-views');
    }
}
