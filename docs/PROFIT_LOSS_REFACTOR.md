# Profit & Loss Calculation — Refactor & Audit

## Summary of changes

- **COGS**: Calculated **only** from `order_details` (`qty * cost_per_unit`). No `stock_logs`, no `products.buying_price` fallback, no `ABS(qty)`.
- **Sales**: From `order_details.unitcost * order_details.quantity`. Single join orders + order_details; date filter on `orders.created_at`.
- **Discounts**: New section — Invoice Discounts `SUM(orders.invoice_discount)` and Line Item Discounts `SUM(order_details.item_discount)`.
- **Profit formula**: Gross Profit = Total Sales − COGS. Net Profit = Gross Profit − Invoice Discounts − Line Item Discounts − Operating Expenses.
- **Validation**: No stock_logs, no fallback, no ABS, no duplicate joins; soft-deleted rows excluded; filters applied consistently.

---

## 1. COGS (order_details only)

**Rules applied:**
- Source: `order_details` only.
- Per row: `quantity * cost_per_unit` (no ABS; `qty` kept positive).
- NULL `cost_per_unit`: treated as 0 via `COALESCE(order_details.cost_per_unit, 0)`.
- No join to `stock_logs`, `products` (for cost), or purchase tables.
- Soft-deleted: `order_details.deleted_at IS NULL` in join; orders excluded via `Order::query()` (SoftDeletes).
- Date: `orders.created_at` between report start and end.
- No extra grouping for the P&L total (one aggregate row).
- Single join orders → order_details so no duplicate rows.

**Clean SQL (COGS only):**

```sql
SELECT SUM(od.quantity * COALESCE(od.cost_per_unit, 0)) AS cogs
FROM orders o
INNER JOIN order_details od ON od.order_id = o.id AND od.deleted_at IS NULL
WHERE o.created_at BETWEEN ? AND ?
  AND o.deleted_at IS NULL
  AND o.shop_id IN (?)
```

**In context (Sales + COGS + Line Item Discounts in one query):**

```sql
SELECT
  SUM(od.unitcost * od.quantity) AS total_sales,
  SUM(od.quantity * COALESCE(od.cost_per_unit, 0)) AS cogs,
  SUM(COALESCE(od.item_discount, 0)) AS line_item_discounts
FROM orders o
INNER JOIN order_details od ON od.order_id = o.id AND od.deleted_at IS NULL
WHERE o.created_at BETWEEN ? AND ?
  AND o.deleted_at IS NULL
  AND o.shop_id IN (?)
```

---

## 2. Sales

- **Formula**: `SUM(order_details.unitcost * order_details.quantity)`.
- Same base query as COGS (one join, one row per line).
- Date: `orders.created_at`. Shop: `orders.shop_id IN (...)`.

---

## 3. Discounts

- **Invoice Discounts**: `SUM(orders.invoice_discount)` from **orders only** (no join to order_details) so each order is counted once.
- **Line Item Discounts**: `SUM(order_details.item_discount)` from the same orders + order_details query as Sales/COGS.

**Invoice discounts SQL:**

```sql
SELECT SUM(COALESCE(o.invoice_discount, 0)) AS invoice_discounts
FROM orders o
WHERE o.created_at BETWEEN ? AND ?
  AND o.deleted_at IS NULL
  AND o.shop_id IN (?)
```

---

## 4. Profit formula (implemented)

- **Gross Profit** = Total Sales − COGS.
- **Net Profit** = Gross Profit − Invoice Discounts − Line Item Discounts − Operating Expenses.

Operating Expenses remain from `account_transactions` (expense debits) with the same date/shop filters as before.

---

## 5. Eloquent usage

- **Sales + COGS + Line Item Discounts**: one `Order::query()->join('order_details', ...)->whereBetween('orders.created_at', ...)->selectRaw(...)->first()`.
- **Invoice Discounts**: `Order::query()->whereBetween('orders.created_at', ...)->sum('invoice_discount')` with same shop filter.
- **Operating Expenses**: unchanged (AccountTransaction, expense debits).
- `applyShopFilter()` uses `whereIn('orders.shop_id', $shopIds)` (or `stock_logs.shop_id` where applicable); no stock_logs in P&L anymore.

---

## 6. Edge cases

| Case | Handling |
|------|----------|
| NULL `cost_per_unit` | `COALESCE(cost_per_unit, 0)` so line contributes 0 to COGS (no fallback to buying_price). |
| NULL `item_discount` | `COALESCE(item_discount, 0)`. |
| NULL `invoice_discount` | `COALESCE(invoice_discount, 0)`. |
| COGS > Sales | Possible if `cost_per_unit` is wrong or selling price very low; formula does not inflate COGS. No automatic cap; fix data if needed. |
| Empty date range | Query returns NULL sums; controller casts to (float) 0. |
| Soft-deleted order | Excluded by `Order::query()` (SoftDeletes). |
| Soft-deleted order_detail | Excluded by `whereNull('order_details.deleted_at')` in join. |

---

## 7. Performance

- **Single aggregate query** for Sales, COGS, and Line Item Discounts avoids multiple passes over order_details.
- **Invoice discounts** from orders only avoids join and duplicate counting.
- **Indexes**: `orders(created_at, shop_id)`, `order_details(order_id, deleted_at)` improve the join and date/shop filter.
- **No stock_logs / products** in P&L COGS path reduces work and keeps logic simple.

---

## 8. Files touched

- `app/Http/Controllers/Dashboard/FinancialReportController.php`: `profitLoss()`, `profitLossLineDetail()`; removed StockLog; COGS/Sales/Discounts from order_details/orders; date on `orders.created_at`.
- `resources/views/reports/financial/profit-loss.blade.php`: Discounts section (Invoice + Line Item), use of `invoiceDiscounts`, `lineItemDiscounts`, `totalDiscounts`; Net Profit reflects new formula.
- `docs/COGS_AUDIT.md`: Can be updated to state COGS is now from order_details only (no stock_logs).

---

## 9. Print layout (full width)

**Scope:** Print only. Screen layout is unchanged.

**Goal:** When the user prints the P&L report, the table and statement use the full width of the printed page.

**Solution:** Use the shared report print pattern. See **`docs/REPORT_PRINT_FULL_WIDTH.md`** for the full solution (same as Cash Flow, Revenue, Sales reports). In `profit-loss.blade.php` the `@media print` block uses:

- `@page { margin: 8mm; size: auto; }`
- `html, body`, `.wrapper`, `.content-page` with `max-width: none` (not `100%`)
- `.container-fluid` and `.container-fluid .row`, `.col-lg-12` with `max-width: none` and zero padding/margin
- `.reports-profit-loss .card`, `.card-body`, `.pl-statement` with `max-width: none`
- `.reports-profit-loss .pl-table` with `width: 100%`, `min-width: 100%`, `max-width: 100%`, `table-layout: fixed`, `box-sizing: border-box`, `display: table`
- Expense detail lines: `min-width: 55%`, `white-space: nowrap`, `word-break: keep-all` so e.g. "2026-02-23 — Expense: #152" stays on one line.
