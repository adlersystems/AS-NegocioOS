# Phase 1 — Step 2: Historical Sale Data Integrity (Implementation Plan)

Status: ACTIVE (implemented and merged to `main` on 2026-09-16).
Scope: preserve the money recorded at sale time against later Product and Setting changes.
No ledgers, no tax engines, no currency, no versioning, no event sourcing, no CQRS.

---

## 1. Current implementation — what is stored vs derived

| Piece | Stored | Derived |
|---|---|---|
| Line amount | `sale_items.total` (persisted) | — |
| Line unit price | `sale_items.unit_price` (persisted, **but overwritten on edit**) | — |
| Line cost | — | `products.production_cost` (current), only at report time |
| Sale subtotal | `sales.subtotal` (persisted, **recomputed on edit**) | — |
| Sale tax amount | `sales.tax_amount` (persisted, **recomputed on edit**) | — |
| Tax rate | — | `settings.iva_percentage` (current) at every point |
| Sale total | `sales.total` (persisted, **recomputed on edit**) | — |
| Paid status | `sales.paid` | — |
| Product margin (current) | — | `products.sale_price - products.production_cost` |
| Historical margin (report) | — | `SUM(sale_items.total) - SUM(quantity) * products.production_cost` (current cost) |

Flow summary:
- `applyItems()` (`SaleController.php:349`) records `unit_price = product->sale_price` and computes `tax = round(subtotal * (iva/100), 2)` with the *current* setting (`:351`, `:413`). Correct at creation, but nothing historical is kept beyond the money amounts.
- `reconcileItems()` (`SaleController.php:230`) is the reprice-on-edit path. The docblock at `:226` says *"Items are rebuilt and totals recomputed from the authoritative prices."* It loads current products with `lockForUpdate()` (`:244-251`), always deletes all items (`:303`), rebuilds them at `$price = product->sale_price` (`:309-316`) and recomputes `subtotal/tax/total` from the *current* `iva_percentage` (`:232`, `:322`).
- `update()` (`:107-126`) calls `reconcileItems()` for *any* edit — even a notes-only change — so every edit rewrites money.
- The edit UI feeds the same behavior: `sales/form.blade.php:24-28` ships only `product_id/quantity/original_quantity` to the JS; `resources/js/sale.js:26` prices each line from the *current* product price (`product ? Number(product.price) : ...`).
- Invoice PDF (`sales/invoice-pdf.blade.php:104`) labels the tax row as `({{ $iva }}%)` where `$iva` is the *current* setting (`SaleController.php:173`).

## 2. Confirmed problems

Each verified against the actual code.

- **P1 — Editing a sale silently reprices and re-totals past money — HIGH**
  `SaleController.php:309` + `:303` + `:321-328` (edit path), regardless of which fields changed. `sales`/`sale_items` money are therefore not immutable.
  Example: sale #5 recorded product X @ Q10, total Q11.20 (12%). Price rises to Q15. Admin fixes a typo in the notes → every line is rebuilt at Q15, subtotal/tax/total change. The historical record is rewritten.

- **P2 — IVA rate is never persisted — MEDIUM**
  No `tax_rate` column on `sales`. `SaleController.php:232/351/413` compute tax from the current `iva_percentage`; `:173` and `:103` and `sales/form.blade.php:152` display the current rate.
  Example: admin changes IVA 12% → 15% (`settings/edit.blade.php` allows any 0-100 value). Re-printing an old invoice shows `(15%)` next to a tax_amount computed at 12%. Editing any old sale recomputes its tax at 15%.

- **P3 — Cost is never captured; profitability report drifts — MEDIUM**
  No cost on `sale_items`. `ReportService::productsReport` (`ReportService.php:138-157`) computes `cost = quantity * products.production_cost` (current) and `margin = revenue - cost`. `ReportController.php:122-124` and the PDF/Excel exports reuse that margin.
  Example: a product's cost rises 10 → 14. The products profitability report for past months now shows deflated margins even though margins at the time were different. Historical profit becomes unverifiable.

- **P4 — Edit form cannot display the recorded unit price — MEDIUM (UI, part of P1)**
  `sales/form.blade.php:24-28` drops `unit_price`; `sale.js:26` derives price from the current product. Users editing an old sale see today's price, not the recorded one.

- **P5 — API returns price 0 for every line item — LOW (adjacent bug, found during verification)**
  `app/Http/Controllers/Api/SaleController.php:73` reads `$item->price`; `SaleItem` has no `price` column or accessor (`app/Models/SaleItem.php`), so `(float) $item->price` is always `0.0`. The correct source is `unit_price`.

Not a problem (no change needed): `indexMetrics` inventory value (`SUM(stock * production_cost)`, `ReportService.php:35-37`), `inventoryReport` value (`ReportService.php:96`) and `DashboardController.php:67` are *current* valuations — correct as-is. `salesReport`/`clientsReport`/dashboard totals read stored `sales.subtotal/tax_amount/total` — already historical.

## 3. Snapshot data required

Per sale:
- `tax_rate` (decimal, %, single value — rate legally applicable to the whole sale; no per-line rates).

Per sale line:
- `cost` (decimal) — unit cost paid at the time of sale (for historical margin).

`unit_price` and `total` already exist — they must simply stop being overwritten.

## 4. Proposed schema (snapshot column migration)

New migration (appended after `2026_09_05_113500_add_paid_to_sales_table.php`):

```php
Schema::table('sales', function (Blueprint $table) {
    $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount');
});

Schema::table('sale_items', function (Blueprint $table) {
    $table->decimal('cost', 12, 2)->nullable()->after('unit_price');
});
```

- Both columns **nullable** to keep migration low-risk, fast on SQLite/MySQL, and to survive rollback cleanly (`dropColumn`).
- No separate snapshot table: one row per sale line already exists; adding two columns is the smallest surface that satisfies the requirement (no multi-rate/multi-currency).

## 5. Fill policy & backfill

**Going forward (new sales):**
- `sales.tax_rate` = `Setting::get('iva_percentage', 12)` at creation time.
- `sale_items.cost` = `product->production_cost` at creation time (read from the same locked product rows already fetched in `applyItems`).

**Backfill (migration `up`, run on existing DBs incl. the 180-sale demo/dev data):**
- `sales.tax_rate` = derived from stored money: `round(tax_amount / subtotal * 100, 2)` when `subtotal > 0` and `tax_amount > 0`; else `0` (covers tax-exempt fixtures that store `tax_amount = 0`). This reconstructs the rate that *actually* produced the stored tax, so it is exact for existing rows regardless of what the setting was.
- `sale_items.cost` = the row's product `production_cost` looked up **with trashed** (`withTrashed()`), fallback `0` only if the product row is gone entirely (soft-deleted rows are still retrievable, so fallback is rare). Demo/seed data was created from current product values, so this backfill is an exact match there.
- Backfill runs as plain `DB::table` updates inside the migration, chunked for safety.

**Rollback:** a single `down()` that drops both columns; no data loss (snapshots live only in the new columns).

## 6. Destroy / refund behavior

- `destroy()` (`SaleController.php:139-165`) deletes the sale and its items and writes stock-restore `IN` movements. The snapshots die with the rows — nothing extra needed.
- Quantity reductions in an *edit* (aka implied partial refund) are handled by the edit policy (Scenario B/D) and restore stock exactly as today (`reconcileItems` `:279-294`).

## 7. Edit policy (Scenarios A–D)

The controller must stop the "always rebuild from authoritative prices" behavior. `update()` will compare the submitted lines with the existing lines and apply:

- **Scenario A — No line change (notes/client/seller/paid only):** skip item rebuild entirely; keep `subtotal/tax_amount/total` and all snapshots untouched. Only update the non-monetary fields.
- **Scenario B — Quantity changed on an existing line:** for each line present before *and* after, keep the recorded `unit_price` and `cost`; recompute only `total = round(unit_price * new_qty, 2)`. Tax rate stays the sale's stored `tax_rate`.
- **Scenario C — New line added:** price the new line at the *current* `product->sale_price` and snapshot `cost = product->production_cost` (correct — the sale is being changed today). Mixed old/new prices inside one sale are fine because tax stays single-rate (stored `tax_rate`).
- **Scenario D — Line removed:** drop the line, restore stock, recompute subtotal/tax from remaining lines at their stored `unit_price`/`tax_rate`.

Tax rule for all scenarios: recompute `tax = round(subtotal * sale->tax_rate / 100, 2)` using the **stored** `tax_rate` (or 0 when stored tax was 0) — never the current setting. The stored rate only changes if it is NULL (legacy/backfill-not-run) → then fall back to the current setting and write it in.

Rounding drift: on any monetary recompute the stored `subtotal/tax_amount/total` are overwritten with freshly computed values; uniform rounding is applied so no drift accumulates across repeated edits.

## 8. Seeder / factory changes

- `SaleFactory`: add `tax_rate` default `'12'`.
- `SaleItemFactory`: add `cost` default derived from the product (`fn () => ...Product::factory()->production_cost` or an explicit state); nullable by default so tests can control it explicitly.
- `SampleDataSeeder`: write `cost = $product->production_cost` on items and `tax_rate = 12` on sales so freshly seeded DBs are snapshot-complete without relying on the backfill migration.

## 9. Reporting / export / view impact

- `ReportService::productsReport` (`:138-157`): cost becomes `SUM(sale_items.cost * sale_items.quantity)` from stored snapshots; `margin = revenue - cost`. The join to `products` stays only for `name/sku`. Existing output keys (`id/name/sku/quantity/revenue/cost/margin`) unchanged → PDF/Excel (`ReportController.php:122-124`, `reports/export-pdf.blade.php`, `reports/partials/products.blade.php`) keep working untouched.
- `salesReport`/`clientsReport`/dashboard metrics: already historical via stored `sales` money — unchanged.
- `inventoryReport` value and `indexMetrics`: current valuations — unchanged.
- `sales/invoice-pdf.blade.php:104`: tax label uses `$sale->tax_rate ?? current setting` instead of always current.
- `sales/form.blade.php:23-28` + `sale.js`: pass recorded `unit_price` and the sale's stored `tax_rate` into the component; `buildItem` uses `data.unit_price` when the line is pre-existing, and current product price only for genuinely new lines; the tax preview label uses the stored rate.
- API `Api\SaleController`: fix P5 (`unit_price`), and add `tax_rate` + per-item `cost` as **new** keys (additive, backward compatible).

## 10. Tests

Unit/feature (SQLite), same suite structure as Step 1:
- `store` persists `tax_rate` and per-item `cost` snapshots.
- Scenario A: notes-only edit keeps every money column byte-identical.
- Scenario B: quantity edit keeps `unit_price`/`cost`, recomputes only line/sale totals at stored rate.
- Scenario C: added line priced at current `sale_price`; tax still at stored rate.
- Scenario D: removed line restores stock and recomputes totals.
- IVA change does NOT affect an existing sale's edit or reprint label.
- `productsReport` margin uses snapshot cost, not current `production_cost` (adjust the current `ReportTest::test_products_report_aggregates_units_revenue_and_margin` which asserts cost-based-on-current).
- Migration backfill test: rows with `subtotal>0/tax>0` get derived `tax_rate`; product cost copied.
- P5: `Api\SaleController@show` returns non-zero `price` per item.

Verification: `composer test` (SQLite) + `phpunit.mysql.xml` (MySQL group) + `vendor\bin\pint`. Dev DB `as_negocios` untouched; only migration backfill runs against it.

## 11. Files to change (implementation phase)

1. New migration (columns + backfill)
2. `app/Models/Sale.php` — `tax_rate` cast
3. `app/Models/SaleItem.php` — `cost` cast
4. `app/Http/Controllers/SaleController.php` — `applyItems` writes snapshots; `reconcileItems` → scenario logic; `edit`/`create`/`exportPdf` pass stored rate
5. `resources/views/sales/form.blade.php` — historical items + stored rate
6. `resources/js/sale.js` — honor recorded `unit_price`/rate
7. `resources/views/sales/invoice-pdf.blade.php` — stored rate label
8. `app/Services/ReportService.php` — snapshot-cost margin
9. `app/Http/Controllers/Api/SaleController.php` — P5 fix + additive fields
10. `database/factories/SaleFactory.php`, `SaleItemFactory.php`
11. `database/seeders/SampleDataSeeder.php`
12. Tests (Section 10)
13. This document → status ACTIVE

## 12. Risks & edge cases

- Reprocessed zeros: sales with `subtotal=0` get `tax_rate=0` on backfill (matches their stored money). If later edited, tax stays 0 until the sale actually has revenue — acceptable.
- Soft-deleted products: retained lines keep price/cost snapshots, so a deleted product's history stays reportable (join remains, rows are not dropped). Backfill uses `withTrashed`.
- Legacy NULL columns: if any row still has `tax_rate = NULL` (e.g. migration skipped), all consumers fall back to the current setting — no crash.
- Rounding drift across repeated edits: eliminated by uniform recompute `round(x, 2)` everywhere.
- Concurrency: Step 1 locks remain intact; Step 2 adds no new locking but the read-modify-write of snapshots happens inside the existing `DB::transaction`.
- Behavior change for users: the docblock at `:226` ("authoritative prices") documents the *old* intent. Repricing existing lines is replaced by freeze-at-original. New lines still follow current prices. This is a deliberate, documented product-behavior change.
- No impact on `togglePaid`, exports lists (`SalesExport` reads stored money), or client pending balances (stored totals).

## 13. Implementation order

1. Review/approve this plan.
2. Migration + backfill; run against dev DB + fresh DB.
3. Models (casts) + `SaleController` store path (snapshots).
4. `SaleController` edit path (Scenarios A–D) + views + JS.
5. `ReportService` margin + invoice PDF label + API fix/additions.
6. Factories + seeder.
7. New tests + adjust affected existing tests.
8. `composer test`, `phpunit.mysql.xml`, Pint; commit & push.

## 14. Final implementation notes & verification (2026-09-16)

Differences from the draft as-approved (approved during review):
- Backfill policy finalized: `tax_rate = round(tax_amount / subtotal * 100, 1)` only when `subtotal > 0 AND tax_amount > 0`; rows with `subtotal > 0 AND tax_amount = 0` AND rows with `subtotal = 0` keep `tax_rate = NULL` (never invent 0%). NULL is preserved on non-monetary edits; the current IVA is captured only when lines actually change.
- Backfilled `tax_rate`/`cost` are documented as **reconstructed** approximations (9 records on dev showed 11.99/12.01 due to seeder rounding that produced the stored tax); everything written after this migration is a true snapshot.
- `sale_items.cost` backfill reads product `production_cost` via raw DB (`withTrashed` equivalent), fallback 0 only if the product row is gone.
- `reconcileItems()` freezes existing lines at stored `unit_price`/`cost`; only genuinely new lines are priced at current values.
- Migration: `2026_09_16_120000_add_historical_snapshots_to_sales_and_sale_items.php` (columns `sales.tax_rate` decimal(5,2) nullable after `tax_amount`; `sale_items.cost` decimal(12,2) nullable after `unit_price`; public `backfillTaxRates()` / `backfillItemCosts()`; `down()` drops both).
- License: reporting/docs/scope unchanged from plan; no ledgers/tax engines/currency/versioning were added.

Verification evidence:
- `composer test` (SQLite): **194/194 passed, 848 assertions**.
- `phpunit.mysql.xml` (MySQL group): **3/3 passed, 21 assertions**.
- `vendor\bin\pint --test`: **passes** (whole repo; `--dirty` fixes applied before final run).
- Dev DB `as_negocios` (MySQL) — pre/post migration fingerprint identical:
  - counts unchanged: 180 sales / 450 sale_items / 37 products.
  - `SUM(subtotal)=131404.99`, `SUM(tax_amount)=15768.60`, `SUM(total)=147173.59` (unchanged by migration).
  - `tax_rate`: 180/180 populated (176 × 12.00, 1 × 11.99, 3 × 12.01); **0 NULL**.
  - `cost`: 450/450 populated; **0 NULL**; 0 rows where `cost <> products.production_cost`.
  - Migration reversibility proven: `migrate:rollback --step=1` then re-`migrate` restored identical state (counts + sums + 0 NULLs).
- New tests cover: store snapshots, scenarios A–D, IVA-change immutability, report margin from snapshot cost, PDF stored-rate label, edit form ships recorded `unit_price` + stored `iva`, API `unit_price` fix + additive `tax_rate`/`cost`, backfill incl. NULL-cases.

Commit: `feat: snapshot sale price cost tax for historical integrity` — single commit, pushed to `origin/main`.