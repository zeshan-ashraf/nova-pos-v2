# System Reset - Quick Reference

## 🚀 Quick Start

1. **Migrate**: `php artisan migrate`
2. **Verify admin exists**: Email = `admin@gmail.com`, Name = `admin`
3. **Assign Super Admin role** to admin user
4. **Access**: Navigate to `/system-reset`

---

## 🔑 Access Requirements

- ✅ Must have **Super Admin** role
- ✅ Must have **shop_id = NULL**
- ✅ Must provide **correct password**
- ✅ Must type **"RESET SYSTEM"** exactly

---

## ⚠️ What Happens

### DELETED:
- All shops
- All users (except admin@gmail.com)
- All products & inventory
- All orders & purchases
- All customers & suppliers
- All employees
- All transactions
- All roles & permissions (recreated)
- All tokens

### PRESERVED:
- Admin user (admin@gmail.com)
- System reset logs
- Database migrations

### RECREATED:
- Super Admin role
- Default permissions
- Admin role assignment

---

## 🔒 Safety Features

1. **Password required**
2. **Confirmation text required**
3. **Single transaction** (rollback on error)
4. **FK-safe deletions** (child → parent)
5. **Concurrent prevention** (one at a time)
6. **Audit logging** (all attempts logged)
7. **Maintenance mode** (auto-enabled/disabled)

---

## 🌐 Routes

| Method | URL | Action |
|--------|-----|--------|
| GET | `/system-reset` | Show confirmation page |
| POST | `/system-reset/execute` | Execute reset |
| GET | `/system-reset/logs` | View history |

---

## 📝 Configuration

File: `config/system-reset.php`

```php
'prevent_reset_in_production' => false,  // Block in production?
'maintenance_secret' => 'system-reset-in-progress',
'preserved_admin_email' => 'admin@gmail.com',
'lock_duration' => 3600,  // 1 hour
```

---

## 🐛 Troubleshooting

### "Already in progress"
```bash
php artisan tinker
>>> Cache::forget('system_reset_in_progress');
```

### "Admin not found"
```sql
INSERT INTO users (name, username, email, password, shop_id)
VALUES ('admin', 'admin', 'admin@gmail.com', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL);
```

### View Logs
```bash
tail -f storage/logs/laravel.log
```

Or in UI: `/system-reset/logs`

---

## ✅ Pre-Reset Checklist

- [ ] Admin user exists
- [ ] Admin has Super Admin role
- [ ] Database backup created
- [ ] Team notified
- [ ] All sessions closed

---

## 📊 Post-Reset Verification

```sql
-- Should return 1 (admin only)
SELECT COUNT(*) FROM users;

-- Should return admin@gmail.com
SELECT email FROM users;

-- Should return NULL
SELECT shop_id FROM users WHERE email = 'admin@gmail.com';

-- Should return 0
SELECT COUNT(*) FROM shops;
SELECT COUNT(*) FROM products;
SELECT COUNT(*) FROM orders;

-- Should have entry
SELECT * FROM system_reset_logs ORDER BY created_at DESC LIMIT 1;
```

---

## 🧪 Testing

```bash
php artisan test --filter SystemResetTest
```

---

## 📚 Full Documentation

See: `docs/SYSTEM_RESET.md`

---

**Emergency Contact**: Check logs at `/system-reset/logs` or `system_reset_logs` table
