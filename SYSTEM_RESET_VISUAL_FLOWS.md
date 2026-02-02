# System Reset Module - Visual Flow Diagrams

## 1. Request Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER INITIATES                          │
│                    (via Web Interface)                          │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   SystemResetController                         │
│                      (HTTP Layer)                               │
│  • Validates request input (password, confirmation_text)        │
│  • Gets authenticated user                                      │
│  • Gets IP address                                              │
│  • Logs attempt to Laravel log                                  │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   SystemResetService                            │
│                   (Main Orchestrator)                           │
│  1. Validate (via ResetValidator)                               │
│  2. Enable maintenance mode                                     │
│  3. Start DB transaction                                        │
│  4. Execute deletions (via ResetPlan)                           │
│  5. Reset auto-increments                                       │
│  6. Recreate base data                                          │
│  7. Ensure admin shop_id = NULL                                 │
│  8. Commit transaction                                          │
│  9. Disable maintenance mode                                    │
│  10. Log result (via ResetLogger)                               │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Return Result to User                        │
│  • Success: Redirect with success message                       │
│  • Failure: Redirect with error message                         │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Validation Flow (ResetValidator)

```
                        START VALIDATION
                               │
                               ▼
                ┌──────────────────────────┐
                │ Check Concurrent Reset?  │
                │ (Cache lock exists?)     │
                └──────┬──────────────┬────┘
                  YES  │              │ NO
                       │              │
                ┌──────▼────┐         │
                │  REJECT   │         │
                └───────────┘         │
                                      ▼
                        ┌──────────────────────────┐
                        │ User is Super Admin?     │
                        │ (Has 'Super Admin' role?)│
                        └──────┬──────────────┬────┘
                          NO   │              │ YES
                               │              │
                        ┌──────▼────┐         │
                        │  REJECT   │         │
                        └───────────┘         │
                                              ▼
                                ┌──────────────────────────┐
                                │ User shop_id = NULL?     │
                                │ (Root admin check)       │
                                └──────┬──────────────┬────┘
                                  NO   │              │ YES
                                       │              │
                                ┌──────▼────┐         │
                                │  REJECT   │         │
                                └───────────┘         │
                                                      ▼
                                        ┌──────────────────────────┐
                                        │ Password correct?        │
                                        │ (Hash::check)            │
                                        └──────┬──────────────┬────┘
                                          NO   │              │ YES
                                               │              │
                                        ┌──────▼────┐         │
                                        │  REJECT   │         │
                                        └───────────┘         │
                                                              ▼
                                                ┌──────────────────────────┐
                                                │ Confirmation = "RESET    │
                                                │ SYSTEM" (exact)?         │
                                                └──────┬──────────────┬────┘
                                                  NO   │              │ YES
                                                       │              │
                                                ┌──────▼────┐         │
                                                │  REJECT   │         │
                                                └───────────┘         │
                                                                      ▼
                                                        ┌──────────────────────────┐
                                                        │ Admin user exists?       │
                                                        │ (admin@gmail.com)        │
                                                        └──────┬──────────────┬────┘
                                                          NO   │              │ YES
                                                               │              │
                                                        ┌──────▼────┐         │
                                                        │  REJECT   │         │
                                                        └───────────┘         │
                                                                              ▼
                                                                    ┌──────────────┐
                                                                    │ Acquire Lock │
                                                                    └──────┬───────┘
                                                                           │
                                                                           ▼
                                                                    ┌──────────────┐
                                                                    │   APPROVE    │
                                                                    └──────────────┘
```

---

## 3. Deletion Order (ResetPlan)

```
                    START DELETIONS
                           │
    ┌──────────────────────┴──────────────────────┐
    │         ALL IN SINGLE TRANSACTION           │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │              LEVEL 1: Deepest Children      │
    │  • sale_return_details                      │
    │  • order_details                            │
    │  • purchase_details                         │
    │  • payment_logs                             │
    │  • purchase_payment_logs                    │
    │  • stock_logs                               │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │          LEVEL 2: Intermediate Children     │
    │  • sale_returns                             │
    │  • orders                                   │
    │  • purchases                                │
    │  • activities (expenses)                    │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │            LEVEL 3: Employee Data           │
    │  • pay_salaries                             │
    │  • advance_salaries                         │
    │  • attendences                              │
    │  • employees                                │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │         LEVEL 4: Products & Inventory       │
    │  • products                                 │
    │  • categories                               │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │      LEVEL 5: Customers & Suppliers         │
    │  • customers                                │
    │  • suppliers                                │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │            LEVEL 6: Shops & Banks           │
    │  • bank_shop (pivot)                        │
    │  • banks                                    │
    │  • shops                                    │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │       LEVEL 7: Roles & Permissions          │
    │  • model_has_permissions                    │
    │  • model_has_roles                          │
    │  • role_has_permissions                     │
    │  • permissions                              │
    │  • roles                                    │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │        LEVEL 8: Users (EXCEPT ADMIN)        │
    │  • DELETE FROM users WHERE id != {admin_id} │
    │  • Admin is PRESERVED by exclusion          │
    └──────────────────────┬──────────────────────┘
                           │
    ┌──────────────────────▼──────────────────────┐
    │              LEVEL 9: Tokens                │
    │  • personal_access_tokens                   │
    │  • password_reset_tokens                    │
    └──────────────────────┬──────────────────────┘
                           │
                           ▼
                    DELETIONS COMPLETE
```

---

## 4. Success Flow

```
User Request
     │
     ▼
┌─────────────────┐
│ Validation      │──── ✅ All checks pass
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Enable          │──── Site goes into maintenance mode
│ Maintenance     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Begin           │──── Start database transaction
│ Transaction     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Execute         │──── Delete in FK-safe order
│ Deletions       │──── (9 levels)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Reset Auto      │──── Reset ID sequences
│ Increments      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Recreate Base   │──── Create Super Admin role
│ Data            │──── Create default permissions
│                 │──── Assign role to admin
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Ensure Admin    │──── Force admin.shop_id = NULL
│ shop_id = NULL  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Commit          │──── All changes committed
│ Transaction     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Disable         │──── Site back online
│ Maintenance     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Log Success     │──── Record in system_reset_logs
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Return Success  │──── User sees success message
│ Message         │
└─────────────────┘
```

---

## 5. Failure Flow (with Rollback)

```
User Request
     │
     ▼
┌─────────────────┐
│ Validation      │──── ❌ Check fails
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Log Failure     │──── Record error in system_reset_logs
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Return Error    │──── User sees error message
│ Message         │──── System unchanged
└─────────────────┘

──────────────── OR ────────────────

User Request
     │
     ▼
┌─────────────────┐
│ Validation      │──── ✅ Passes
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Enable          │
│ Maintenance     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Begin           │
│ Transaction     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Execute         │──── ❌ ERROR OCCURS
│ Deletions       │      (e.g., FK violation)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Catch Exception │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Rollback        │──── ALL changes reverted
│ Transaction     │──── Database unchanged
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Disable         │──── Site back online
│ Maintenance     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Log Failure     │──── Record error in system_reset_logs
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Return Error    │──── User sees error message
│ Message         │──── Database unchanged
└─────────────────┘
```

---

## 6. Component Interaction

```
┌──────────────────────────────────────────────────────────┐
│                    Browser/Client                        │
└─────────────────────┬────────────────────────────────────┘
                      │ HTTP Request
                      │ POST /system-reset/execute
                      ▼
┌──────────────────────────────────────────────────────────┐
│                  Laravel Middleware                      │
│  • auth (verify authenticated)                           │
│  • role:Super Admin (verify role)                        │
└─────────────────────┬────────────────────────────────────┘
                      │
                      ▼
┌──────────────────────────────────────────────────────────┐
│            SystemResetController                         │
│  • Validate request                                      │
│  • Get user & IP                                         │
│  • Call service                                          │
│  • Return response                                       │
└─────────────────────┬────────────────────────────────────┘
                      │
                      ▼
┌──────────────────────────────────────────────────────────┐
│            SystemResetService                            │
│  (Main Orchestrator)                                     │
└──┬─────────────┬──────────────┬──────────────────────────┘
   │             │              │
   │             │              │
   ▼             ▼              ▼
┌──────┐   ┌──────────┐   ┌──────────┐
│Validator│ │   Plan   │   │  Logger  │
└────┬───┘   └────┬─────┘   └────┬─────┘
     │            │              │
     │            │              │
     ▼            ▼              ▼
┌─────────────────────────────────────┐
│          Database                   │
│  • Validate queries                 │
│  • Delete queries                   │
│  • Insert queries (base data)       │
│  • Log queries                      │
└─────────────────────────────────────┘
```

---

## 7. State Transitions

```
┌─────────────┐
│   IDLE      │ ◄─────────────────────┐
│ (No reset)  │                        │
└──────┬──────┘                        │
       │ User initiates reset         │
       │                              │
       ▼                              │
┌─────────────┐                        │
│ VALIDATING  │                        │
└──────┬──────┘                        │
       │ Checks pass                  │
       │                              │
       ▼                              │
┌─────────────┐                        │
│ MAINTENANCE │                        │
│ MODE ON     │                        │
└──────┬──────┘                        │
       │                              │
       ▼                              │
┌─────────────┐                        │
│ TRANSACTION │                        │
│ STARTED     │                        │
└──────┬──────┘                        │
       │                              │
       ▼                              │
┌─────────────┐     Error              │
│ DELETING    │────────────┐           │
│ DATA        │            │           │
└──────┬──────┘            │           │
       │ Success           ▼           │
       │            ┌──────────────┐   │
       ▼            │  ROLLING     │   │
┌─────────────┐    │  BACK        │   │
│ RECREATING  │    └──────┬───────┘   │
│ BASE DATA   │           │           │
└──────┬──────┘           │           │
       │                  │           │
       ▼                  │           │
┌─────────────┐           │           │
│ COMMITTING  │           │           │
│ TRANSACTION │           │           │
└──────┬──────┘           │           │
       │                  │           │
       ▼                  │           │
┌─────────────┐           │           │
│ MAINTENANCE │           │           │
│ MODE OFF    │◄──────────┘           │
└──────┬──────┘                        │
       │                              │
       ▼                              │
┌─────────────┐                        │
│  LOGGING    │                        │
└──────┬──────┘                        │
       │                              │
       │ Complete                     │
       └──────────────────────────────┘
```

---

## 8. Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    BEFORE RESET                             │
├─────────────────────────────────────────────────────────────┤
│ Users:       100 records (including admin)                  │
│ Shops:       10 shops                                       │
│ Products:    500 products                                   │
│ Orders:      1000 orders                                    │
│ Customers:   200 customers                                  │
│ Suppliers:   50 suppliers                                   │
│ Employees:   30 employees                                   │
│ ... (more tables with data)                                 │
└─────────────────────────────────────────────────────────────┘
                           │
                           │ RESET EXECUTES
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    AFTER RESET                              │
├─────────────────────────────────────────────────────────────┤
│ Users:       1 record (admin@gmail.com only) ✅             │
│ Shops:       0 records                                      │
│ Products:    0 records                                      │
│ Orders:      0 records                                      │
│ Customers:   0 records                                      │
│ Suppliers:   0 records                                      │
│ Employees:   0 records                                      │
│ Roles:       1 record (Super Admin) ✅                      │
│ Permissions: ~15 default permissions ✅                     │
│ system_reset_logs: +1 record ✅                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 9. Security Layers

```
                    ┌─────────────────┐
                    │  User Request   │
                    └────────┬────────┘
                             │
                ┌────────────▼────────────┐
                │  Layer 1: Auth Check    │
                │  (Must be logged in)    │
                └────────────┬────────────┘
                             │ ✅
                ┌────────────▼────────────┐
                │  Layer 2: Role Check    │
                │  (Super Admin role)     │
                └────────────┬────────────┘
                             │ ✅
                ┌────────────▼────────────┐
                │  Layer 3: Shop Check    │
                │  (shop_id = NULL)       │
                └────────────┬────────────┘
                             │ ✅
                ┌────────────▼────────────┐
                │  Layer 4: Password      │
                │  (Correct password)     │
                └────────────┬────────────┘
                             │ ✅
                ┌────────────▼────────────┐
                │  Layer 5: Confirmation  │
                │  ("RESET SYSTEM")       │
                └────────────┬────────────┘
                             │ ✅
                ┌────────────▼────────────┐
                │  Layer 6: Admin Exists  │
                │  (admin@gmail.com)      │
                └────────────┬────────────┘
                             │ ✅
                ┌────────────▼────────────┐
                │  Layer 7: No Concurrent │
                │  (Lock check)           │
                └────────────┬────────────┘
                             │ ✅
                    ┌────────▼────────┐
                    │  RESET ALLOWED  │
                    └─────────────────┘
```

---

## 10. File Structure Tree

```
app/
├── Http/
│   └── Controllers/
│       └── Dashboard/
│           └── SystemResetController.php ✨
├── Providers/
│   └── SystemResetServiceProvider.php ✨
└── Services/
    └── SystemReset/ ✨
        ├── SystemResetService.php
        ├── ResetValidator.php
        ├── ResetPlan.php
        └── ResetLogger.php

config/
└── system-reset.php ✨

database/
└── migrations/
    └── 2026_02_01_000000_create_system_reset_logs_table.php ✨

docs/
└── SYSTEM_RESET.md ✨

resources/
└── views/
    └── system-reset/ ✨
        ├── confirm.blade.php
        └── logs.blade.php

routes/
└── web.php (modified) ✨

tests/
└── Feature/
    └── SystemResetTest.php ✨

Root:
├── SYSTEM_RESET_COMPLETE.md ✨
├── SYSTEM_RESET_IMPLEMENTATION.md ✨
├── SYSTEM_RESET_QUICK_REFERENCE.md ✨
└── SYSTEM_RESET_VISUAL_FLOWS.md ✨

✨ = New/Modified file
```

---

**End of Visual Flow Diagrams**
