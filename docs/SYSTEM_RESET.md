# System Reset Module - Documentation

## Overview

The **System Reset (Protected)** module provides a production-safe way to reset the entire POS database to a fresh state while preserving exactly one admin user. This module is designed with multiple layers of security, transactional safety, and comprehensive audit logging.

## Architecture

### Service-Based Design

The module follows a clean, service-based architecture with clear separation of concerns:

```
app/Services/SystemReset/
├── SystemResetService.php    # Main orchestrator
├── ResetValidator.php         # Security & validation
├── ResetPlan.php              # Deletion plan & base data
└── ResetLogger.php            # Audit logging
```

### Controller Layer

```
app/Http/Controllers/Dashboard/
└── SystemResetController.php  # HTTP layer (NO business logic)
```

## Key Features

### 1. Admin Preservation (Non-Negotiable)

The module **MUST** preserve exactly one admin user with these properties:
- **Email**: `admin@gmail.com`
- **Name**: `admin`
- **shop_id**: `NULL`

**CRITICAL**: The admin is NOT deleted and recreated. The existing admin user is preserved by ID exclusion.

### 2. Security Layers

The module implements multiple security layers:

1. **Role Verification**: Only users with "Super Admin" role can access
2. **Password Confirmation**: User must provide their current password
3. **Confirmation Text**: User must type "RESET SYSTEM" exactly
4. **Shop Verification**: User must have `shop_id = NULL` (root admin only)
5. **Concurrent Prevention**: Only one reset can run at a time
6. **Environment Check**: Optional production environment protection

### 3. Transaction Safety

- All deletions happen within a **single database transaction**
- If ANY operation fails → **complete rollback**
- No partial deletions
- No orphan records
- Maintenance mode enabled/disabled automatically

### 4. FK-Safe Deletions

The module deletes records in strict child → parent order to respect foreign key constraints:

**Level 1** (Deepest children):
- sale_return_details
- order_details
- purchase_details
- payment_logs
- purchase_payment_logs
- stock_logs

**Level 2** (Intermediate):
- sale_returns
- orders
- purchases
- activities (expenses)

**Level 3** (Employee data):
- pay_salaries
- advance_salaries
- attendences
- employees

**Level 4** (Products):
- products
- categories

**Level 5** (Relations):
- customers
- suppliers

**Level 6** (Shops):
- bank_shop (pivot)
- banks
- shops

**Level 7** (Permissions):
- model_has_permissions
- model_has_roles
- role_has_permissions
- permissions
- roles

**Level 8** (Users):
- users (EXCEPT admin)

**Level 9** (Tokens):
- personal_access_tokens
- password_reset_tokens

### 5. Base Data Recreation

After deletion, the module recreates essential system data:

1. **Super Admin Role**: Creates the "Super Admin" role
2. **Role Assignment**: Assigns Super Admin role to preserved admin
3. **Default Permissions**: Creates basic permissions for:
   - Dashboard
   - Shops
   - Users
   - Roles
   - Permissions
4. **Permission Assignment**: Links all permissions to Super Admin role
5. **Cache Clearing**: Clears Spatie permission cache

### 6. Audit Logging

Every reset attempt (success or failure) is logged with:
- `triggered_by_user_id`: Who initiated the reset
- `triggered_by_email`: Email of the user
- `ip_address`: IP address of the request
- `result`: "success" or "failed"
- `duration_seconds`: How long it took (for successful resets)
- `error_message`: Error details (for failed resets)
- `created_at`: Timestamp

**IMPORTANT**: The `system_reset_logs` table survives the reset and is NOT deleted.

## Installation

### Step 1: Run Migration

```bash
php artisan migrate
```

This creates the `system_reset_logs` table.

### Step 2: Verify Admin User Exists

Ensure your database has a user with:
- Email: `admin@gmail.com`
- Name: `admin`

If not, create one:

```sql
INSERT INTO users (name, username, email, password, shop_id, email_verified_at, created_at, updated_at)
VALUES ('admin', 'admin', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NOW(), NOW(), NOW());
-- Default password: password
```

### Step 3: Assign Super Admin Role

```sql
-- Get user ID
SET @user_id = (SELECT id FROM users WHERE email = 'admin@gmail.com');

-- Get or create Super Admin role
INSERT INTO roles (name, guard_name, created_at, updated_at)
VALUES ('Super Admin', 'web', NOW(), NOW())
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);

SET @role_id = LAST_INSERT_ID();

-- Assign role
INSERT INTO model_has_roles (role_id, model_type, model_id)
VALUES (@role_id, 'App\\Models\\User', @user_id);
```

### Step 4: Access the Module

Navigate to: `/system-reset`

**Note**: This route is only accessible to Super Admin users.

## Usage

### Via Web Interface

1. Login as Super Admin
2. Navigate to `/system-reset`
3. Enter your password
4. Type "RESET SYSTEM" in the confirmation field
5. Check the acknowledgment checkbox
6. Click "Execute System Reset"
7. Confirm the final warning dialog

### Programmatic Usage

```php
use App\Services\SystemReset\SystemResetService;
use App\Models\User;

$resetService = app(SystemResetService::class);
$admin = User::where('email', 'admin@gmail.com')->first();

$result = $resetService->execute(
    triggeredBy: $admin,
    password: 'admin-password',
    confirmationText: 'RESET SYSTEM',
    ipAddress: request()->ip()
);

if ($result['success']) {
    echo "Reset successful: " . $result['message'];
} else {
    echo "Reset failed: " . $result['message'];
}
```

## Configuration

Edit `config/system-reset.php`:

```php
return [
    // Prevent reset in production
    'prevent_reset_in_production' => env('PREVENT_RESET_IN_PRODUCTION', false),
    
    // Maintenance mode secret
    'maintenance_secret' => env('MAINTENANCE_SECRET', 'system-reset-in-progress'),
    
    // Admin user details
    'preserved_admin_email' => 'admin@gmail.com',
    'preserved_admin_name' => 'admin',
    
    // Lock duration (1 hour)
    'lock_duration' => 3600,
    
    // Enable logging
    'enable_logging' => true,
];
```

Add to `.env`:

```env
PREVENT_RESET_IN_PRODUCTION=true
MAINTENANCE_SECRET=your-secret-key-here
```

## Error Handling

### Common Errors

**1. "A system reset is already in progress"**
- **Cause**: Another reset is running or a previous reset didn't complete
- **Solution**: Wait 1 hour or manually clear cache: `Cache::forget('system_reset_in_progress')`

**2. "Access denied. Only Super Admin users can perform system reset."**
- **Cause**: User doesn't have Super Admin role
- **Solution**: Assign Super Admin role to user

**3. "Admin user with email admin@gmail.com not found"**
- **Cause**: Required admin user doesn't exist
- **Solution**: Create the admin user (see Installation Step 2)

**4. "Password confirmation failed"**
- **Cause**: Incorrect password entered
- **Solution**: Enter the correct password

**5. "Confirmation text does not match"**
- **Cause**: Didn't type "RESET SYSTEM" exactly
- **Solution**: Type exactly: `RESET SYSTEM` (case-sensitive)

### Rollback Behavior

If ANY error occurs during the reset:
1. **Database transaction is rolled back** (no partial changes)
2. **Maintenance mode is disabled**
3. **Error is logged** to `system_reset_logs`
4. **Error message is returned** to user
5. **Lock is maintained** for 1 hour to prevent rapid retry attempts

## Maintenance Mode

During reset:
- Site enters **maintenance mode**
- Admin can still access using secret: `https://yourdomain.com/?secret=system-reset-in-progress`
- Maintenance mode is automatically **disabled** after reset (success or failure)

## Routes

```php
// Show reset confirmation page
GET  /system-reset

// Execute reset
POST /system-reset/execute

// View reset history
GET  /system-reset/logs
```

All routes require:
- Authentication (`auth` middleware)
- Super Admin role (`role:Super Admin` middleware)

## Testing

### Manual Testing Checklist

1. ✅ Non-super-admin cannot access
2. ✅ Wrong password is rejected
3. ✅ Wrong confirmation text is rejected
4. ✅ Admin user is preserved after reset
5. ✅ Admin shop_id is NULL after reset
6. ✅ All other users are deleted
7. ✅ Super Admin role exists after reset
8. ✅ Admin has Super Admin role after reset
9. ✅ Default permissions are created
10. ✅ All shops are deleted
11. ✅ All products are deleted
12. ✅ All orders are deleted
13. ✅ All customers are deleted
14. ✅ All suppliers are deleted
15. ✅ Reset is logged in system_reset_logs
16. ✅ Concurrent reset is prevented
17. ✅ Failed reset is rolled back completely

### Test Script

Create a test file to verify behavior:

```php
// tests/Feature/SystemResetTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Services\SystemReset\SystemResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SystemResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_preserved_after_reset()
    {
        // Create admin user
        $admin = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'shop_id' => null,
        ]);
        
        $admin->assignRole('Super Admin');
        
        // Create some other users
        User::factory()->count(5)->create();
        
        // Execute reset
        $service = app(SystemResetService::class);
        $result = $service->execute($admin, 'password', 'RESET SYSTEM', '127.0.0.1');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, User::count());
        $this->assertEquals('admin@gmail.com', User::first()->email);
        $this->assertNull(User::first()->shop_id);
    }
}
```

## Security Considerations

### DO NOT:
- ❌ Expose this module in normal navigation menus
- ❌ Allow shop-level admins to access this feature
- ❌ Disable foreign key checks during deletion
- ❌ Use TRUNCATE commands
- ❌ Skip transaction handling
- ❌ Allow concurrent resets

### DO:
- ✅ Require Super Admin role
- ✅ Require password confirmation
- ✅ Require explicit confirmation text
- ✅ Use database transactions
- ✅ Respect foreign key constraints
- ✅ Log all attempts
- ✅ Enable maintenance mode
- ✅ Prevent concurrent execution

## Support

### Viewing Logs

Access reset history at: `/system-reset/logs`

Or query directly:

```sql
SELECT * FROM system_reset_logs ORDER BY created_at DESC;
```

### Debugging

Enable debug logging by checking `storage/logs/laravel.log` for:
- `System reset initiated`
- `Deleting from: {table}`
- `System reset completed successfully`
- `System reset failed`

### Manual Recovery

If a reset fails and leaves the system in an inconsistent state:

1. Check the error in `system_reset_logs` table
2. Review `storage/logs/laravel.log`
3. Manually restore from backup
4. Clear the reset lock: `Cache::forget('system_reset_in_progress')`

## License

This module is part of the Nova POS system.

---

**Last Updated**: 2026-02-01  
**Version**: 1.0.0  
**Author**: Senior Laravel Backend Architect
