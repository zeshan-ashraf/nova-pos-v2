<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Reset Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the System Reset module.
    |
    */

    /**
     * Prevent system reset in production environment
     * 
     * Set to true to add an additional safety check that prevents
     * system reset when APP_ENV=production
     */
    'prevent_reset_in_production' => env('PREVENT_RESET_IN_PRODUCTION', false),

    /**
     * Maintenance mode secret
     * 
     * Secret key used during maintenance mode to allow admin access
     */
    'maintenance_secret' => env('MAINTENANCE_SECRET', 'system-reset-in-progress'),

    /**
     * Admin user email
     * 
     * The email address of the admin user that will be preserved during reset
     */
    'preserved_admin_email' => 'admin@gmail.com',

    /**
     * Admin user name
     * 
     * The name of the admin user that will be preserved during reset
     */
    'preserved_admin_name' => 'admin',

    /**
     * Lock duration in seconds
     * 
     * How long to hold the concurrent reset prevention lock
     */
    'lock_duration' => 3600, // 1 hour

    /**
     * Enable audit logging
     * 
     * Whether to log all reset attempts to the database
     */
    'enable_logging' => true,
];
