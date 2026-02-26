# COGS Implementation Audit

## Source of truth

- **Table:** `stock_logs` (Laravel model: `StockLog`).
- **COGS** = sum of cost of goods for sold items, from `stock_logs` rows that represent sales (out).

---

## Required business logic (verified)

1. **Include only rows where:**
   - `source_type = 'sale'`
   - `direction = 'out'`
   - `deleted_at IS NULL`

2. **Cost per row:**
   - If `cost_per_unit IS NOT NULL`: `cost = ABS(qty) * cost_per_unit`
   - Else: `cost = ABS(qty) * products.buying_price` (fallback)

3. **Grouping:** For P&L total we sum over all such rows in the date range (no per-order grouping needed for the single COGS figure). Per-order grouping is available via `source_id` if needed.

4. **Date filter:** Applied via join to `orders` on `stock_logs.source_id = orders.id` and `orders.order_date` between report start/end.

---

## Previous implementation (bugs)

- **COGS was not based on `stock_logs`.** It used `orders` + `order_details` + `products`:
  - `SUM(order_details.quantity * COALESCE(order_details.cost_per_unit, products.buying_price, 0))`
- So the implementation did **not** follow the specified logic (stock_logs only, sale/out, deleted_at excluded).
- **Possible double-count or mismatch:** If order_details and stock_logs ever diverge (e.g. one row type added without the other), COGS could be wrong. Using stock_logs as the single source avoids that.
- **deleted_at:** order_details used `whereNull('order_details.deleted_at')`; stock_logs was not used at all, so its soft deletes were not considered in COGS.

---

## Corrected implementation (current)

- **P&L COGS** is now computed from `stock_logs` only.

**Logic:**

- Use `StockLog::query()` so **SoftDeletes** (e.g. `deleted_at IS NULL`) is applied.
- Filter: `source_type = 'sale'`, `direction = 'out'`.
- Join `products` for `buying_price` fallback.
- Join `orders` for date filter: `orders.order_date` between report start and end.
- Cost expression: `ABS(stock_logs.qty) * COALESCE(stock_logs.cost_per_unit, products.buying_price, 0)`.
- Shop filter on `stock_logs.shop_id`.

**Why ABS(qty):**  
Schema/docs say `qty` is “positive integer only” with `direction` indicating in/out. Using `ABS(qty)` guards against any legacy or bad data with negative qty so cost stays non‑negative.

**No duplicate inflation:**  
One row per `stock_log` row; join to `products` and `orders` is 1:1 for each log row, so the sum is not doubled.

**Edge cases (COGS > revenue):**  
Can still happen if:
- `cost_per_unit` or `products.buying_price` is very high (data/input issue).
- Selling price is set very low.

The formula itself does not double-count or include non-sale/non-out or deleted rows.

---

## SQL (conceptual)

```sql
SELECT SUM(
  ABS(sl.qty) * COALESCE(sl.cost_per_unit, p.buying_price, 0)
) AS cost_of_goods_sold
FROM stock_logs sl
INNER JOIN products p ON p.id = sl.product_id
INNER JOIN orders o ON o.id = sl.source_id   -- source_id is string; MySQL casts for join
WHERE sl.source_type = 'sale'
  AND sl.direction = 'out'
  AND sl.deleted_at IS NULL
  AND o.order_date BETWEEN ? AND ?
  AND sl.shop_id IN (?)
```

---

## Eloquent (current code)

```php
$cogsQuery = StockLog::query()
    ->where('source_type', 'sale')
    ->where('direction', 'out')
    ->join('products', 'stock_logs.product_id', '=', 'products.id')
    ->join('orders', 'stock_logs.source_id', '=', 'orders.id')
    ->whereBetween('orders.order_date', [$start, $end])
    ->selectRaw('SUM(ABS(stock_logs.qty) * COALESCE(stock_logs.cost_per_unit, products.buying_price, 0)) as cost_of_goods_sold');
$this->applyShopFilter($cogsQuery, $shopFilter['shop_ids'], 'stock_logs.shop_id');
$cogs = (float) $cogsQuery->value('cost_of_goods_sold');
```

---

## P&L line-level detail

- **Line detail report** still uses `order_details` + `products` for per-line COGS (one row per order line). So:
  - **P&L total COGS** = from `stock_logs` (single source of truth).
  - **Line-level COGS** = from `order_details` (for display/debug). Totals there may not match P&L if the two sources ever diverge; consider aggregating from `stock_logs` by order/line if exact parity is required.

---

## Safer / optional checks

1. **Cap or flag high cost:** In reporting, you could flag or cap rows where `(cost_per_unit OR buying_price) > price` (selling price) so COGS > revenue is visible or bounded.
2. **Nullable buying_price:** `COALESCE(..., products.buying_price, 0)` treats NULL as 0 so missing buying price does not create NULL in the sum.
3. **source_id type:** `stock_logs.source_id` is string; `orders.id` is bigint. MySQL handles the join; if you see performance issues, consider an indexed cast or a stored integer column for `order_id` on `stock_logs`.
