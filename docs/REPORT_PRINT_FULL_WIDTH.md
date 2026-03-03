# Report print full-width — solution

When a report table does not stretch to full page width on print, use this pattern (same as **Cash Flow**, **Revenue**, **Sales Daily/Customer/Product**, **Stock** reports).

## Apply only inside `@media print { ... }`

1. **Page**
   ```css
   @page { margin: 8mm; size: auto; }
   ```

2. **Reset layout**
   ```css
   html, body { width: 100% !important; margin: 0 !important; padding: 0 !important; }
   .wrapper { display: block !important; width: 100% !important; }
   .content-page { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; }
   body, .wrapper { padding: 0 !important; margin: 0 !important; }
   ```
   **Important:** Use `max-width: none` (not `100%`) on `.content-page` so the content can use full printable width.

3. **Container and grid**
   ```css
   .container-fluid { width: 100% !important; max-width: none !important; padding-left: 0 !important; padding-right: 0 !important; }
   .container-fluid .row { margin-left: 0 !important; margin-right: 0 !important; width: 100% !important; }
   .container-fluid .row .col-lg-12 { padding-left: 0 !important; padding-right: 0 !important; max-width: none !important; }
   ```

4. **Card and table wrapper**
   - Card: `width: 100% !important; max-width: none !important;`
   - Card body: `width: 100% !important; max-width: none !important; padding: 8px !important; box-sizing: border-box !important;`
   - Table wrapper (if any): `width: 100% !important; max-width: none !important; overflow: visible !important; display: block !important;`

5. **Table (so it stretches)**
   ```css
   .your-table-class { width: 100% !important; min-width: 100% !important; max-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; display: table !important; }
   ```

6. **Hide chrome**
   ```css
   .iq-sidebar, .iq-top-navbar, .iq-footer,
   .report-filter-card, .d-print-none, .btn { display: none !important; }
   ```

7. **Print color**
   ```css
   body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
   ```

## Reference files

- `resources/views/reports/financial/cash-flow.blade.php` — full pattern including table `#cashFlowReportTable`
- `resources/views/reports/financial/revenue.blade.php` — same layout pattern
- `resources/views/reports/financial/profit-loss.blade.php` — uses this for `.pl-table`
- `resources/views/reports/sales/daily.blade.php`, `customer.blade.php`, `product.blade.php` — same idea with `.container-fluid`
- `resources/views/suppliers/ledger.blade.php` — uses `100vw` + `left: 50%; margin-left: -50vw` for table wrapper when needed

## Profit & Loss specific

- Report container has class `reports-profit-loss` on `.container-fluid`.
- Table has class `pl-table`; statement wrapper has class `pl-statement`.
- Expense detail rows (`.pl-expense-detail`) are shown in print with `display: table-row !important`.
- First column and expense detail label: `min-width: 55%`, `white-space: nowrap`, `word-break: keep-all` so "2026-02-23 — Expense: #152" stays on one line.
