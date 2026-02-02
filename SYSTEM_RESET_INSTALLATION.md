# System Reset Module - Installation & Setup Guide

## 🎯 Quick Installation (5 Minutes)

### Step 1: Verify Files Exist

All files should already be in place. Verify key files:

```bash
# Check service files
ls -la app/Services/SystemReset/

# Check controller
ls -la app/Http/Controllers/Dashboard/SystemResetController.php

# Check views
ls -la resources/views/system-reset/

# Check config
ls -la config/system-reset.php

# Check migration
ls -la database/migrations/*system_reset_logs*
```

### Step 2: Run Migration

```bash
php artisan migrate
```

**Expected output:**
```
Migrating: 2026_02_01_000000_create_system_reset_logs_table
Migrated:  2026_02_01_000000_create_system_reset_logs_table (XX.XXms)
```

### Step 3: Clear All Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### Step 4: Verify Admin User

```bash
php artisan tinker
```

In tinker:
```php
>>> $admin = \App\Models\User::where('email', 'admin@gmail.com')->first();
>>> $admin ? $admin->email : 'NOT FOUND';
// Should output: "admin@gmail.com"

>>> $admin->name;
// Should output: "admin"

>>> $admin->shop_id;
// Should output: null

>>> exit
```

**If admin doesn't exist**, create it:

```bash
php artisan tinker
```

```php
>>> use App\Models\User;
>>> use Illuminate\Support\Facades\Hash;
>>> $admin = User::create([
...   'name' => 'admin',
...   'username' => 'admin',
...   'email' => 'admin@gmail.com',
...   'password' => Hash::make('password'),
...   'shop_id' => null,
...   'email_verified_at' => now(),
... ]);
>>> "Admin created with ID: " . $admin->id;
>>> exit
```

### Step 5: Verify/Create Super Admin Role

```bash
php artisan tinker
```

```php
>>> use Spatie\Permission\Models\Role;
>>> use App\Models\User;

>>> $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
>>> $admin = User::where('email', 'admin@gmail.com')->first();
>>> $admin->assignRole($role);
>>> "Role assigned successfully";
>>> exit
```

### Step 6: Verify Routes

```bash
php artisan route:list | grep system-reset
```

**Expected output:**
```
GET|HEAD  system-reset ................. system-reset.show › SystemResetController@show
POST      system-reset/execute ......... system-reset.execute › SystemResetController@execute
GET|HEAD  system-reset/logs ............ system-reset.logs › SystemResetController@logs
```

### Step 7: Test Access

1. Start your development server:
```bash
php artisan serve
```

2. Open browser and navigate to:
```
http://localhost:8000/system-reset
```

3. You should see the System Reset confirmation page

---

## 🔧 SQL Quick Setup (Alternative)

If you prefer SQL commands:

```sql
-- 1. Create/verify admin user
INSERT INTO users (name, username, email, password, shop_id, email_verified_at, created_at, updated_at)
VALUES (
    'admin',
    'admin',
    'admin@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    NULL,
    NOW(),
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = 'admin',
    shop_id = NULL,
    updated_at = NOW();

-- 2. Create Super Admin role
INSERT INTO roles (name, guard_name, created_at, updated_at)
VALUES ('Super Admin', 'web', NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- 3. Assign role to admin
INSERT IGNORE INTO model_has_roles (role_id, model_type, model_id)
SELECT 
    r.id,
    'App\\Models\\User',
    u.id
FROM roles r, users u
WHERE r.name = 'Super Admin'
  AND u.email = 'admin@gmail.com';

-- 4. Verify setup
SELECT 
    u.id,
    u.name,
    u.email,
    u.shop_id,
    r.name as role
FROM users u
LEFT JOIN model_has_roles mhr ON u.id = mhr.model_id
LEFT JOIN roles r ON mhr.role_id = r.id
WHERE u.email = 'admin@gmail.com';

-- Expected result:
-- +----+-------+------------------+---------+-------------+
-- | id | name  | email            | shop_id | role        |
-- +----+-------+------------------+---------+-------------+
-- |  1 | admin | admin@gmail.com  | NULL    | Super Admin |
-- +----+-------+------------------+---------+-------------+
```

---

## 🧪 Testing the Installation

### Test 1: Access Control

```bash
# Test as non-admin (should be blocked)
# Login as a regular user and try to access /system-reset
# Expected: 403 Forbidden or Access Denied
```

### Test 2: Validation

```bash
# Login as admin
# Navigate to /system-reset
# Try submitting with:
# - Wrong password → Should show error
# - Wrong confirmation text → Should show error
# - Correct details → Should execute reset
```

### Test 3: Automated Tests

```bash
php artisan test --filter SystemResetTest
```

**Expected output:**
```
PASS  Tests\Feature\SystemResetTest
✓ admin user is preserved after reset
✓ all shops are deleted
✓ all customers are deleted
✓ all products are deleted
✓ all orders are deleted
✓ super admin role exists after reset
✓ admin has super admin role after reset
✓ reset fails with wrong password
✓ reset fails with wrong confirmation text
✓ non super admin cannot reset
✓ reset fails if admin user does not exist
✓ reset is logged
✓ failed reset is logged
✓ transaction rollback on failure
✓ admin shop id is null after reset

Tests:  15 passed
Time:   X.XXs
```

---

## 📋 Post-Installation Checklist

- [ ] Migration ran successfully
- [ ] Admin user exists (admin@gmail.com)
- [ ] Admin name is "admin"
- [ ] Admin shop_id is NULL
- [ ] Super Admin role exists
- [ ] Admin has Super Admin role
- [ ] Routes are registered
- [ ] Can access /system-reset page
- [ ] Tests pass (if running tests)
- [ ] Logs directory writable (storage/logs)
- [ ] Cache cleared

---

## 🎮 First Reset Test (Recommended)

### Create Test Data

```bash
php artisan tinker
```

```php
>>> use App\Models\Shop;
>>> use App\Models\User;
>>> use App\Models\Product;

// Create test shop
>>> $shop = Shop::create([
...   'name' => 'Test Shop',
...   'owner_name' => 'Test Owner',
...   'phone' => '1234567890',
...   'address' => 'Test Address',
...   'is_parent' => false,
... ]);

// Create test user
>>> $user = User::create([
...   'name' => 'Test User',
...   'username' => 'testuser',
...   'email' => 'test@example.com',
...   'password' => bcrypt('password'),
...   'shop_id' => $shop->id,
... ]);

>>> "Created shop ID: {$shop->id} and user ID: {$user->id}";
>>> exit
```

### Verify Test Data Exists

```sql
SELECT COUNT(*) as shop_count FROM shops;
SELECT COUNT(*) as user_count FROM users;
-- Should show: shops > 0, users > 1 (admin + test user)
```

### Execute Reset

1. Login as admin@gmail.com
2. Navigate to `/system-reset`
3. Enter password: `password` (or your admin password)
4. Type: `RESET SYSTEM`
5. Check acknowledgment box
6. Click "Execute System Reset"
7. Confirm dialog

### Verify Reset Success

```sql
-- Should show only admin
SELECT COUNT(*) as user_count FROM users;  -- Result: 1
SELECT email FROM users;  -- Result: admin@gmail.com

-- Should show no shops
SELECT COUNT(*) as shop_count FROM shops;  -- Result: 0

-- Check reset log
SELECT * FROM system_reset_logs ORDER BY created_at DESC LIMIT 1;
-- Should show: result = 'success'
```

---

## 🔧 Configuration (Optional)

### Add to .env

```env
# System Reset Configuration
PREVENT_RESET_IN_PRODUCTION=true
MAINTENANCE_SECRET=your-secret-key-here
```

### Publish Config (Optional)

```bash
php artisan vendor:publish --tag=system-reset-config
```

This creates `config/system-reset.php` if you want to customize it further.

---

## 🚨 Troubleshooting

### Issue: "Class not found"

```bash
composer dump-autoload
php artisan clear-compiled
php artisan optimize
```

### Issue: "Migration already exists"

```bash
# Check if table exists
php artisan tinker
>>> \Schema::hasTable('system_reset_logs');
# If true, migration already ran
```

### Issue: "Route not found"

```bash
php artisan route:clear
php artisan route:cache
php artisan route:list | grep system-reset
```

### Issue: "View not found"

```bash
php artisan view:clear
ls -la resources/views/system-reset/
```

### Issue: "Permission denied"

```bash
# Fix permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 📞 Support Commands

### View System Info

```bash
php artisan tinker
```

```php
>>> echo "PHP Version: " . PHP_VERSION;
>>> echo "Laravel Version: " . app()->version();
>>> echo "Database: " . config('database.default');
>>> echo "Cache Driver: " . config('cache.default');
>>> exit
```

### View Reset Logs

```bash
php artisan tinker
```

```php
>>> DB::table('system_reset_logs')->orderBy('created_at', 'desc')->take(5)->get();
>>> exit
```

### Clear Reset Lock (if stuck)

```bash
php artisan tinker
```

```php
>>> Cache::forget('system_reset_in_progress');
>>> "Lock cleared";
>>> exit
```

---

## ✅ Installation Complete!

Your System Reset module is now fully installed and ready to use.

**Next Steps:**
1. ✅ Review documentation: `docs/SYSTEM_RESET.md`
2. ✅ Test in development environment
3. ✅ Train Super Admin users
4. ✅ Create backup procedures
5. ✅ Deploy to production (when ready)

**Access URL:** `http://your-domain/system-reset`

**Test Credentials:**
- Email: `admin@gmail.com`
- Password: `password` (or your custom password)

---

**Installation Time:** ~5 minutes  
**Difficulty:** Easy  
**Status:** ✅ Ready for Production
