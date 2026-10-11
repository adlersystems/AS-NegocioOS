# Phase 1 — Step 10 Plan

Status: ACTIVE — IMPLEMENTED

## 1. Objective and scope

Correct the handling of **fractional IVA percentages** across sale creation, sale editing, tax
calculation and historical storage so that a configured rate such as `12.5 %` is used and stored
exactly, instead of being silently truncated to `12 %`.

Scope is limited to the tax-percentage precision defect (B-5 from the Step 9 audit). The following
are explicitly **not** part of this step: the sale-cancellation/audit model (B-7), inventory,
authentication, API authorization, rounding-model redesign for non-tax amounts, dependency
upgrades, and general production hardening.

Step 9 (`546d0e1`, `a5fb378`) is treated as stable and is **not reopened**. The fix set below only
touches tax-rate precision; it does not alter any of the stock-write or snapshot behaviour hardened
in Step 9.

## 2. Verified findings

All findings below were confirmed by reading the current code at `a5fb378` (they are facts, not
hypotheses). No implementation change has been made.

### 2.1 Configuration path (supports decimals end-to-end)

- Stored in the generic `settings` table: `value` is `text` (migration
  `2026_01_01_000060_create_settings_table.php:17`), so any string rounds-trip.
- Default: `Setting::DEFAULTS['iva_percentage'] = '12'` (`app/Models/Setting.php:28`).
- Validation: `UpdateSettingsRequest` rule `['required', 'numeric', 'min:0', 'max:100']`
  (`app/Http/Requests/UpdateSettingsRequest.php:28`) — **decimals are already accepted**, and there
  is currently **no precision cap** (a value such as `12.5999` is accepted).
- Settings UI already uses `type="number" step="0.01" min="0" max="100"`
  (`resources/views/settings/edit.blade.php:91`).
- Seeder: `api` seeder stores `'12'` (`database/seeders/SettingsSeeder.php:24`).

**Conclusion:** there is nothing in the configuration path that truncates a fractional rate. The
defect is purely in the PHP casts listed below.

### 2.2 Sale creation truncates the rate (`SaleController::applyItems`)

`app/Http/Controllers/SaleController.php:445`:

```php
$iva = (int) Setting::get('iva_percentage', 12);
```

This is the **core defect**. With `iva_percentage = '12.5'`, `(int) '12.5'` yields `12`, so:

- `$tax = round($subtotal * ($iva / 100), 2)` (`:516`) computes tax at 12 %, not 12.5 %.
- `'tax_rate' => $iva` (`:522`) persists `12` (i.e. `12.00`) as the sale's historical rate.

The edit path (`reconcileItems:414`) and the seeder (`SampleDataSeeder.php:23`) already cast with
`(float)`, and `edit`/PDF read a stored rate with `(float)` — so **the two creation/edition paths
already disagree**: creation truncates, editing preserves. This asymmetry is exactly what the audit
predicted.

### 2.3 Display/fallback casts truncate in three more places

- `SaleController::create` `:62` — `'iva' => (int) Setting::get('iva_percentage', 12)` feeds the
  Alpine.js sale-form preview (`resources/views/sales/form.blade.php:23,163`) and `resources/js/sale.js:14,100-106`.
  With 12.5 configured the on-screen preview sub-total/tax/total are computed at 12 %.
- `SaleController::edit` `:114` — fallback for legacy `tax_rate = null` uses `(int)`; stored rates
  already use `(float)`.
- `SaleController::exportPdf` `:248` — invoice PDF label `($iva%)`
  (`resources/views/sales/invoice-pdf.blade.php:104`) truncates via the same int fallback.

### 2.4 API exposes the truncated value

`app/Http/Controllers/Api/SettingController.php:20`:

```php
'iva_percentage' => (int) $settings['iva_percentage'],
```

The mobile-app contract returns `12` for a configured `12.5`. (Read-only endpoint; the API has no
sale-creation path — `routes/api.php:15-37` is entirely read-only, confirmed in Step 9.)

### 2.5 Storage fully supports fractional rates

- `sales.tax_rate` is `decimal(5,2)` nullable (`2026_09_16_120000_...:21`), enough for
  `0.00 … 100.00`.
- `Sale` model casts `tax_rate => 'decimal:2'` (`app/Models/Sale.php:26`) and `subtotal` /
  `tax_amount` / `total` to `decimal:2` (`:23-25`).
- The historical backfill migration already rounds reconstructed rates to **one** decimal
  (`2026_09_16_...:63`), so legacy rows at `12.5` are already representable.

### 2.6 Rounding is otherwise consistent

- Creation: `subtotal` is accumulated from `price * quantity` (`applyItems:462`) — exact to 2 dp
  because `unit_price` is `decimal(12,2)` and `quantity` is an integer; `tax = round(subtotal *
  rate/100, 2)` (`:516`); `total = round(subtotal + tax, 2)` (`:521`); per-line totals rounded
  (`:498`).
- Editing: `subtotal = round(array_sum(line totals), 2)` (`:412`), `tax = round(subtotal *
  rate/100, 2)` (`:415`), `total = round(subtotal + tax, 2)` (`:420`). For the same lines both paths
  produce identical values (the sums are exact 2 dp), so no re-point of B-6.
- Client preview uses the same formula (`sale.js:100-106`). With the fractional rate after the fix
  it matches the server; residual half-cent float-boundary differences between `Math.round` and PHP
  `round()` in the *live preview* are a pre-existing cosmetic edge (the server value is
  authoritative) and are documented in §6.

## 3. Root cause

The configured rate is a **string** in `settings` (`'12.5'`). Two consumers cast it with `(int)` —
`SaleController::applyItems` (`:445`, authoritative) and the preview/display/API casts — which
truncates to a whole number before computation and before persistence. The storage layer
(`decimal(5,2)`) and the config layer already handle decimals; only the `(int)` casts discard them.

A secondary latent defect: there is no validation cap on decimals, so an over-precise configured
value (e.g. `12.5999`) would be used for tax `round()` while `tax_rate` truncates to `12.60` on
write, making the stored “snapshot rate” differ from the rate actually applied. Editing such a sale
would then recompute with `12.60`. This is closed by the validation tweak in §4.3.

## 4. Proposed changes (grouped by file)

### 4.1 `app/Http/Controllers/SaleController.php`

- `:445` — `$iva = (int) Setting::get('iva_percentage', 12);` → `$iva = (float) Setting::get('iva_percentage', 12);`
  **This is the authoritative fix**: tax and `tax_rate` now use the true fractional rate.
- `:62` — `(int)` → `(float)` for the create-form preview value.
- `:114` — legacy fallback `(int)` → `(float)` for the edit-form preview value (stored-rate branch
  already float).
- `:248` — legacy fallback `(int)` → `(float)` for the invoice-PDF label.

**Scope note (resolves any contradiction with §11):** the invoice-PDF IVA percentage label
(`resources/views/sales/invoice-pdf.blade.php:104`, `($iva%)`) is kept **in scope** because it
directly displays the same fractional tax rate this step is correcting — a truncated label would
keep showing `12%` on a `12.5%` invoice even after the calculation fix. This is a tax-precision
display correction, explicitly **not** report/export formatting. The excluded category (§11) is
unrelated presentational work on reports, the sales-list/Excel exports, or the invoice layout
itself (headers, tables, styling, column arrangement), none of which this step touches.

No rounding formula changes; `round(..., 2)` is preserved exactly.

### 4.2 `app/Http/Controllers/Api/SettingController.php`

- `:20` — `(int)` → `(float)`. `json_encode` serialises a whole float as the integer literal
  (verified: `json_encode((float)'15')` → `15`), so integer configs keep the current wire format
  while `12.5` is now returned as `12.5`.

### 4.3 `app/Http/Requests/UpdateSettingsRequest.php` (recommended, small)

- Tighten `iva_percentage` to at most two decimals so the applied rate always equals the stored
  `decimal(5,2)` rate: add `regex:/^\d{1,3}(\.\d{1,2})?$/` **in addition to** the existing
  `required`, `numeric`, `min:0`, `max:100` rules. This is scope-appropriate: it keeps the stored
  historical `tax_rate` an exact reflection of the rate used, preventing the `12.5999` vs `12.60`
  snapshot divergence described in §3.

  **Verified rule behaviour** (confirmed by executing the regex + range check against representative
  values):

  | Input | regex `^\d{1,3}(\.\d{1,2})?$` | `numeric` + `min:0`/`max:100` | Accepted |
  |---|---|---|---|
  | `12` | pass | pass | **yes** |
  | `12.5` | pass | pass | **yes** |
  | `12.50` | pass | pass | **yes** |
  | `0` | pass | pass | **yes** |
  | `100` | pass | pass | **yes** |
  | `100.00` | pass | pass | **yes** |
  | `12.5999` | **fail** | pass | **no** |
  | `100.01` | pass | **fail (max)** | **no** |
  | `999.99` | pass | **fail (max)** | **no** |
  | `12,5` | fail | **fail (not numeric)** | **no** |
  | `12.` | **fail** | pass | **no** |
  | `" 12"` (leading space) | **fail** | pass | **no** |
  | `''` / `abc` | **fail** | fail | **no** |

  The `0–100` range validation is fully preserved by the existing `min:0`/`max:100` rules; the regex
  only constrains decimal precision, permitting `1–3` integer digits and `0–2` decimal places.

### 4.4 No changes to

- `app/Models/Sale.php` (cast already correct), `app/Models/Setting.php`, migrations, routes,
  views (`settings/edit.blade.php` already `step="0.01"`), `resources/js/sale.js` (`Number()`
  handles decimals), `SaleFactory`, seeders (they already use `(float)` / string storage).

## 5. Regression test plan

Tests are additive, follow the existing `RefreshDatabase` SQLite conventions, and live in the
existing files that already own each concern. No existing assertion is weakened.

### 5.1 `tests/Feature/SaleHistoricalSnapshotTest.php` (tax snapshot semantics)

1. `test_new_sale_with_fractional_iva_stores_rate_and_computes_tax` — set `iva_percentage` to
   `'12.5'`, store `2 × 50`; assert subtotal `100.00`, **tax_rate `12.5`**, tax_amount `12.50`,
   total `112.50`, and `assertDatabaseHas('sales', ['id' => $sale->id])` with `tax_rate` persisted
   (fraction survives the `decimal:2` cast).
2. `test_editing_fractional_iva_sale_recomputes_at_the_stored_rate` — create at `12.5`, edit
   quantity `2 → 3`; assert subtotal `150.00`, tax `18.75`, total `168.75`, tax_rate still `12.5`;
   also assert the stock reconciliation (out movement `+1`) as in the existing pattern.
3. `test_fractional_iva_global_change_does_not_rewrite_history` — create at `12.5`, then
   `Setting::set('iva_percentage', '15')`, refresh; assert all monetary fields and `tax_rate` are
   unchanged (12.5 variant of the existing `test_iva_setting_change_does_not_affect_existing_sale`).
4. `test_fractional_iva_tax_rounds_to_two_decimals` — price `33.33`, qty `1`, rate `12.5`;
   `round(33.33 * 0.125, 2) = 4.17`; assert tax_amount `4.17`, total `37.50`. Deterministic
   rounding proof at the stored rate.
5. `test_create_form_preview_carries_fractional_iva` — set `12.5`, `GET sales.create`; assert the
   rendered Alpine config contains `\u0022iva\u0022:12.5` and the label `(12.5%)`
   (`form.blade.php:163`). (Existing whole-value test at `test_edit_form_carries_...` stays green —
   `json_encode(12.0)` yields `12`.)
6. `test_invoice_pdf_label_uses_fractional_stored_rate` — store a sale at `12.5` and verify the
   invoice PDF label renders `(12.5%)`.

   **Least-brittle approach (matches the existing `test_invoice_pdf_label_uses_stored_tax_rate`,
   `SaleHistoricalSnapshotTest.php:295-316`):** do **not** exercise the DomPDF download route
   (binary payload, slow, and it would require resetting company settings for the `$company` array);
   instead render the `sales.invoice-pdf` view directly via `view(...)->render()`, exactly as the
   existing test does. Pass `'iva' => (float) $sale->tax_rate` — i.e. derive the label value from
   the **recorded stored rate**, the same value the corrected `exportPdf` would pass for a sale with
   a stored rate — and assert the rendered HTML contains `(12.5%)` and not `(12%)`.

   The test deliberately avoids three brittle patterns:
   - it does **not** copy the controller's full fallback ternary into the test (a stored rate
     guarantees the fallback branch is never taken, so only the stored-rate expression matters);
   - it does **not** assert against the binary PDF directly;
   - it does **not** depend on the global `iva_percentage` setting (the stored snapshot is the
     source of truth, mirroring §6 snapshot semantics).

   The existing whole-value case at `test_invoice_pdf_label_uses_stored_tax_rate` remains
   unchanged; this adds only the fractional case.

### 5.2 `tests/Feature/SettingTest.php` (config persistence)

7. `test_admin_can_set_a_fractional_iva_that_persists` — POST settings with `iva_percentage`
   `'12.5'`; assert `Setting::get('iva_percentage') === '12.5'`.
8. `test_iva_precision_capped_to_two_decimals` — POST `'12.5999'` → `assertSessionHasErrors('iva_percentage')`;
   POST `'12.5'` → no error. (Guards §4.3.)

### 5.3 `tests/Feature/ApiEndpointsTest.php` (API contract)

9. `test_settings_returns_fractional_iva` — set `'12.5'`; `GET /api/settings` →
   `assertJsonPath('currency.iva_percentage', 12.5)`. The existing
   `test_settings_returns_company_defaults_and_currency` (`:198`) is **unaffected** because
   `json_encode((float)'15')` remains `15`.

### 5.4 MySQL group (optional, no engine-specific change expected)

The fix is engine-agnostic. One cheap regression: extend
`tests/Feature/MysqlConcurrencyTest` lifecycle/oversell happy-path with a `12.5` assertion to prove
the decimal round-trips on MySQL (currently asserted only at `12%`, `:60-61`). Marked optional in
the record if the suite count is kept stable.

No redundant duplication: creation tax math, editing tax math, snapshot immutability, API output
and settings validation are each covered once.

## 6. Rounding and historical-data considerations

- **Rounding rules are unchanged.** Both paths already use `round($subtotal * ($rate / 100), 2)`
  and `round($subtotal + $tax, 2)`. This plan only removes the truncation before that rounding.
- **Stored snapshot semantics are unchanged.** New sales capture the exact applied rate into
  `tax_rate`; editing recomputes from the *stored* rate (`reconcileItems:414`), never the current
  global setting; global IVA changes never rewrite existing sales (`test_iva_setting_change_...`).
  Legacy `tax_rate = null` rows keep the documented behaviour: preserved on non-monetary edits,
  captured from the **current** setting (as float) when lines change.
- **Float-boundary preview caveat (pre-existing, not fixed).** `sale.js` computes
  `Math.round(subtotal * iva / 100 * 100) / 100`; PHP uses `round(..., 2)`. For exact 2 dp
  subtotals and 2 dp rates the results agree; only rare `x.xx5` third-decimal boundaries can diverge
  by a cent in the live preview. The server is authoritative and remains unchanged. Documented, not
  altered, to avoid scope creep.
- **No backfill.** Existing sales created with a truncated rate were taxed and recorded at the
  integer rate; they are historical artefacts and are deliberately left untouched (changing them
  would falsify recorded tax). Forward-only fix.

## 7. Migration assessment

**No migration is required.** Both relevant columns already support fractions:
`sales.tax_rate decimal(5,2)` and `settings.value text`. Nothing in this plan alters schema,
indexes, or constraints. `php artisan migrate:status` will show all 16 migrations already `Ran`,
with none pending.

## 8. Risks and compatibility considerations

| Item | Assessment |
|---|---|
| Existing whole-number behaviour | Unchanged. `json_encode((float)'15')` → `15` (verified above), so API payloads, form previews and PDF labels for integer IVA values produce byte-identical output. |
| Existing tests | All current assertions that involve `12%`/`15%`/`iva` stay green (verified semantics above). Only additive tests are introduced. |
| API contract type drift | A same-type-preserving `(float)` cast; fractional values now emitted correctly. No endpoint schema change. |
| Historical snapshot integrity | Preserved exactly (§6); Step 9 ledger hardening untouched. |
| Over-precise configs | Closed by the recommended §4.3 validation rule; without it the applied-vs-stored divergence described in §3 remains possible. |
| Mobile/API consumer of `iva_percentage` | Was `(int)`; becomes the true value. For whole configs the wire value is still `15`; only fractional configs change (previously wrongly `12`). |
| Non-tax rounding redesign | Out of scope; `round()` semantics and the audit-trail amounts are unchanged. |

## 9. Validation commands

Baseline at the start of this step (from the Step 9 record): SQLite suite **279 tests / 1,397
assertions**, MySQL group **7 tests / 61 assertions**, dev MySQL metrics (sales 180, sale_items 450,
products 37, product_trashed 0, inventory_movements 487, etc.).

All commands are executed with the repository's own scripts, on Windows PowerShell (`vendor\bin\...`):

```powershell
composer test                 # config:clear + php artisan test  (SQLite)
vendor\bin\phpunit -c phpunit.mysql.xml   # MySQL concurrency group
vendor\bin\pint --test        # formatting; run `vendor\bin\pint` if it flags files
php -l <each changed file>    # lint changed PHP files
php artisan migrate:status    # expect 16 migrations Ran, none pending
```

A read-only dev-DB metrics probe (same technique and values as Step 9) is captured **before** and
**after** to prove the step mutates no dev data.

## 10. Acceptance criteria

1. A configured IVA of `12.5 %` yields `tax_amount` and `total` computed at 12.5 % on creation,
   and `sales.tax_rate` persists `12.50`.
2. The create-form preview and the API `currency.iva_percentage` both surface `12.5` for a
   fractional config, and remain `12`/`15` for whole-number configs.
3. Editing a 12.5 % sale recomputes tax at the stored 12.5 % rate; quantities, stock and movements
   reconcile exactly as before.
4. Changing the global IVA after a sale does not alter that sale's stored rate/amounts.
5. Tax amounts and totals round to 2 decimals deterministically at fractional rates.
6. Whole-number behaviour is unchanged (byte-identical preview/API/PDF output for `12`/`15`).
7. `composer test`, the MySQL group, `pint --test`, `php -l` and `migrate:status` are all green;
   dev-DB metrics before == after; no new migrations.

## 11. Explicit exclusions

- B-7 — sale cancellation/audit model; line-level cancellation trail.
- Any rounding-model redesign for non-tax arithmetic or half-up/half-even changes.
- Backfilling or rewriting historical sale tax values created with the old truncation.
- Inventory, authentication, throttling, authorization, API write paths, and **report/export
  formatting** — defined as presentational work on report views/layouts, sales-list PDF/Excel
  exports, and the invoice layout (headers, tables, styling); the invoice-PDF tax-rate **label
  correction in §4.1 is a tax-precision change and stays in scope**, distinct from this category.
- Dependency upgrades and general production hardening.
- Step 9 work is not reopened; only the additive tax-precision changes above are made.

## 12. Implementation sequence

1. Apply §4.1 (four `(float)` casts in `SaleController`), §4.2 (API cast), §4.3 (validation rule).
2. Add the additive regression tests from §5.
3. Run the full validation block (§9); capture after-metrics and compare to before.
4. Run `vendor\bin\pint` on changed files (allowed to reformat only changed lines).
5. Commit as two commits on `main`, then push to `origin/main`:
   - `fix: preserve fractional IVA rates in sale tax calculation`
   - `docs: record step 10 implementation evidence (update this plan to ACTIVE + implementation record)`
6. Report evidence: counts before/after, MySQL results, Pint/lint/migration status, dev-DB
   before/after metrics, and the final git state.

---

## 13. Implementation record

Executed on `main`, starting from `a5fb378` (Step 9). Scope was limited to the fractional IVA
precision fix (B-5) exactly as approved in §§4–5. No migration was written or applied.

### 13.1 Commits

| Order | Hash | Subject |
|---|---|---|
| 1 | `75a1274432194faf38169cda952f4de244c97187` | `fix: preserve fractional IVA rates in sale tax calculation` |
| 2 | (this commit) | `docs: record step 10 implementation evidence` |

### 13.2 What changed

- `app/Http/Controllers/SaleController.php` — four `(int)` → `(float)` casts: the authoritative
  creation rate in `applyItems` (`:442`), the create-form preview (`:62`), and the legacy null-rate
  fallbacks in `edit` (`:114`) and `exportPdf` (`:248`). Tax formulas (`round(..., 2)`), totals,
  movement/stock logic, and snapshot semantics are unchanged.
- `app/Http/Controllers/Api/SettingController.php` — `iva_percentage` returned via `(float)` so
  `12.5` is exposed correctly; whole values keep their existing JSON representation
  (`json_encode((float)'15')` → `15`).
- `app/Http/Requests/UpdateSettingsRequest.php` — added `regex:/^\d{1,3}(\.\d{1,2})?$/` alongside
  the existing `required`, `numeric`, `min:0`, `max:100` rules, capping `iva_percentage` at two
  decimals. `12`, `12.5`, `12.50`, `0`, `100.00` accepted; `12.5999`, `100.01` and out-of-range
  values rejected; unrelated settings rules untouched.

No migrations, no backfill, no rounding redesign, no report/export/layout changes, no
authentication/authorization/inventory changes (Step 9 behaviour intact).

### 13.3 Regression tests added

`tests/Feature/SaleHistoricalSnapshotTest.php`:
`test_new_sale_with_fractional_iva_stores_rate_and_computes_tax`,
`test_editing_fractional_iva_sale_recomputes_at_the_stored_rate`,
`test_fractional_iva_global_change_does_not_rewrite_history`,
`test_fractional_iva_tax_rounds_to_two_decimals`,
`test_create_form_preview_carries_fractional_iva`,
`test_invoice_pdf_label_uses_fractional_stored_rate` (direct view render, the project's existing
least-brittle pattern).

`tests/Feature/SettingTest.php`: `test_admin_can_set_a_fractional_iva_that_persists`,
`test_iva_precision_capped_to_two_decimals` (accepts `12`/`12.5`/`12.50`/`0`/`100.00`, rejects
`12.5999`/`100.01`).

`tests/Feature/ApiEndpointsTest.php`: `test_settings_returns_fractional_iva` (API exposes `12.5`).

`tests/Feature/MysqlConcurrencyTest.php`: `test_fractional_iva_round_trips_on_mysql` (optional
MySQL regression; fractional rate persists through store then edit).

### 13.4 Verification results (actually executed)

| Check | Command | Result |
|---|---|---|
| Full SQLite suite | `composer test` | **288 passed / 288**, 1,453 assertions (was 279 / 1,397) |
| MySQL concurrency group | `vendor\bin\phpunit -c phpunit.mysql.xml` | **8 passed / 8**, 70 assertions (was 7 / 61) |
| Formatting | `vendor\bin\pint --test` | passed |
| Syntax | `php -l` on the 7 changed PHP files | no syntax errors |
| Migrations | `php artisan migrate:status` | 16 migrations, all `Ran`, none pending |

### 13.5 Development database integrity

Read-only metrics captured immediately before and after the work against the dev MySQL database
(`as_negocios`); they were **identical**, confirming the implementation touched no dev business data
(tests run against the in-memory SQLite and `as_negocios_test` MySQL databases only):

```
sales 180 · sale_items 450 · products 37 · products_trashed 0 · clients 22
SUM(subtotal) 131404.99 · SUM(total) 147173.59 · SUM(tax_amount) 15768.60
SUM(inventory_movements.quantity) 3732 · inventory_movements 487
personal_access_tokens 0 · audit_logs 0
```

### 13.6 Deviations from the plan

None. All §4 changes and §5 regression tests were implemented as approved; the optional MySQL
fractional regression test was included. No pre-existing assertion was weakened.

---

IMPLEMENTATION STATUS: IMPLEMENTED
APPROVAL REQUIRED: NO