# Phase 1 — Step 8 Plan

Status: ACTIVE

Approved title: **Complete seller data isolation on client surfaces (web + API)**

## 1. Executive summary

A fresh, independent audit of the current repository (`main` @ `f6de54b`, after Steps 1–7) was
performed across all eleven requested areas. **Steps 1–7 are intact; the audit found no regression
in any of them.**

> **Outcome:** this plan was approved and implemented on 2026-10-05 as **Complete seller data
> isolation on client surfaces (web + API)**. See §19 for the Implementation Record.

Remaining findings fall into four themes:

| Theme | Severity range | Root cause |
|---|---|---|
| **A. Seller data isolation is incomplete** | HIGH | Step 6 applied `Sale::visibleTo()` to every *sale* surface but not to the *client* surfaces, which are a denormalized view of the same sales |
| **B. Inventory ledger integrity still has holes** | HIGH | Step 1 locked the sale paths; the cancel path, the product form, and the sale row itself were never brought under the same rules |
| **C. Production defaults are unsafe for deployment** | CRITICAL-if-deployed | `APP_DEBUG=true` shipped as default, a seeded admin with a published password, an SVG upload path, an unpatched framework advisory |
| **D. Performance/operability debt** | MEDIUM–HIGH | Non-sargable date filters, missing indexes, unbounded exports, no log rotation, undocumented post-deploy steps |

**Recommended Step 8: complete seller data isolation** on client surfaces (web + API). It is the
only confirmed **authorization** breach remaining in the application after four exhaustive passes
over routes, middleware, controllers, FormRequests, policies and resource binding. It is reachable
by the **lowest-privilege role with no special conditions**, and it directly contradicts a
guarantee Step 6 already shipped and regression-tested.

The highest-severity *data-integrity* finding — cancelling a sale whose product has been archived
silently loses stock while writing a movement row that claims the stock returned — is equally rated
HIGH and is proposed as **Step 9**. It is permanent and unrecoverable, but it requires a specific
sequence (archive product → cancel sale), so it ranks below an exposure that is continuous once the
attacker is simply a logged-in seller.

Theme C is the cheapest work in the whole backlog (all Low complexity) but cannot be *the* Step
because it is deployment hygiene rather than a code path: nothing leaks until someone deploys
incorrectly. It is Step 10.

---

## 2. Current repository state

- Branch `main`, HEAD `f6de54b`, synchronized with `origin/main`, working tree clean.
- Laravel 13.29.0, PHP 8.5, Sanctum 4.3.3. SQLite for dev/tests (`phpunit.xml`), MySQL for the
  `mysql` test group (`phpunit.mysql.xml`).
- 258 tests, 1,250 assertions across 20 feature test files.
- Route surface: 1 guest page, login/forgot/reset, ~45 authenticated web routes, 13 API routes.
- Roles: `admin`, `encargado`, `vendedor`. Authorization is middleware-string based
  (`RoleMiddleware`); there is **no `app/Policies` directory**.
- Migrations: 14, all applied. Additive historical migrations from Steps 2–3 are present
  (`sale_items.unit_price/cost/tax_rate`, `sales.tax_rate/client_name/client_nit`, unique
  `clients.nit`).
- Asset pipeline: Vite + self-hosted fonts (`laravel-vite-plugin/fonts`). **No CDN.**
- Scheduler: one entry — `sanctum:prune-expired --hours=24` daily.
- Queued work: **none**. The `database` queue driver is inert and needs no worker.
- `.env` is untracked and has never been committed; no secrets in tracked files.

---

## 3. Fresh audit findings

### A. Seller data isolation (Step 6 gap)

| # | Finding | Severity | Evidence | Likelihood | Business impact | Covered by 1–7? |
|---|---|---|---|---|---|---|
| A-1 | `GET /api/clients/{client}` returns **every** seller's sales to any authenticated caller | HIGH | `app/Http/Controllers/Api/ClientController.php:44-52`; route `routes/api.php:21-22` has no role gate and no scoping | Trivial — one request, `?limit=9999` | Full disclosure of colleagues' invoice numbers, totals and payment state | **No** (Step 4 added the endpoint; Step 6 scoped sales only) |
| A-2 | Web `/clients/{client}` shows all sellers' invoices per client | HIGH | `app/Http/Controllers/ClientController.php:53-56` (`$client->sales()->with('seller:id,name')`, no `visibleTo`), rendered at `resources/views/clients/show.blade.php:73-97`; route `routes/web.php:116-117` | Trivial — the default vendedor page | Same disclosure through the UI | No |
| A-3 | Client index aggregates are company-wide | MEDIUM-HIGH | `ClientController.php:23-30` and `Api/ClientController.php:18-24` (`withCount`/`withSum` unscoped) → `sales_total`, `pending_balance`, `sales_count` | Trivial, no pagination needed | Per-client lifetime spend and unpaid balance including sales the vendedor never made | No |
| A-4 | Client index/show have **no role gate at all** | MEDIUM | `routes/web.php:116-117`, `routes/api.php:21-22` (`auth` group only) | Any authenticated user | A `vendedor` reaches an admin-adjacent surface; combined with A-2/A-3 it is a business-intelligence leak | No |

**Proof that this is a gap, not a design choice:** every *sale* surface applies the scope —
`SaleController.php:37,93`, `Api/SaleController.php:21,54-58`, both dashboards, the invoice PDF and
`SalesExport`. `Sale::scopeVisibleTo` (`app/Models/Sale.php:86-89`) exists and works. The client
pages simply never call it.

**Nuance that must be preserved:** the *shared client registry* is intentional
(`app/Models/User.php:112-118` documents open registration for walk-in customers). Only the
**attached sales data and aggregates** are the leak. Step 8 must not restrict who may *register* a
client.

### B. Inventory / sale integrity (Step 1 gap)

| # | Finding | Severity | Evidence | Likelihood | Business impact | Covered by 1–7? |
|---|---|---|---|---|---|---|
| B-1 | Cancelling a sale whose product is soft-deleted **silently loses stock** and writes a movement claiming it returned | HIGH | `SaleController.php:196` `$item->product?->increment(...)` resolves through the default scope → `null` for a trashed product → no-op, while `:198-205` still inserts an `in` movement, then `:208` deletes the sale. Every other path uses `Product::withTrashed()->lockForUpdate()` (`:425-429`) | Requires: sell → archive product → cancel sale. Very plausible (discontinue a product, return comes in weeks later) | **Permanent, unrecoverable inventory corruption.** Ledger says stock returned; stock stayed out. The sale row is gone, so nothing can be recomputed. | Partially undermines Step 1 |
| B-2 | Product stock is a submitted form field written **without a transaction or row lock** | HIGH | `ProductController.php:56,58` and `:105-110` (`$data['stock']` → `update()` → `logAdjustment()`); `stock` is fillable and present in `resources/views/products/form.blade.php:26` | Two managers editing the same product; also any failure between the two writes | Lost update → `stock ≠ initial + Σ movements` permanently; or stock changed with **no** movement row | No (Step 1 hardened the sale and manual-adjustment writers only) |
| B-3 | `Sale::update()` and `Sale::destroy()` never lock the **sale row** | HIGH | `SaleController.php:118-152`, `:188-209` — route-model binding, no `lockForUpdate`; `reconcileItems()` derives deltas from a snapshot read at `:126` then mass-deletes at `:360` | Two admins on the same invoice | Silent last-write-wins; one admin's line items deleted by another's save with no error | No |
| B-4 | Archived products remain sellable | MEDIUM-HIGH | `app/Http/Requests/StoreSaleRequest.php:27` `exists:products,id` ignores soft-delete scope; `:57` re-loads with `withTrashed()`; `:62` gates only on `is_active`, and `ProductController::destroy` never clears `is_active` | Any admin/encargado sale after archiving | Selling discontinued goods; also the enabler of B-1 | Explicitly deferred in Step 3, never taken |
| B-5 | Configured decimal IVA is truncated to an integer | MEDIUM | `SaleController.php:422` `(int) Setting::get('iva_percentage', 12)`; `UpdateSettingsRequest.php:28` accepts decimals | Any non-integer tax rate configured | Systematic under-taxing with no error | No |
| B-6 | Create vs update round totals differently | LOW | `applyItems()` accumulates unrounded and rounds once; `reconcileItems()` rounds per line | Any sale edited after creation | Cents of divergence — looks like tampering when disputed | No |
| B-7 | Deleting a sale cascades `sale_items` away **without** firing Eloquent events | MEDIUM | `database/migrations/2026_01_01_000040_create_sale_items_table.php:16` `cascadeOnDelete` + `SaleController.php:208` `$sale->delete()` | Cancel any sale | Line-level snapshots and audit entries vanish with no trail | No |

### C. Authorization shape (not currently exploitable)

| # | Finding | Severity | Evidence |
|---|---|---|---|
| C-1 | 8 of 10 FormRequests return `authorize(): true`; no Policies exist; several actions rely solely on route middleware | LOW (regression risk, not a live hole) | `StoreClientRequest.php:10`, `UpdateClientRequest.php:11`, `StoreProductRequest.php:10`, `UpdateProductRequest.php:11`, `StoreSaleRequest.php:11`, `StoreInventoryMovementRequest.php:13`, `UpdateSettingsRequest.php:9`; no `app/Policies` |
| C-2 | Sanctum abilities are issued but never enforced | LOW | `Api/AuthController.php:36` mints `['api']`; no `tokenCan` / `CheckAbilities` / `abilities` middleware anywhere |
| C-3 | `User` mutations are not audited, yet `User` is offered as an audit filter | LOW | `User.php:19` lacks `RecordsActivity`; `AuditLogController` lists `User` in `MODELS` |
| C-4 | Complete staff roster (id + name) disclosed to `vendedor` | LOW | `SaleController.php:51,61,113` — unfiltered `User::orderBy('name')` |
| C-5 | Password change does not revoke sessions, remember-me, or API tokens | MEDIUM | `UserController.php:49-68`, `ResetPasswordController.php:35-39`; `AuthenticateSession` not registered |

### D. Production readiness / deployment

| # | Finding | Severity | Evidence |
|---|---|---|---|
| D-1 | `APP_DEBUG=true` is the shipped default (`.env.example:4` and the live `.env:4`); Laravel 13's debug page dumps request headers and POST body, so a 500 on `/login` or an API route can expose a plaintext password, the session cookie, or a Sanctum bearer token | **CRITICAL if deployed as-is** | `.env.example:4`; `config/app.php:42` falls back to `false`; `.env.production:4` is correct |
| D-2 | `laravel/framework` 13.29.0 is inside `CVE-2026-102279`'s range (fixed in ≥ 13.30.0) — an **XSS in that same debug page** | HIGH | `composer audit` → GHSA-jh5r-qr3c-85q8, `>=13.0.0,<13.30.0` |
| D-3 | `league/commonmark` 2.10.0: high (quadratic DoS) + medium advisories; transitive, only fixable via the framework bump. **Unreachable** — no Markdown rendering exists | HIGH (severity) / LOW (exploitability) | `composer why league/commonmark` → only framework; zero `CommonMark` / `Str::markdown` call sites |
| D-4 | Seeded admin `admin@as-negocios.com` / `password`, published at `README.md:49-55`, install step `php artisan migrate:fresh --seed` at `README.md:40`, with **no production guard** in any seeder | **CRITICAL if seeded in production** | `UserSeeder.php:15-28` + `UserFactory.php:31`; `DatabaseSeeder.php:17-21` calls it unconditionally |
| D-5 | Logo upload accepts **SVG** and is served from the app's own origin with no sandbox → stored XSS when navigated to directly | MEDIUM (admin-gated) | `UpdateSettingsRequest.php:26` `mimes:...,svg`; `settings/edit.blade.php:51`; `public` disk has no `serve => true`, so Apache serves it raw; app CSP allows `'unsafe-inline'` |
| D-6 | **No rate limiting on any API route**; only `login` is throttled | HIGH | `routes/api.php:4-6`; `bootstrap/app.php` never calls `throttleApi()` |
| D-7 | `per_page` / `limit` unbounded and unvalidated on every API list; `per_page=-1` compiles to `LIMIT -1` | HIGH | `Api/ClientController.php:24,51`, `Api/ProductController.php:25`, `Api/SaleController.php:29`, `Api/InventoryController.php:29` |
| D-8 | Session cookie has no `Secure` flag; `SESSION_SECURE_COOKIE` documented nowhere | MEDIUM | `config/session.php:172` `env('SESSION_SECURE_COOKIE')`, key absent from all env files; `secure .. null` |
| D-9 | CSP is Apache-only, weakened by `'unsafe-inline' 'unsafe-eval'`; missing HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` | MEDIUM | `public/.htaccess:1-5`; no header middleware in `bootstrap/app.php` |
| D-10 | Log rotation absent (`LOG_STACK=single`); `laravel-excel` temp files accumulate (340 orphaned `.xlsx`, 2 MB, containing customer PII) | MEDIUM | `.env:19`; `config/logging.php:61-66`; `laravel.log` already 5.1 MB |
| D-11 | `php artisan storage:link` is required but documented nowhere and absent from the deploy bundle | MEDIUM | `config/filesystems.php:44,76-78`; `Setting::logoUrl()`; zero doc matches |
| D-12 | The `sanctum:prune-expired` schedule needs a cron that is documented nowhere | MEDIUM | `routes/console.php:15`; zero cron references in any doc |

### E. Reports, performance, database integrity

| # | Finding | Severity | Evidence |
|---|---|---|---|
| E-1 | Every report and export loads **unbounded** result sets into memory | HIGH (at scale) | `ReportService.php:54,81,121`; all four `app/Exports/*`; `SaleController.php:251`; `ClientController.php:107`; `InventoryController.php:96` |
| E-2 | Every date filter is non-sargable (`date(col) >= ?`) and `sales.created_at` is unindexed | HIGH (at scale) | `ReportService.php:77-78,111-117,141-142`; `Sale::scopeBetweenDates` |
| E-3 | Missing indexes on `sales.created_at`, `sales.paid`, `sales(seller_id,created_at)`, `products.deleted_at`, `products.is_active`, `inventory_movements.created_at`, `audit_logs.created_at` | MEDIUM-HIGH | No migration declares them; `softDeletes()` at `2026_08_30_220424` has no index |
| E-4 | Dashboard issues ~18 separate aggregates per render, each with its own `whereBetween` | MEDIUM | `DashboardController.php:24-31` (6 counts) + `:36-43` (12 sums); mirrored in `Api/DashboardController` |
| E-5 | Inventory PDF is an N+1 (eager-load omits `product`) over an unbounded set | HIGH | `InventoryController.php:96` `->with('user:id,name')`; `resources/views/inventory/export-pdf.blade.php` reads `$movement->product->name` per row |
| E-6 | Inventory report silently zeroes archived products (`$product` → `null`, `value => 0`) while the products report shows them | HIGH (correctness) | `ReportService.php:76-100` vs `:136` |
| E-7 | Products report mixes live `products.name/sku` with `sale_items` snapshots; INNER JOIN drops hard-deleted history | MEDIUM | `ReportService.php:135-146` |
| E-8 | API shows **live** client identity, contradicting the Step 3 snapshot contract used by the web UI | MEDIUM | `Api/SaleController.php:56,65-68` returns `$sale->client->nit/email`; `Api/DashboardController.php:72-79` uses `$sale->client->name`; web uses `buyerName()` |
| E-9 | `sales.paid_at` does not exist — payment history is overwritten in place | MEDIUM | `2026_01_01_000030` has only `paid`; `SaleController.php:179` `forceFill(['paid' => ...])` |
| E-10 | Un-grouped `OR` in `Product::scopeSearch` / `Client::scopeSearch` can leak soft-deleted rows and bypass sibling filters | MEDIUM | `app/Models/Product.php:63-66`, `app/Models/Client.php:40-44` |
| E-11 | Audit log stores full client PII and per-item cost as unencrypted JSON, indefinitely | MEDIUM | `RecordsActivity.php:11-12` excludes only `updated_at` / `deleted_at` |
| E-12 | No `CHECK` constraints; `products.stock` and `inventory_movements.quantity` unrestricted at DB level; `restrictOnDelete` on `sale_items.product_id` is dead code | LOW-MEDIUM | No `check()` in any migration |

### F. Pre-existing test fragility (not a Step 1–7 regression)

`tests/Feature/ProductTest.php::test_index_renders_products_with_stock_badges` is **flaky**.
`database/factories/ProductFactory.php:26` assigns `expiration_date` randomly in
`['-15 days','+120 days']` with probability 0.6, so roughly 6.7% of runs create an already-expired
product and the badge renders `Vencido` instead of `En stock`. Observed failing once in a full-suite
run, then passing 4/4 in isolation. Neither file has been touched since Step 4 (`485ef99`). This is
a test-data defect and must not be attributed to Steps 1–7.

---

## 4. Findings already addressed by Steps 1–7 (verified intact)

Every item below was re-verified against the current code, not read from documentation.

| Step | Protection | Verification evidence |
|---|---|---|
| 1 | Sales/inventory transactions | `SaleController.php:70,124,192`; `InventoryController.php:59-60` — `DB::transaction` wraps items, stock, movements and audit |
| 1 | Row locking + post-lock stock re-check | `SaleController.php:425-429` `withTrashed()->lockForUpdate()`, `:452-459` re-check inside the lock; `InventoryController.php:64-71` |
| 1 | Real InnoDB concurrency proof | `tests/Feature/MysqlConcurrencyTest.php` drives parallel connections and asserts genuine oversell rejection |
| 2 | Sale price/cost/tax snapshots | `sale_items.unit_price`, `.cost`, `.tax_rate`, `sales.tax_rate`; `applyItems()` reads price/cost from the **locked** product row (`:437,:444`); `ReportService.php:139-140` derives margin from snapshots |
| 2 | Server-computed money | No `$request->all()` anywhere; all writes use `validated()`; `resources/js/sale.js` is display-only |
| 3 | Client identity snapshots | `client_name` / `client_nit` written at create+update (`SaleController.php:165-173`); consumed via `buyerName()` / `buyerNit()` by list, show, invoice PDF, `SalesExport`, reports, API index |
| 3 | Safe client deletion | `ClientController.php:80-95` blocks deletion while sales reference the client; DB independently protects via nullable FK `ON DELETE SET NULL` (`:16`) so the sale + snapshot survive |
| 3 | NIT uniqueness at DB level | `2026_09_17_100001` unique index, not only validation |
| 4 | API commercial-data authorization | `Api/ProductController.php:41-43,80-84`, `Api/SaleController.php:87-89`, `Api/DashboardController.php:98-100` omit cost/margin; reports are `role:admin,encargado`; `tests/Feature/ApiAuthorizationTest.php` |
| 5 | Login throttling | `app/Support/LoginThrottle.php` (5 attempts / 5 min, key = lowercased email + IP), wired in `Auth/LoginController.php:24` and `Api/AuthController.php:18`; `tests/Feature/{LoginThrottleTest,ApiAuthThrottleTest}.php` |
| 6 | Seller attribution enforcement | `SaleController.php:73-75,146-148` force `seller_id` to the actor unless `canOverrideSeller()`; re-asserted in `StoreSaleRequest.php:23` |
| 6 | Seller sales scoping | `Sale::scopeVisibleTo` (`Sale.php:86-89`) applied on `SaleController.php:37,93`, `Api/SaleController.php:21,54-58`, both dashboards, invoice PDF, `SalesExport`; `tests/Feature/SalesSellerScopingTest.php:98-240` |
| 7 | Sanctum 12-hour expiration | `config/sanctum.php:62` `env('SANCTUM_EXPIRATION', 720)`; enforced via the `created_at` window; `tests/Feature/ApiTokenExpirationTest.php` |
| 7 | Explicit `['api']` token ability | `Api/AuthController.php:36` `createToken('api', ['api'])`; asserted in the same test file |
| 7 | Pruning schedule | `routes/console.php:15` `sanctum:prune-expired --hours=24` daily; `schedule:list` → `0 0 * * *` |

**Nothing in this audit changes any of the above.**

---

## 5. Remaining findings ranked by priority

| Rank | ID | Finding | Severity | Complexity |
|---|---|---|---|---|
| 1 | A-1..A-4 | Seller data isolation bypassed on client surfaces (web + API) | **HIGH** | Low |
| 2 | B-1 | Cancelling a sale with an archived product silently loses stock and falsifies the ledger | **HIGH** | Low |
| 3 | B-2 | Product stock written without transaction or lock | **HIGH** | Low–Med |
| 4 | B-3 | No sale-row lock on update/cancel | **HIGH** | Low |
| 5 | D-1..D-4 | Production defaults: debug on, unpatched framework, published seeded admin password | **CRITICAL-if-deployed** | S |
| 6 | D-6/D-7 | No API rate limiting; unbounded `per_page` | **HIGH** | Low |
| 7 | D-5 | SVG upload → stored XSS on own origin | MEDIUM | S |
| 8 | E-1/E-2/E-5 | Unbounded exports, non-sargable filters, N+1 PDF | HIGH (at scale) | Medium |
| 9 | E-3 | Missing indexes on hot filter columns | MEDIUM-HIGH | Low |
| 10 | B-4 | Archived products remain sellable | MEDIUM-HIGH | Low |
| 11 | C-5 | Password change does not revoke sessions/tokens | MEDIUM | Med |
| 12 | D-8..D-12 | Session `Secure`, headers, log rotation, `storage:link`, cron docs | MEDIUM | S |
| 13 | E-6..E-12 | Report/snapshot correctness and DB constraints | MEDIUM | Low–Med |
| 14 | B-5/B-6/B-7, C-1..C-4, E-9 | Correctness, rounding, audit coverage, policies | LOW–MEDIUM | Low |
| 15 | F | Flaky `ProductTest` due to random `expiration_date` in the factory | LOW | S |

---

## 6. Recommended Step 8

**Step 8 — Complete seller data isolation on client surfaces (web + API).**

Rationale for placing it first:

1. **Security ranks above data integrity, and this is the only confirmed security hole left.**
   Four independent passes over routes, middleware, controllers, FormRequests, policies and
   resource binding found no other authorization bypass. Every other finding is integrity,
   correctness or deployment hygiene.
2. **It is reachable by the default, lowest-privilege role with zero special conditions.** Any
   `vendedor` who opens a client record sees colleagues' invoices and company-wide revenue totals.
   No parameter, no IDOR primitive, no race, no misconfiguration. Unlike B-1, it is not a
   sequence-dependent bug — it is simply the normal page.
3. **It contradicts a guarantee this program already shipped and tested.** Step 6's own invariant is
   "a `vendedor` sees only their own sales", regression-tested across index, show, invoice PDF,
   exports, API and dashboard. The client pages are a denormalized view of those same sales and were
   not re-visited when Step 6 landed — the same class of oversight as B-2, where Step 1 hardened the
   sale writers but not the product writer.
4. **It is cheap and low-risk.** No migration, no schema change, no new concept. One scope
   (`Sale::visibleTo`) already exists and is already used six times elsewhere. A focused step closes
   it and can be regression-tested exactly like Step 6 was.
5. **It restores trust in Step 6.** Leaving a known hole in a shipped guarantee is worse for an SME
   customer than any remaining integrity defect, because they will believe the isolation exists.

### Why not the others

- **B-1 (stock loss)** is the most severe *integrity* defect and would be the natural Step 8 if the
  scoping leak did not exist. It needs archive-then-cancel sequencing, so its exposure is episodic.
  → **Step 9.**
- **Theme D (production defaults)** is nearly free (all Low complexity) and should ship early, but
  it is deployment hygiene: nothing leaks until someone deploys with the shipped defaults. Bundling
  it would dilute the focus of a single step. → **Step 10.**
- **B-2/B-3** share a root cause with B-1 (unlocked inventory writers) and belong with it. → **Step 9.**
- **E-1..E-12** are correctness and scale concerns with no current exposure at SME data volumes.
  → **Steps 11–12.**
- **C-1 (policies)** is a refactor of a system that is currently correct. Introducing a permission
  framework is explicitly out of bounds and unjustified by the current product.

---

## 7. Business impact

For an SME customer, seller isolation is a contractual and competitive property. The intended model
is that a salesperson sees their own book, not their colleagues'.

| Dimension | Impact of leaving the gap open |
|---|---|
| Competitive / trust | A salesperson learns colleagues' volumes, targets, pricing and payment state; disputes over commission and targets become unfalsifiable |
| Data exposure | Per-client lifetime spend, unpaid balance, invoice numbers and sale dates of the whole business, reachable one page at a time |
| Compliance posture | Step 6 is documented as a completed control in `docs/phase1-step6-plan.md`; the shipped gap makes that documentation inaccurate |
| Customer trust in the product | The most common SME objection to a sales system is "my sellers will snoop on each other"; this gap is precisely that objection |
| Financial | No direct corruption — the numbers remain correct, only their visibility is wrong |

**After Step 8:** a `vendedor` sees only their own sales attached to a client, and client aggregates
reflect only their own activity. The shared client registry (needed for walk-in customers) is
preserved unchanged.

---

## 8. Proposed scope

### In scope

1. **Web client detail** — `ClientController@show`: apply `Sale::visibleTo()` to the client's sales
   relation; for a `vendedor`, render only their own invoices.
2. **Web client index** — `ClientController@index`: scope the `withSum` / `withCount` aggregates so
   `sales_count`, `sales_total` and `pending_balance` reflect the requesting user's visible sales;
   suppress or recompute the aggregate columns rather than showing company-wide totals to a seller.
3. **API client detail** — `Api/ClientController@show`: same scoping; additionally **cap the `limit`
   parameter** (currently unbounded) and align it with the pagination envelope.
4. **API client index** — `Api/ClientController@index`: scoped aggregates.
5. **Bounded pagination on the touched endpoints** — clamp `per_page` / `limit` to a sane maximum
   (e.g. 100) on the four client endpoints. Scoped to client endpoints only; the project-wide
   pagination cap is Step 11.
6. **Regression tests** — extend `tests/Feature/SalesSellerScopingTest.php` (or add
   `ClientSellerScopingTest.php`) covering, for both web and API: own sales visible; other sellers'
   invoices absent from the detail page and from the API payload; other sellers' sales absent from
   aggregates; admin/encargado unchanged (global visibility); a client with no sales by the current
   seller renders cleanly.

### Explicit non-goals for this step

- Restricting **who may create/register a client** — the shared registry is intentional.
- Changing the role middleware matrix or introducing policies.
- Changing the `Sale::visibleTo` semantics.
- Any change to sales, inventory, product, or schema code.

---

## 9. Explicit out-of-scope items

| Item | Reason | Planned home |
|---|---|---|
| B-1 cancel-with-archived-product stock loss | Different root cause; deserves its own step | Step 9 |
| B-2 product stock unlocked / non-transactional | Same | Step 9 |
| B-3 sale-row lock on update/cancel | Same | Step 9 |
| B-4 archived products sellable | Authorization-adjacent but a sale-path rule | Step 9 |
| B-5 IVA integer truncation, B-6 rounding divergence, B-7 cascade audit | Financial correctness | Step 11 |
| C-5 password change revoking sessions/tokens | Authentication lifecycle | Step 12 |
| C-1 policies / FormRequest `authorize()` | Currently correct; a permission framework is unjustified | Step 12 (opportunistic) |
| C-2 enforce token abilities | Step 7 explicitly deferred; no client needs scoped tokens | Deferred |
| C-3 audit `User` mutations, C-4 staff roster leak | Low | Step 11 |
| D-1..D-4 debug default, framework bump, seeded admin password | Deployment defaults | **Step 10 (cheap, do early)** |
| D-5 SVG upload XSS | File upload | Step 10 |
| D-6 API rate limiting, D-7 global pagination cap | API hardening | Step 11 |
| D-8 `Secure` cookie, D-9 headers/CSP | TLS-dependent | Step 10 (after TLS confirmed) |
| D-10 log rotation, D-11 `storage:link`, D-12 cron docs | Operations | Step 10 |
| E-1..E-12 reports, exports, indexes, snapshot consistency, DB constraints | Performance/correctness | Steps 11–12 |
| F flaky `ProductTest` | Test-data defect, not a security control | Step 10 (cheap test fix) |
| Trusted proxies | Topology unknown; `'*'` would actively break Step 5 throttling | Deferred until deployment is known |
| Queue worker / Redis | Zero queued work exists | Never |
| Refresh tokens, multi-tenant, per-token heterogeneous expiry | No demonstrated requirement | Never |

---

## 10. Implementation strategy

Ordered so that each step is independently verifiable and low-risk:

1. **Add the scope at the data layer** — apply `Sale::visibleTo(auth()->user())` inside the relation
   and aggregate closures in both `ClientController` and `Api\ClientController`. Prefer
   `withSum(['sales as sales_total' => fn ($q) => $q->visibleTo($user)], 'total')` so the aggregate
   is computed in SQL under the same predicate as the listing (no PHP post-filtering, no N+1).
2. **Confirm admin/encargado are unaffected** — `scopeVisibleTo` is a no-op for non-sellers, so
   their global view must not change. This is the main regression risk; assert it explicitly.
3. **Handle the seller view of aggregate columns** — for a `vendedor`, either show the scoped values
   or hide the columns. Hiding is the safer, simpler choice; decide during implementation and record
   the decision in the commit.
4. **Cap `limit` / `per_page`** on the four client endpoints. Use the existing
   `Api\Concerns\PaginatesToJson` helper so the cap lands in one place for these endpoints. Do not
   expand to other endpoints in this step.
5. **Tests before commit** — extend `tests/Feature/SalesSellerScopingTest.php` with client-surface
   cases for web + API, admin/encargado parity, and the limit cap.
6. **Verification** — `composer test`, `vendor\bin\phpunit -c phpunit.mysql.xml`,
   `vendor\bin\pint --test`, `php -l` on changed files, `migrate:status`, before/after dev-DB row
   counts.
7. **Documentation** — flip this document to ACTIVE, add an Implementation Record with the commit
   hash and exact test counts, and correct the client-surface coverage claim in
   `docs/phase1-step6-plan.md` so the Step 6 evidence is accurate.

**Explicitly avoid:** touching the sales scoping code path, adding a new scope with different
semantics, or "fixing" related report/perf issues opportunistically.

---

## 11. Files/modules expected to change

| File | Change | Risk |
|---|---|---|
| `app/Http/Controllers/ClientController.php` | `visibleTo()` on the sales relation and on the `withSum` / `withCount` closures; seller-aware aggregate columns | Low |
| `app/Http/Controllers/Api/ClientController.php` | Same scoping; clamp `limit`; consistent envelope | Low |
| `app/Http/Controllers/Api/Concerns/PaginatesToJson.php` | Optional: a shared `per_page` cap helper (client endpoints only) | Low |
| `resources/views/clients/show.blade.php` | Empty-state when a seller has no sales for the client | Low |
| `resources/views/clients/index.blade.php` | Hide/adjust aggregate columns for sellers if step 3 of the strategy says so | Low |
| `tests/Feature/SalesSellerScopingTest.php` (or new `ClientSellerScopingTest.php`) | New regression cases | Low |
| `docs/phase1-step8-plan.md` | Status flip + Implementation Record | None |
| `docs/phase1-step6-plan.md` | Correct the client-surface coverage statement | None |

Not expected to change: models, migrations, routes, middleware, config, Sanctum,
sales/inventory logic, anything in `app/Exports`.

---

## 12. Database/migration impact

**None.** No migration is required or permitted in this step.

- `Sales.visibleTo` is an existing query scope (`app/Models/Sale.php:86-89`); it filters on the
  existing `sales.seller_id` column.
- Aggregates remain `withSum` / `withCount` sub-queries — the scoping predicate is applied inside the
  closure, still a single SQL statement. No extra round-trip.
- No new index is needed for correctness. (A composite `sales(seller_id, created_at)` index would help
  these queries perform; that belongs with the index migration in Step 11, not here.)
- Expected row-count and total deltas on the dev database after the change: **zero**. The step is
  read-path only; it must be verified with before/after counts exactly as Steps 6 and 7 were.

---

## 13. Test strategy

All cases go in `tests/Feature/SalesSellerScopingTest.php` (extending it keeps the Step 6 regression
suite in one place; a separate file is acceptable if the file grows unwieldy).

| # | Case | Assertion |
|---|---|---|
| 1 | Vendedor opens a client they served | 200; own invoices present |
| 2 | Vendedor opens a client served **only** by another seller | 200; own-sales section empty; **no** other seller's invoice number, total, seller name or paid flag anywhere in the response/HTML |
| 3 | Vendedor client **index** | Aggregate columns, if shown, equal the sum of that vendedor's own sales only — never the company-wide figure |
| 4 | Vendedor hits `GET /api/clients/{id}` | Payload contains only own sales; `seller` never identifies a colleague on a returned invoice |
| 5 | Vendedor hits `GET /api/clients` | Aggregates scoped; a colleague's sale does not inflate `sales_total` / `sales_count` / `pending_balance` |
| 6 | Admin / encargado, same client | Global view unchanged — every invoice, aggregates identical to today |
| 7 | Client with **no** sales for the current vendedor | 200 with a clean empty state, no error, no division by zero |
| 8 | API `limit` cap | `?limit=9999` returns at most the capped page size |
| 9 | Unauthenticated | Redirect (web) / 401 (API) — unchanged |
| 10 | Sales surfaces | The existing Step 6 sale-scoping cases continue to pass untouched |

**Determinism:** seed fixtures explicitly (no reliance on factory randomness for the scoping
assertions); use exact date/amount values so the aggregate assertion is unambiguous.

**Explicitly not covered here:** the flaky `ProductTest` badge case (F) is unrelated and must not be
"fixed" as part of this step.

---

## 14. Regression strategy

1. **Full suite** — `composer test` must be green. Note the known flaky case (F) and re-run any
   failure in isolation before attributing it to this step.
2. **MySQL group** — `vendor\bin\phpunit -c phpunit.mysql.xml` (3 tests, 21 assertions) must stay
   green; this guards Step 1's locking behavior.
3. **Steps 1–7 unchanged** — the change is read-path only; `SaleTest`, `SaleHistoricalSnapshotTest`,
   `InventoryTest`, `ProductTest`, `ClientTest`, `ApiAuthorizationTest`, `ApiEndpointsTest`,
   `LoginThrottleTest`, `ApiAuthThrottleTest`, `ApiTokenExpirationTest`, `SalesSellerScopingTest`,
   `ReportTest`, `DashboardTest`, `AuditLogTest` all remain green.
4. **Dev-DB safety** — capture `sales`, `sale_items`, `products`, `clients`, SUM(subtotal),
   SUM(total), SUM(tax_amount) and `personal_access_tokens` before and after; all identical. This
   step must write nothing.
5. **Schema** — `migrate:status` unchanged, all `Ran`, nothing pending.
6. **Static checks** — `vendor\bin\pint --test` clean; `php -l` clean on every changed file.
7. **Explicit non-regression on admin/encargado** — case 6 above is the one most likely to break
   silently, since scoping is a no-op for those roles and a mistake is invisible from a seller test.

---

## 15. Deployment considerations

- **No migration, no config change, no new env key, no new scheduled job.** Deployment is a code pull
  + `composer install` + asset build, identical to Steps 6 and 7.
- **No client or integration change.** The API response shape for clients is unchanged except that
  seller-scoped aggregates are now correct; the `data` / `meta` structure is preserved.
- **No migration lock-out risk**, no long-running DDL, no downtime.
- The existing Step 7 deployment requirement stands unchanged: production must run
  `* * * * * php artisan schedule:run` for token pruning.
- Nothing in this step depends on TLS, on the proxy topology, or on a queue worker.

---

## 16. Risks and trade-offs

| Risk | Likelihood | Mitigation |
|---|---|---|
| A seller legitimately needs a client's full history for service reasons, and the fix removes it | Medium — this is the product decision being made | The shared **registry** stays open; only per-seller sales are scoped. `encargado`/admin keep the global history. Decision should be confirmed with the business owner before implementation |
| `withSum` closure scoping produces subtly different SQL than the listing scope (e.g. null handling on unpaid) | Low | Assert the aggregate numerically in tests (cases 3 and 5) rather than structurally |
| Over-scoping breaks a legitimate `encargado` workflow | Low | `scopeVisibleTo` is a no-op for non-sellers; case 6 pins this |
| Scope drift: a future endpoint adds client sales without scoping | Medium | Centralize the scoped relation in the controller/model rather than repeating the closure; keep the client cases in the existing Step 6 suite so any regression is caught |
| Hiding aggregate columns for sellers is seen as removing a feature | Medium | Prefer showing scoped values over hiding; if hidden, record it as an intentional product decision |
| Capping `limit` breaks an unknown API consumer | Low | No API client exists in the repo; the cap is generous (100) and Step 7 already placed clients on a 12-hour re-login cycle |
| Scope creep into report/perf fixes | Medium | Hard out-of-scope list in §9; unrelated fixes go to their own steps |

**Trade-off accepted:** seller isolation is enforced at the query layer rather than by hiding columns
in the view. That is the more robust of the two (it cannot be bypassed by another entry point) and
matches the pattern Step 6 already established for sales.

---

## 17. Recommended follow-up steps

Ordered; **no implementation detail planned here.**

- **Step 9 — Inventory and sale-write integrity.** B-1 (cancel with archived product loses stock and
  falsifies the ledger), B-2 (product stock without transaction/lock), B-3 (no sale-row lock on
  update/cancel), B-4 (archived products sellable). One coherent "finish Step 1" step; mostly
  `withTrashed()->lockForUpdate()` discipline, no migration.
- **Step 10 — Production deployment defaults (cheap, do early).** D-1 (`APP_DEBUG=false` default),
  D-2/D-3 (framework bump clearing all three advisories), D-4 (guard the seeder and remove the
  published password), D-5 (drop SVG from the upload allow-list), D-10 (log rotation + excel temp
  cleanup), D-11/D-12 (document `storage:link` and the cron), plus the one-line fix for the flaky
  `ProductTest` factory. Almost entirely config, `.env.example`, `.htaccess`, `README.md` and seeder
  guards — all Low complexity, and the highest severity per unit of risk in the entire backlog.
- **Step 11 — API hardening and report performance.** D-6 (`throttleApi` + per-route limits), D-7
  (global `per_page` cap + validation for all GET filters), E-1 (chunked exports), E-2 (sargable
  date ranges), E-3 (index migration), E-5 (PDF eager-load), E-4 (dashboard aggregation), E-9
  (`paid_at`), B-5/B-6/B-7.
- **Step 12 — Correctness, audit coverage and lifecycle.** E-6/E-7/E-8 (archived-product and
  snapshot consistency across reports and the API), E-8's live-client-identity leak in the API
  payload, E-11 (audit PII/cost redaction), C-5 (revoke sessions/tokens on password change), C-3
  (audit `User` mutations), C-1 (policy / FormRequest `authorize()` cleanup, opportunistic only).
- **Deferred / revisit-on-deployment:** trusted proxies (never with `'*'` — it would break Step 5
  throttling), session `Secure` cookie (only once TLS is confirmed), CSP hardening beyond
  `object-src` / `base-uri` / `form-action` (a nonce pipeline would break the anti-FOUC script and
  Alpine), token ability enforcement (no consumer needs it yet), client PII role/field policy,
  `per_page`/limit semantics for chunked exports.

---

## 18. Approval checklist

All items **approved** on 2026-10-05. Decisions taken:

| Item | Decision |
|---|---|
| Step 8 scope | Complete seller data isolation on client surfaces (web + API) |
| Registry visibility | Client registry stays **shared**; creating/registering clients remains open to every role |
| Seller sales visibility | A `vendedor` sees **only their own** sales attached to a client |
| Global visibility | `admin` and `encargado` retain global client sales visibility |
| Seller aggregates | Remain **visible and scoped** (`sales_count`, `sales_total`, `pending_balance`); not hidden |
| Limit cap | **100** items maximum on the four client endpoints |
| Scope reuse | Reuse `Sale::visibleTo()`; no second implementation; scope semantics untouched |
| Migration | **None** permitted |
| Out of scope | Sales write, inventory, product, Sanctum, auth, Step 6 behavior, routes/middleware (unless strictly necessary), exports, reports, unrelated performance |
| Test home | New `tests/Feature/ClientSellerScopingTest.php` |
| Step 9 ordering | Inventory integrity next (B-1..B-4), then production defaults as Step 10 |
| Step 6 doc correction | Permitted |

---

## 19. Implementation Record (2026-10-05)

Approved plan executed in full as scoped.

### Business rules shipped

- **Rule C — Shared registry.** The client registry (index, create, store) remains open to every role.
  No role middleware was added or changed.
- **Rule D — Seller-scoped client sales.** On the client index, client detail, and both API client
  endpoints, any sales-derived value shown to a `vendedor` is restricted to `seller_id = auth id`.
  A `vendedor` receives their own invoices only — no colleague invoice number, total, date, seller
  name or paid state.
- **Rule E — Global visibility preserved.** `admin` and `encargado` see company-wide client sales and
  aggregates, unchanged.
- **Rule F — Seller aggregates stay visible.** `sales_count`, `sales_total` and `pending_balance` are
  rendered for sellers with correctly scoped values, computed in SQL. Nothing was hidden.
- **Rule G — Client list ceiling.** `per_page` (index) and `limit` (detail) are capped at **100** for
  both the web-facing API payload and nested sale listings.

### Files changed

- `app/Http/Controllers/ClientController.php` — `index` scopes the three aggregates with
  `withCount`/`withSum` closures using `Sale::visibleTo()`; `show` scopes the sales relation and the
  `unpaid_total` sum. All at SQL level; no PHP-side filtering.
- `app/Http/Controllers/Api/ClientController.php` — same SQL-level scoping on `index` and `show`;
  `MAX_ITEMS = 100` caps `per_page` and `limit`. Response envelope unchanged; `PaginatesToJson`
  reused as-is.
- `app/Models/Client.php` — `getPendingBalanceAttribute()` now reuses `Sale::visibleTo()` in its
  fallback query. Without this, a seller viewing a client with no visible sales got `NULL` from the
  scoped sum and fell through to an **unscoped** receivables query, leaking the colleague's balance.
  This closes a second leak path that the plan had not identified.
- `resources/views/clients/show.blade.php` — seller-specific empty state; no column removal or
  redesign.
- `lang/es/app.php`, `lang/en/app.php` — new `app.clients.no_visible_history` key.
- `tests/Feature/ClientSellerScopingTest.php` — new, 11 tests / 94 assertions, deterministic
  fixtures (explicit totals, tax and paid flags; no random factory values in scoping assertions).
- `tests/Feature/ClientTest.php` — one fixture corrected: `test_show_displays_purchase_history_and_pending_balance`
  used `User::factory()->create()` (a `vendedor`) as the viewer while the sales belonged to another
  seller, which encoded the pre-fix behavior. Changed to `manager()`.

### Two bugs found and fixed beyond the literal plan text

1. **API `sales_total` was always `0`.** The endpoint read `$client->sales_total`, but
   `withSum('sales', 'total')` aliases to `sales_sum_total`, so the field never resolved. Fixed by
   aliasing the sum to `sales_total` in the API query only. The web index deliberately keeps
   `sales_sum_total` because `clients/index.blade.php:91` reads that attribute.
2. **`pending_balance` unscoped fallback.** Described above. This was a real cross-seller receivable
   leak on the client index for any client a seller had never served.

### Not modified, per approved scope

`sale write logic`, `inventory logic`, `product logic`, Sanctum, authentication, Step 6
seller-scoping semantics, `routes/web.php`, `routes/api.php`, exports, reports, `Sale::visibleTo()`,
and all database migrations.

### Verification

- `composer test` → **269/269 passed, 1,344 assertions**.
- `vendor\bin\phpunit -c phpunit.mysql.xml` → **3/3 passed, 21 assertions**.
- `vendor\bin\pint --test` → **passes** (one `single_blank_line_at_eof` fix applied to the new test).
- `php -l` → clean on all 7 changed PHP files.
- `php artisan migrate:status` → all 16 migrations `Ran`, none pending. No migration added.
- Dev database before/after **byte-identical**: 180 sales / 450 sale_items / 37 products / 22 clients
  / 0 tokens; `SUM(subtotal)=131,404.99`, `SUM(total)=147,173.59`, `SUM(tax_amount)=15,768.60`.
- Steps 1–7 intact: the full suite includes `SalesSellerScopingTest` (16 tests),
  `ApiTokenExpirationTest` (9 tests) and the Step 1–5 integrity suites, all green.
- Final `git status`: clean apart from the Step 8 documentation commit itself.

### Commits

- Implementation: `56fc5b3` — `feat: complete seller isolation on client surfaces`.
- Documentation: this commit — `docs: record step 8 implementation evidence`.

### Deviation from the approved scope

None that add or alter behavior. Two implementation notes worth recording, both strictly
narrowing rather than widening: the `Client` accessor change in §19 and the `sales_total` alias
correction. Both were required to satisfy approved requirement B (scoped aggregates) and were
flagged before the final run.

---

IMPLEMENTATION STATUS: IMPLEMENTED AND VERIFIED

APPROVAL REQUIRED: NO