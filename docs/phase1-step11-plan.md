# Phase 1 — Step 11 Plan

Status: ACTIVE — IMPLEMENTED
Author: audit performed on `main` at `c948d50`
Date: 2026-10-19
Scope type: read-surface historical-data integrity (no migration)

---

## 1. Objective and scope

Identify and implement the single most appropriate bounded hardening task for Phase 1, Step 11,
based on a fresh, evidence-based audit of the current repository rather than an automatic carry-over
of an older deferred finding.

**Recommended scope (subject to approval):** correct two confirmed read-surface defects that present
historical business data incorrectly to users:

- **E-6** — the **inventory report silently zeroes archived products** (name rendered as “Ninguno”,
  stock value rendered as `0`), understating inventory valuation.
- **E-8** — the **API read surfaces expose the live client identity** (`name`/`nit`) instead of the
  stored client-identity snapshots, contradicting the snapshot contract already used by the web UI
  and creating an internal inconsistency inside the same API dashboard response.

Both are historical-data-integrity errors (the first-listed audit area), both are confirmed against
the current code, both are decision-light, both require **no migration**, and both are independently
testable.

This document was approved and has since been implemented on `main` (see “Implementation record” in
§16 for evidence, counts, metrics and commit hashes).

---

## 2. Verified repository baseline

Observed directly (not assumed):

- Branch `main`, working tree **clean**, no uncommitted or staged changes.
- `HEAD = c948d500e4602b836f849b4f29231404933e4be0` (`docs: record step 10 implementation evidence`).
- `git status -sb` → `## main...origin/main` (synchronized; no ahead/behind), confirmed after
  `git fetch origin`. `git diff origin/main` is empty.
- Recent history (newest first):
  - `c948d50` docs: record step 10 implementation evidence
  - `75a1274` fix: preserve fractional IVA rates in sale tax calculation
  - `a5fb378` docs: record step 9 implementation evidence
  - `546d0e1` fix: harden inventory and sale write integrity
- Docs present: `docs/phase1-step2..10-plan.md`, `docs/mysql-concurrency-verification.md`,
  `docs/manual-en.md`, `docs/manual-es.md`. No Step 11 plan exists yet.
- Only tracked config env file is `.env.example`; a local **untracked** `.env.production` exists with
  `APP_ENV=production` and `APP_DEBUG=false` (see D-1).

Tooling baseline (from `AGENTS.md` and prior records): PHP 8.5 / Laravel framework **13.29.0**,
SQLite in-memory for the default suite, dedicated MySQL `as_negocios_test` for the `mysql` group,
Pint default preset. The default suite baseline at the end of Step 10 was **288 tests / 1,453
assertions**; the `mysql` group was **8 tests / 70 assertions**.

The repository is materially in sync and contains no changes that affect this audit.

---

## 3. Relevant findings from prior steps

Cross-referencing the Step 3–10 plans and the Step 8 master backlog (its §3 tables and §17
sequencing):

- **Completed and verified intact:** Steps 1–9 protections (transactions, row locking, price/cost/tax
  snapshots, client-identity snapshots, safe client deletion, API authorization, login throttling,
  seller attribution/scoping, Sanctum 12 h expiry, stock-write integrity) and Step 10 fractional IVA.
  `B-1`–`B-4` fixed in Step 9; `B-5` fixed in Step 10; `B-6` rejected; `A-1`–`A-4` fixed in Step 8.
- **Explicitly deferred and still open (reassessed below):** `E-6`/`E-7`/`E-8` (reporting snapshot
  consistency), `D-6` (API rate limiting), `D-7` (global `per_page`/`limit` cap), `D-2`/`D-3`
  (dependency advisories), `D-1`/`D-4`/`D-5`/`D-10`/`D-11`/`D-12` (production defaults, upload, ops),
  `F` (flaky `ProductTest`), `C-5` (password-change session/token revocation), `C-3` (audit `User`
  mutations), `B-7` (sale-cancellation history model), `E-1`–`E-5`/`E-9`/`E-11`/`E-12`
  (report/export performance, indexes, `paid_at`, audit redaction, CHECK constraints).
- **Important sequencing note (observed):** the Step 8 plan (§17) bundled the cheap “production
  deployment defaults” (D-1…D-5, D-10…D-12, F) into *Step 10*. That step was instead used for the
  `B-5` IVA fix (per Step 9 §19). Consequently **the D-1/D-4/D-5/D-10…D-12/F cluster was skipped and
  remains open**, while the Step 8 plan’s *Step 11* material (“API hardening and report performance”:
  D-6, D-7, E-1…E-5, E-9) was never started. This plan re-evaluates both buckets rather than assuming
  the original assignment.
- **Already resolved / not applicable (verified):**
  - `C-4` “staff roster leak” — `/users*` is `role:admin` only (`routes/web.php:94-100`); the seller
    list passed to the sale form is view-data only and is not rendered for `vendedor` after Step 6.
    Not exploitable.
  - `D-1` config fallback — `config/app.php:42` already defaults `debug` to `false`; the residual
    concern is only the tracked `.env.example` value.
  - `D-10` excel-temp accumulation — no `storage/framework/laravel-excel` directory currently exists;
    the log-rotation half of D-10 is still open (`laravel.log` ≈ 5.9 MB, `LOG_STACK=single`).

---

## 4. Current audit findings and supporting evidence

Each item was re-verified against the current code (file paths are relative to the repository root).

### E-6 — Inventory report silently zeroes archived products — CONFIRMED, HIGH (correctness)

`app/Services/ReportService.php`:

- Line 80: `->with('product:id,name,sku,production_cost,stock')` loads the product through the default
  scope, so **soft-deleted products are excluded** and `$group->first()->product` is `null` for any
  movement whose product was archived.
- Line 90: `'name' => $product?->name ?? __('app.labels.none')` → the row displays “Ninguno”.
- Line 96: `'value' => (float) ($product?->stock * $product?->production_cost ?? 0)` → valuation `0`.

Impact: the inventory report — a financial valuation view used by admin/encargado — omits the stock
value of every archived product while still listing the product’s movement rows, so the report reads
as if archived stock is worth nothing. Movement history itself is present; only name and value are
lost. No test covers an archived product in this report (`tests/Feature/ReportTest.php:101-134` uses
live products only).

### E-8 — API read surfaces use live client identity — CONFIRMED, MEDIUM

The application’s snapshot contract (Steps 2–3) stores `sales.client_name` / `sales.client_nit` at
write time and exposes them via `Sale::buyerName()` / `Sale::buyerNit()`
(`app/Models/Sale.php:57-67`). The web UI already uses these. Three API reads do not:

- `app/Http/Controllers/Api/SaleController.php:56` eager-loads `client`, and `:64-69` returns
  `client.name` / `client.nit` / `client.email` from the **live** `Client` row, while the sibling
  `client_name` / `client_nit` snapshot fields are returned alongside at `:70-71`.
- `app/Http/Controllers/Api/SaleController.php:35` (index) already uses `buyerName()` — so the same
  resource is inconsistent between `index` and `show`.
- `app/Http/Controllers/Api/DashboardController.php:106-107` builds `top_client.name` from
  `$topClient->client->name` (**live**), while `:112` in the same response uses `$sale->buyerName()`
  (**snapshot**) — an inconsistency inside one payload. The web dashboard uses the snapshot
  (`app/Http/Controllers/DashboardController.php:106` `$topClient->buyerName()`).

Impact: after a client is renamed (or deleted, since `client_id` is `ON DELETE SET NULL`), API
consumers and the API dashboard show a different buyer identity than the web UI and the stored
invoice snapshot for the same sale. There is no client **email** snapshot column, so `client.email`
cannot be made snapshot-consistent without a schema change (see §9).

### D-7 — API `per_page` / `limit` unbounded and unvalidated — CONFIRMED, MEDIUM-HIGH

- Uncapped and unfloored: `app/Http/Controllers/Api/ProductController.php:25`,
  `app/Http/Controllers/Api/SaleController.php:29`, `app/Http/Controllers/Api/InventoryController.php:29`
  (`->paginate($request->integer('per_page', 10))`).
- Ceiling-only, no floor: `app/Http/Controllers/Api/ClientController.php:31`
  (`min($request->integer('per_page', 10), MAX_ITEMS)`) and `:61`
  (`limit(min($request->integer('limit', 20), MAX_ITEMS))`).
- `$request->integer()` casts to `int` without range checking. A large value requests an unbounded
  result set (memory/response-size pressure on an authenticated endpoint); a **negative** value
  compiles to `LIMIT -1`, which returns the whole table on SQLite and is a SQL error on MySQL.
  Laravel’s `LengthAwarePaginator` guards the `per_page = 0` division (`max($this->perPage, 1)` at
  `vendor/laravel/framework/src/Illuminate/Pagination/LengthAwarePaginator.php:61`), so `0` is not a
  500 — the real risks are the unbounded upper bound and negative/erroneous values.
- Step 8 already introduced the cap pattern (`MAX_ITEMS = 100`) for clients only, so the fix is
  consistent rather than novel.

### D-6 — No rate limiting on API routes — CONFIRMED, HIGH (abuse) / needs policy

`routes/api.php:15-37` applies only `auth:sanctum` (plus `role:` on three routes); `bootstrap/app.php`
never calls `throttleApi()`, and only `login` has a limiter (Step 5). Limits per role/token are a
product decision (a strict default could disrupt a POS client), so this is ranked but not recommended
as the primary scope.

### Dependencies — advisories remain — CONFIRMED, security (dependency upgrade)

`composer audit --locked` currently reports **4 advisories across 3 packages**:

- `laravel/framework` **13.29.0** — CVE-2026-102279, “XSS in Debug Page Information”
  (affected `>=13.0.0,<13.30.0`). Matches Step 8 D-2.
- `league/commonmark` — quadratic-time DoS + a raw-HTML bypass (transitive; Step 8 assessed it as
  unreachable — no Markdown rendering exists). Matches D-3.
- `symfony/http-foundation` — CVE-2026-108082, ReDoS in `HeaderUtils::split()` reachable through the
  `Cache-Control` request header. **Not present in the Step 8 list** — a newer advisory.

Fixing these is a dependency upgrade (framework/symfony release bump) and is therefore explicitly out
of scope for the recommended step; it is listed as its own candidate.

### Production/deployment cluster (skipped in Step 10) — CONFIRMED

- `D-1`: tracked `.env.example:4` ships `APP_DEBUG=true`; the correct `.env.production` is **untracked**
  (not delivered by the repo).
- `D-4`: `README.md:49-55` publishes `admin@as-negocios.com` / `password`; install step
  `migrate:fresh --seed` (`README.md:40`); `database/seeders/UserSeeder.php` has no production guard
  and `DatabaseSeeder` calls it unconditionally.
- `D-5`: `app/Http/Requests/UpdateSettingsRequest.php:26` allows `svg`; `SettingController.php:24-29`
  stores it on the `public` disk and `Setting::logoUrl()` (`app/Models/Setting.php:100-105`) serves it
  same-origin → stored XSS when navigated to directly (admin-gated uploader).
- `D-10`/`D-11`/`D-12`: `LOG_STACK=single` (`.env.example:19`, `config/logging.php:57,61`);
  `storage:link` and the `sanctum:prune-expired` cron are undocumented (`README.md`).
- `F`: `database/factories/ProductFactory.php:26` still assigns `expiration_date` via
  `optional(0.6)->dateTimeBetween('-15 days', '+120 days')`, so
  `ProductTest::test_index_renders_products_with_stock_badges` remains intermittently flaky.

### Other ranked-but-deferred items — CONFIRMED / needs decision or migration

- `E-7`: `ReportService::productsReport()` (`:135-146`) selects live `products.name`/`sku` and uses an
  inner join. Correctly snapshotting the product name requires a **new product-name snapshot column**
  (migration) or a LEFT-JOIN fallback that cannot recover a hard-deleted product’s true name.
- `B-7`: hard cancellation discards line-level history — a data-model decision.
- `C-5`: password change does not revoke other sessions/tokens — Step 8/9 roadmap placed this in
  Step 12.
- `C-3`: `User` does not use `RecordsActivity` although `AuditLogController` lists `User` as a filter.
- `E-1`…`E-5`, `E-9`, `E-11`, `E-12`: at-scale performance/index/correctness items; several need a
  migration (indexes, `paid_at`, CHECK constraints) and are not measurable at the current dataset
  size (~180 sales).

---

## 5. Prioritized candidate findings

Ranked by practical impact for a small-business MVP, weighted by confirmed status, decision-freedom,
migration need and risk.

| Rank | ID | Finding | Severity | Migration | Decision needed | Recommendation |
|---|---|---|---|---|---|---|
| 1 | E-6 | Inventory report zeroes archived products (name/value) | HIGH (correctness) | No | No | **Step 11 (core)** |
| 2 | E-8 | API reads use live client identity vs snapshots | MEDIUM | No | Minor (email handling) | **Step 11 (companion)** |
| 3 | D-7 | API `per_page`/`limit` unbounded/unvalidated | MEDIUM-HIGH | No | No | Next step (strong runner-up) |
| 4 | D-6 | No API rate limiting | HIGH (abuse) | No | Yes (limits) | Needs policy decision |
| 5 | Dependencies | framework/commonmark/symfony advisories | HIGH (security) | No | No | Separate dependency-upgrade step |
| 6 | D-4 | Published seeded admin password, no prod guard | HIGH-if-deployed | No | No | Deployment-hardening cluster |
| 7 | D-5 | SVG upload → stored XSS | MEDIUM (admin-gated) | No | No | Deployment/upload cluster |
| 8 | D-1/D-10/D-11/D-12, F | Debug example, log rotation, ops docs, flaky test | MEDIUM/LOW (ops) | No | No | Cheap ops cluster |
| 9 | C-5 | Password change does not revoke sessions/tokens | MEDIUM | No | Minor | Roadmap Step 12 |
| 10 | E-7 | Products report live name; drops hard-deleted history | MEDIUM | Yes (snapshot col.) | Yes | Deferred (needs migration) |
| 11 | B-7 | Cancellation discards line-level history | MEDIUM | Maybe | Yes (model) | Deferred (needs decision) |
| 12 | C-3, E-1…E-5, E-9, E-11, E-12 | Audit coverage, perf, indexes, constraints | LOW–MED-HIGH | Mostly yes | Varies | Deferred |

**Why E-6/E-8 over D-7:** D-7 is a clean, close runner-up and is the item the Step 8 roadmap slotted
for “Step 11.” It is set aside here because the audit’s first-priority area is financial/historical
data integrity, and E-6/E-8 are actively presenting **wrong historical financial data** to users
today (understated inventory valuation; rewritten buyer identity on invoices), whereas D-7 is a
robustness/abuse concern on an authenticated API. Both are small; E-6/E-8 wins on user-visible
correctness. D-7 should be the immediate next step if this scope is approved.

---

## 6. Recommended Step 11 scope and rationale

**Title:** *Preserve historical client and product data on read surfaces.*

**Core (required): E-6.** Make the inventory report resolve archived products so their movement rows
show the real product name and their stock valuation is no longer zeroed.

**Companion (separable): E-8.** Make the API sale detail and the API dashboard `top_client` use the
stored client-identity snapshots (falling back to the live client only when a snapshot is absent, as
legacy sales do), matching the web UI.

**Rationale:**
- Directly serves the top audit area (financial/historical-data integrity) with confirmed defects.
- No migration, no backfill, no write-path change — read-only corrections.
- Consistent with existing conventions: `buyerName()`/`buyerNit()` already define the fallback
  contract; `MAX_ITEMS`-style caps and snapshot usage are prior art.
- Independently testable with feature tests that assert the corrected values.
- Small and reversible; does not touch Steps 1–10 behaviour.

**Decision required at approval:** for E-8, how to treat `client.email` (no snapshot column exists).
Recommended minimal option: leave `client.email` as the live value and document it as best-effort, so
no API field is removed. Alternative: drop `client.email` from the payload. Either is acceptable; the
plan assumes “keep live + document.”

If the approver prefers robustness first, the substitute primary scope is **D-7** (§5, rank 3), which
is equally bounded and migration-free.

---

## 7. Proposed changes grouped by file

### E-6 — inventory report (core)

- `app/Services/ReportService.php` — in `inventoryReport()` (`:74-101`), load the product relation
  **including archived rows**, e.g. constrain the eager load with
  `->with(['product' => fn ($q) => $q->withTrashed()->select('id', 'name', 'sku', 'production_cost', 'stock')])`.
  Keep grouping, `stock_in`/`stock_out`/`net` and `value` semantics unchanged; only the previously
  null product now resolves. No other method in the service changes.

### E-8 — API client-identity snapshots (companion)

- `app/Http/Controllers/Api/SaleController.php` — in `show()` (`:60-91`), set the `client.name` and
  `client.nit` fields from `$sale->buyerName()` / `$sale->buyerNit()`; keep `client.id`; keep
  `client.email` live and document the caveat (pending approval). `client_name` / `client_nit` remain
  unchanged. No change to `index()` (already snapshot-based).
- `app/Http/Controllers/Api/DashboardController.php` — in `top_client` (`:106-109`), use
  `$topClient->buyerName() ?? __('app.labels.none')`, matching the web dashboard
  (`DashboardController.php:106`).

No other production files are proposed. No routes, config, models, migrations, middleware or Blade
changes.

---

## 8. Regression test plan

All new tests live in the existing files; no existing assertion is weakened.

**E-6 — `tests/Feature/ReportTest.php`**

- New `test_inventory_report_includes_archived_products`: create a product with a known
  `production_cost` and `stock`, record inventory movements for it, soft-delete the product, then
  request `reports.index?type=inventory` and assert the archived product’s **name** appears and its
  **stock value** (`stock × production_cost`) is shown — and that `__('app.labels.none')` is **not**
  shown for that row. This test fails on current code (product null → name “Ninguno”, value `0.00`)
  and passes after the fix.
- Existing `test_inventory_report_groups_movements_per_product` must remain green (live products).

**E-8 — `tests/Feature/SaleHistoricalSnapshotTest.php`**

- New `test_api_sale_show_uses_client_identity_snapshots`: create a client, create a sale for that
  client, rename the client (and/or null its identity), then
  `getJson(route('api.sales.show', $sale))` as admin and assert `data.client.name` equals the stored
  `client_name` (the pre-rename value) and `data.client.nit` equals `client_nit`; assert
  `data.client_name` / `data.client_nit` are unchanged. Document the `data.client.email` expectation.
- New (or extend) `test_api_dashboard_top_client_uses_snapshot_name`: create sales so a known client
  is the top client, rename the client, request `/api/dashboard`, assert `top_client.name` is the
  snapshot (pre-rename) name, consistent with the web dashboard. Bind to the existing
  `SalesSellerScopingTest` dashboard fixtures pattern (`tests/Feature/SalesSellerScopingTest.php:257-275`).
- Existing `test_api_returns_historical_unit_price_and_additive_snapshot_fields`
  (`SaleHistoricalSnapshotTest.php:318-335`) makes no client-identity assertion and must stay green.

**Targeted checks:** run `composer test`; confirm no other API/report test asserts the old
live-client or zeroed-archived behaviour (grep verified none do: `ReportTest`, `ApiEndpointsTest`,
`ApiAuthorizationTest`, `SalesSellerScopingTest`, `SaleHistoricalSnapshotTest`).

---

## 9. Database and migration assessment

- **No migration is required.**
- E-6 relies on the existing `products.deleted_at` soft-delete column and the existing relation; the
  fix is a query-scope change only.
- E-8 relies on the existing `sales.client_name` / `sales.client_nit` snapshot columns added in
  `database/migrations/2026_09_17_100000_add_client_identity_snapshots_to_sales_table.php`.
- The only snapshot gap is `client.email`, which has no column. Per §6 the recommended handling keeps
  it live (no schema change); if full email snapshotting is later desired it becomes a separate,
  migration-bearing step (tracked as a deferred finding, not part of Step 11).
- `php artisan migrate:status` must continue to show the same 16 migrations, all `Ran`, none pending.

---

## 10. Compatibility and historical-data considerations

- **Read-only changes.** Neither fix writes to the database; no backfill of historical sales or
  products is performed or needed.
- **Legacy fallback preserved.** `buyerName()`/`buyerNit()` fall back to the live client when the
  snapshot is null, exactly as today, so legacy sales without snapshots are unaffected.
- **API field set is stable.** `data.client.{id,name,nit,email}` and `top_client.{name,total}` remain
  present; only `name`/`nit` values become snapshot-accurate. `client.email` stays live (documented).
  This is a value correction, not a contract removal.
- **Products report (E-7) and cancellation history (B-7) are untouched** — they need a migration or a
  product decision, respectively, and are explicitly deferred.
- **No security, authorization, inventory or financial write-path change**; Steps 1–10 behaviour is
  preserved.

---

## 11. Validation commands

Run from the repository root (Windows PowerShell):

```powershell
php -l app\Services\ReportService.php
php -l app\Http\Controllers\Api\SaleController.php
php -l app\Http\Controllers\Api\DashboardController.php
php -l tests\Feature\ReportTest.php
php -l tests\Feature\SaleHistoricalSnapshotTest.php

vendor\bin\pint --test

composer test                              # expect 288 + new tests, all green
vendor\bin\phpunit -c phpunit.mysql.xml     # expect 8/8 unchanged
php artisan migrate:status                  # expect 16 migrations, all Ran, none pending
```

Baseline for comparison: SQLite **288 passed / 1,453 assertions**; `mysql` group **8 passed / 70
assertions** (end of Step 10).

---

## 12. Development database safety checks

- No migration, no seeder and no data-writing code is introduced, so the development database
  (`as_negocios`) must be unchanged.
- Before and after implementation, capture the read-only metrics probe and confirm they are
  **identical**: `sales`, `sale_items`, `products`, `products_trashed`, `clients`,
  `SUM(subtotal)`, `SUM(total)`, `SUM(tax_amount)`, `SUM(inventory_movements.quantity)`,
  `inventory_movements`, `personal_access_tokens`, `audit_logs`.
  (Step 10 recorded: sales 180; sale_items 450; products 37; products_trashed 0; clients 22;
  `SUM(subtotal)` 131404.99; `SUM(total)` 147173.59; `SUM(tax_amount)` 15768.60;
  `SUM(inventory_movements.quantity)` 3732; inventory_movements 487; personal_access_tokens 0;
  audit_logs 0.)
- Expected change after Step 11: none. A probe helper already exists at
  `%LOCALAPPDATA%\Temp\opencode\step9_metrics.php`.
- The test suite uses the in-memory SQLite database and the dedicated `as_negocios_test` MySQL
  database; the dev database is never touched by tests.

---

## 13. Acceptance criteria

1. The inventory report lists an archived product’s movement rows with its real **name** and a
   non-zero **stock value** (`stock × production_cost`); the placeholder label is no longer shown for
   archived products.
2. The API `GET /api/sales/{sale}` returns `data.client.name`/`data.client.nit` matching the stored
   `client_name`/`client_nit` snapshots (with the legacy live-fallback preserved when a snapshot is
   null).
3. The API `GET /api/dashboard` `top_client.name` matches the web dashboard `top_client` name.
   *Implemented as the **current** client identity:* the dashboard is a current-period view and its
   aggregate query does not select the snapshot column, so both surfaces show the live client name
   (see §16.6 for the approved deviation from the original “snapshot name” wording).
4. All existing tests still pass; the new regression tests pass and fail against the pre-fix code.
5. `composer test`, the `mysql` group, `vendor\bin\pint --test`, `php -l` and `migrate:status` all
   pass/unchanged as in §11.
6. Development-database metrics are identical before and after (§12).
7. No migration, no new dependency, no change to Steps 1–10 behaviour.

---

## 14. Explicit exclusions

Not part of this step (each is a separate candidate per §5):

- **D-7** global `per_page`/`limit` caps and filter validation.
- **D-6** API rate limiting (needs a limits policy).
- **Dependency upgrades** (laravel/framework, symfony/http-foundation, league/commonmark advisories).
- **D-1/D-4/D-5/D-10/D-11/D-12** production-default, seeder-guard, SVG-upload, log-rotation and
  ops-documentation items; and the **F** flaky `ProductTest` factory fix.
- **E-7** products-report live name / hard-deleted history (requires a product-name snapshot
  migration).
- **B-7** sale-cancellation history model (requires a data-model decision).
- **C-5** password-change session/token revocation and **C-3** `User`-mutation auditing.
- **E-1…E-5, E-9, E-11, E-12**: exports chunking, sargable date filters, indexes, dashboard
  aggregation, `paid_at`, audit PII redaction, CHECK constraints.
- Any redesign of `ReportService`, the API payload shape, authentication/authorization, inventory or
  sale write paths.
- Reopening Steps 1–10; modifying their implementation records; editing existing tests to make the
  new behaviour pass.

---

## 15. Implementation sequence and commit strategy

Proposed sequence (after explicit approval only):

1. Add the E-6 regression test (red) and apply the `ReportService::inventoryReport()` fix (green).
2. Add the E-8 regression tests (red) and apply the `Api\SaleController::show()` and
   `Api\DashboardController` `top_client` fixes (green).
3. Run the full validation block in §11; capture the §12 metrics before/after.
4. Update this document to `ACTIVE — IMPLEMENTED` with an implementation record (evidence, counts,
   metrics, commit hashes).
5. Commit and push to `origin/main` (no force).

Commit strategy (mirrors Steps 9–10, which used one implementation commit plus one docs commit):

- Commit 1: `fix: preserve historical client and product data on read surfaces` (E-6 + E-8 code and
  tests).
- Commit 2: `docs: record step 11 implementation evidence` (this plan flipped to ACTIVE with the
  implementation record).

No unrelated change is bundled. If push is rejected or the remote diverges, stop and report; do not
force-push.

---

## 16. Implementation record

Executed on `main`, starting from `c948d50` (Step 10). Scope was limited to **E-6** (inventory report
archived products) and **E-8** (API client-identity snapshots) exactly as approved in §6. No
migration was written or applied.

### 16.1 Commits

| Order | Hash | Subject |
|---|---|---|
| 1 | `4861dcada72499aefbd4dfae11249fa5d34b5c60` | `fix: preserve historical client and product data on read surfaces` |
| 2 | (this commit) | `docs: record step 11 implementation evidence` |

### 16.2 What changed

- `app/Services/ReportService.php` — `inventoryReport()` now eager-loads the `product` relation with
  `withTrashed()` (selecting `id,name,sku,production_cost,stock`), so archived products resolve their
  name/stock/cost instead of rendering the placeholder label and a zero value. Grouping,
  `stock_in`/`stock_out`/`net` and the `stock × production_cost` valuation semantics are unchanged.
  No other method in the service changed.
- `app/Http/Controllers/Api/SaleController.php` — `show()` now returns `data.client.name` /
  `data.client.nit` from `$sale->buyerName()` / `$sale->buyerNit()` (stored snapshots, live fallback
  preserved when a snapshot is null). `client.id` and the additive `client_name`/`client_nit` fields
  are unchanged; `client.email` remains live (no snapshot column exists). `index()` was already
  snapshot-based and is untouched.
- `app/Http/Controllers/Api/DashboardController.php` — `top_client.name` now uses
  `$topClient->buyerName() ?? __('app.labels.none')`, matching the web dashboard.

No migrations, no backfill, no write-path/authorization changes, no payload-shape or report/export
redesign; Steps 1–10 behaviour is preserved.

### 16.3 Regression tests added

`tests/Feature/ReportTest.php`:
`test_inventory_report_includes_archived_products` — an archived product with movements is listed with
its real name and non-zero stock value, and the placeholder label is not shown.

`tests/Feature/SaleHistoricalSnapshotTest.php`:
`test_api_sale_show_uses_client_identity_snapshots` — after a client rename, the API sale detail
returns the pre-rename `client_name`/`client_nit` snapshots while `client.id` and the live `client.email`
are preserved;
`test_api_dashboard_top_client_name_reflects_current_client_identity` — the API `top_client.name`
equals the live current client name and matches the web dashboard.

### 16.4 Verification results (actually executed)

| Check | Command | Result |
|---|---|---|
| Full SQLite suite | `composer test` | **291 passed / 291**, 1,470 assertions (was 288 / 1,453) |
| MySQL concurrency group | `php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml` | **8 passed / 8**, 70 assertions (unchanged) |
| Formatting | `vendor\bin\pint --test` on the changed files | passed |
| Syntax | `php -l` on the 5 changed PHP files | no syntax errors |
| Migrations | `php artisan migrate:status` | 16 migrations, all `Ran`, none pending |

### 16.5 Development database integrity

Read-only metrics captured immediately before and after the work against the dev MySQL database
(`as_negocios`); they were **identical** (tests run against in-memory SQLite and `as_negocios_test`
only):

```
sales 180 · sale_items 450 · products 37 · products_trashed 0 · clients 22
SUM(subtotal) 131404.99 · SUM(total) 147173.59 · SUM(tax_amount) 15768.60
SUM(inventory_movements.quantity) 3732 · inventory_movements 487
personal_access_tokens 0 · audit_logs 0
```

### 16.6 Deviations from the plan

- **E-8 dashboard semantics (approved mid-implementation).** §6/§8 originally expected the API
  `top_client.name` to be the stored snapshot. Verification showed the `top_client` aggregate
  (`selectRaw('client_id, SUM(total) as total')`) does not select `client_name`, so `buyerName()`
  falls back to the live client — identical to the web dashboard, whose query has the same shape.
  Because the dashboard is a current-period view, the approved resolution keeps the **live** current
  client name on both surfaces, and the dashboard test asserts API/web parity. The sale-detail
  snapshot fix (a genuinely historical document) is unaffected.
- No other deviation. No pre-existing assertion was weakened; no formatter-applied churn beyond the
  changed lines.

---

## Observed facts vs inferred risks vs unresolved questions

- **Observed facts:** all file/line references, the git baseline, test baselines, `composer audit`
  output, `migrate:status` count, and the dev-database metric shape were read from the current
  repository in this audit.
- **Inferred risks:** the practical impact statements in §4 (report under-valuation; API/web buyer
  mismatch) follow directly from the observed code paths; the D-6/at-scale/E-* impacts are inferred
  and intentionally not selected as the primary scope.
- **Unresolved questions:** (a) ~~the exact `client.email` handling for E-8~~ — **resolved: kept
  live** (§16.2); (b) ~~whether to prioritise D-7 instead of E-6/E-8~~ — **resolved: E-6/E-8 chosen**;
  (c) the API-limit policy for D-6 (deferred); (d) whether E-7’s product-name snapshot migration is
  desired in a later step (deferred).

---

IMPLEMENTATION STATUS: IMPLEMENTED
APPROVAL REQUIRED: NO
