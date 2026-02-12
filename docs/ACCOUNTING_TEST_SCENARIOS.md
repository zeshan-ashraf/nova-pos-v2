# Accounting Double-Entry Test Scenarios

Use these scenarios to verify ledger correctness after the accounting refactor. **Do not modify historical records**; run tests only for **new** transactions.

---

## Global Rules (Verify)

- **Customer balance** = `SUM(debit) - SUM(credit)` → positive = customer owes us, negative = advance.
- **Supplier balance** = `SUM(credit) - SUM(debit)` → positive = we owe supplier, negative = advance paid.
- All multi-row inserts must run inside `DB::transaction()`.
- Filter by `shop_id` everywhere.

---

## 1. Credit Sale (unpaid invoice)

**Action:** Create an invoice with payment method = Credit, pay = 0, due = total.

**Expected `account_transactions`:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| sale        | credit    | total | Revenue |
| customer    | debit     | total | AR increase (customer owes) |

**Verify:** Customer ledger balance for that customer = total (they owe us).

---

## 2. Full Cash Sale (fully paid at creation)

**Action:** Create an invoice with payment method = Cash (or Bank), pay = total, due = 0. PaymentLog(s) created; SalePaymentLedgerService runs.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| sale        | credit    | total | Revenue |
| customer    | debit     | total | AR (then reduced by payment) |
| cash or bank| debit     | total | Money in (from payment) |
| customer    | credit    | total | AR decrease (payment received) |

**Verify:** Customer balance = 0. Cash/bank increased by total.

---

## 3. Partial Sale (part paid at creation)

**Action:** Create invoice with pay = X, due = Y, total = X + Y (X, Y > 0). PaymentLog for X; SalePaymentLedgerService runs.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| sale        | credit    | total | Revenue |
| customer    | debit     | total | AR |
| cash/bank   | debit     | X     | Money in |
| customer    | credit    | X     | AR decrease |

**Verify:** Customer balance = Y (due). Cash/bank increased by X.

---

## 4. Customer Payment (standalone)

**Action:** Payments → Customer Payment → record payment (cash or bank) for a customer.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| cash or bank| debit     | amount| Money in |
| customer    | credit    | amount| AR decrease |

**Verify:** Customer balance decreased by amount. Cash/bank increased.

---

## 5. Credit Purchase (unpaid)

**Action:** Create a purchase with payment_status = Credit, pay = 0, due = total.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| purchase    | debit     | total | Inventory/cost |
| supplier    | credit    | total | We owe (AP) |

**Verify:** Supplier balance = total (we owe them).

---

## 6. Full Paid Purchase

**Action:** Create a purchase with payment_status = Cash/Bank, pay = total, due = 0.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| purchase    | debit     | total | Inventory/cost |
| cash or bank| credit   | total | Money out |

**Verify:** Supplier balance = 0. Cash/bank decreased by total. No supplier credit row (due = 0).

---

## 7. Partial Purchase

**Action:** Create purchase with pay = X, due = Y, total = X + Y (X, Y > 0).

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| purchase    | debit     | total | Inventory/cost |
| supplier    | credit    | Y     | We owe (due) |
| cash or bank| credit    | X     | Money out |

**Verify:** Supplier balance = Y. Cash/bank decreased by X.

---

## 8. Supplier Payment (standalone)

**Action:** Payments → Supplier Payment → record payment (cash or bank) to a supplier.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| supplier    | debit     | amount| We owe less (AP decrease) |
| cash or bank| credit    | amount| Money out |

**Verify:** Supplier balance decreased by amount. Cash/bank decreased.

---

## 9. Supplier Payment (later, against credit purchase)

**Action:** On an existing credit purchase, record a payment (PurchasePaymentLog). PurchaseLedgerService::recordPurchasePaymentLog runs.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| cash or bank| credit    | amount| Money out |
| supplier    | debit     | amount| AP decrease |

**Verify:** Supplier balance decreased by amount.

---

## 10. Expense

**Action:** Create an expense (cash or bank). ExpenseLedgerService::recordExpense runs.

**Expected:**

| account_type | direction | amount | meaning |
|-------------|-----------|--------|---------|
| expense     | debit     | amount| Expense increase |
| cash or bank| credit    | amount| Money out |

**Verify:** Cash/bank decreased. (Expense account_type used for reporting; balance formula unchanged for customer/supplier.)

---

## Quick Checklist

- [ ] Credit sale: customer debit + sale credit only.
- [ ] Full cash sale: sale credit + customer debit + cash/bank debit + customer credit.
- [ ] Partial sale: same as full cash but amounts split (pay vs due).
- [ ] Customer payment: cash/bank debit + customer credit.
- [ ] Credit purchase: purchase debit + supplier credit (due).
- [ ] Full paid purchase: purchase debit + cash/bank credit only.
- [ ] Partial purchase: purchase debit + supplier credit (due) + cash/bank credit (pay).
- [ ] Supplier payment (standalone): supplier debit + cash/bank credit.
- [ ] Supplier payment (later): cash/bank credit + supplier debit.
- [ ] Expense: expense debit + cash/bank credit.
- [ ] All inserts inside DB::transaction; shop_id set; no duplicate entries for same source_type + source_id.
