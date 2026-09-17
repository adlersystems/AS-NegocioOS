# Phase 1 — Step 4: API Authorization Matrix & Commercial Data Secrecy (Implementation Plan)

Status: ACTIVE — implemented and merged to `main` on 2026-09-16 (see §15).
Scope: close the API role-gap and restrict production cost / margin / inventory valuation to `admin` + `encargado` on every surface (web, API, dashboard); align API authorization with the web role matrix.
Explicitly deferred: login/web brute-force throttling, Sanctum token expiry/abilities, seller-sales ownership scoping and `seller_id` attribution, client-PII individual-view restriction, security headers, pagination caps (see §7).

---

## 1. Executive summary

The audit found two coupled, high-value gaps that define Step 4:

1. **The API has no coherent authorization model.** Only `/api/settings` carries a role gate (`routes/api.php:34-35`). The other 10 data endpoints are `auth:sanctum` + nothing — a `vendedor` token can read exactly what the web UI reserves for `admin`/`encargado` (`routes/web.php:64-92`), and can read full PII, per-item cost, margin and seller breakdowns.
2. **Production cost / margin / inventory valuation leak to every authenticated role.** On the web, `GET /products/{id}` (`routes/web.php:126`, auth-only) renders `production_cost`, `margin` and stock value (`products/show.blade.php:68-77`; `ProductController.php:73-78`), and the dashboard KPI `inventory_value` is cost-derived (`dashboard/index.blade.php:49`). The API exposes `production_cost`/`margin`/`stock_value` (`Api/ProductController.php:34,36,64-67`), per-item `cost` on invoices (`Api/SaleController.php:79`), margin reports (`Api/ReportController.php:53-56` via `ReportService::productsReport`, `ReportService.php:157-158`) and `inventory_value` (`Api/DashboardController.php:81`).

Step 4 will: (a) give every API endpoint a role gate consistent with the web matrix, (b) hide cost/margin/inventory-valuation from roles that are not `admin`/`encargado` on every surface (web + API), and (c) add the authorization/data-exposure regression tests to lock the matrix. No migrations, no schema change, no new architecture.

---

## 2. Current authorization model (verified)

### Roles (`app/Models/User.php:21-31`)
- `admin` — full access; `ROLES = [admin, vendedor, encargado]` (`User.php:27-31`).
- `encargado` — products/inventory/reports auth expected; no sale creation.
- `vendedor` — front-desk seller: creates sales, reads business data.

### Helper predicates (`User.php:80-119`)
| Helper | Members | Used for |
|---|---|---|
| `canWriteProducts()` | admin, encargado | product CRUD (`User.php:80-83`) |
| `canWriteSales()` | admin | edit/update/delete sales (`User.php:88-91`) |
| `canSell()` | admin, vendedor | create sale (`User.php:96-99`) |
| `canManageClients()` | admin, encargado | client update/delete/exports (`User.php:108-111`) |
| `canTogglePaid()` | admin, encargado | paid toggle (`User.php:116-119`) |
| `RoleMiddleware` | exact string match → 403 | `app/Http/Middleware/RoleMiddleware.php:14-23`; alias `role` at `bootstrap/app.php:22-24` |

### Web matrix (ground truth, `routes/web.php`)
| Surface | Routes | Gate |
|---|---|---|
| sales create/store | 51-54 | `role:admin,vendedor` |
| sales edit/update/delete | 57-62 | `role:admin` (controller `canWriteSales` at `SaleController.php:96,111,179`) |
| sales index/show/invoice PDF/export PDF/export Excel | 40-45,49-50,55-56 | `role:admin,vendedor,encargado` |
| sales paid toggle | 46-47 | `role:admin,encargado` |
| inventory (index/create/store/export pdf/excel) | 64-73 | `role:admin,encargado` |
| reports (index/export pdf/excel) | 75-82 | `role:admin,encargado` |
| settings / audit / users | 84-101 | `role:admin` (Users also `isAdmin()` in requests) |
| clients update/destroy/export pdf/excel | 106-115 | `role:admin,encargado` (+ `canManageClients()` in `ClientController.php:71,82,99,119`) |
| clients index/show/create/store | 103-104,116-117 | authenticated only (intentional: creation open, Step 3) |
| products index/show | 119,126 | authenticated only (**no gate — part of Step 4 web fix**) |
| products create/store/edit/update/destroy | 121-132 | `role:admin,encargado` (+ `canWriteProducts()` in `ProductController.php:44,51,91,98,117`) |
| dashboard | 38 | authenticated only (**no gate — dashboard leaks cost-derived KPI and per-seller data**) |

### API matrix (ground truth, `routes/api.php`)
| # | Endpoint | Auth | Role gate | Ownership scope | Sensitive fields |
|---|---|---|---|---|---|
| 1 | POST `auth/login` | none | none (no throttle) | n/a | returns full user (passwords hidden) |
| 2 | POST `auth/logout` | sanctum | none | own token only | — |
| 3 | GET `me` | sanctum | none | own user | — |
| 4 | GET `dashboard` | sanctum | **none** | business-wide | receivables, revenue, **inventory_value**, **by_seller** chart |
| 5 | GET `clients` | sanctum | **none** | all clients | **email, phone, nit, address**, pending_balance |
| 6 | GET `clients/{client}` | sanctum | **none** (IDOR) | any client by id | **email, phone, nit, notes**, full sales list w/ seller names |
| 7 | GET `products` | sanctum | **none** | all products | **production_cost, margin**, sale_price, stock |
| 8 | GET `products/{product}` | sanctum | **none** (IDOR) | any product | **production_cost, margin, stock_value**, movement user names |
| 9 | GET `sales` | sanctum | **none** | all sales; `seller_id` filter | client **nit**, seller name, totals |
| 10 | GET `sales/{sale}` | sanctum | **none** (IDOR) | any sale | client **nit/email**, per-item **cost** |
| 11 | GET `inventory` | sanctum | **none** | all movements | actor names, product info |
| 12 | GET `reports` | sanctum | **none** | business-wide; `seller_id` filter | **margin** (products), **nit**, seller names, inventory `value` (cost-derived) |
| 13 | GET `settings` | sanctum | `role:admin` | all settings | company NIT/phone/email (correctly gated) |

---

## 3. Findings

### CRITICAL

**F-API-01 — API data endpoints have no role authorization (whole API exposure).**
- Affected roles: `vendedor` (escalation by omission), `encargado` (unintended bulk access).
- Resource: all API resources except `settings`.
- Evidence: `routes/api.php:15-35` (only settings gated); no `abort_unless`/`Gate` in any `Api\*Controller` (`Api/ProductController.php`, `Api/SaleController.php`, `Api/ClientController.php`, `Api/DashboardController.php`, `Api/InventoryController.php`, `Api/ReportController.php`).
- Current behavior: a `vendedor` token yields the same read access a web `admin`/`encargado` has to reports, inventory history, cost/margin and business-wide aggregates (`Api/DashboardController.php:37-42,66-81`; `Api/ReportController.php:53-56`).
- Why it matters: the web UI deliberately restricts these surfaces (`routes/web.php:64-92`); the API is a wholesale bypass — privilege escalation by missing check, not by flawed logic.
- Recommended action: mirror the web matrix on the API (reports/inventory → `role:admin,encargado`; dashboard cost/seller data gated by a `canViewCosts()`-style helper; product/sale payloads conditionally include cost/margin/stock-value fields only for `admin`/`encargado`).
- Step 4: **YES** (core).

**F-API-02 — Unscoped resource fetch (IDOR) on `{client}`, `{product}`, `{sale}`.**
- Affected roles: any authenticated token.
- Evidence: `Api/ClientController.php:44-75`, `Api/ProductController.php:46-80`, `Api/SaleController.php:49-84`; routes `api.php:22,25,28`.
- Current behavior: sequential-id reads of any record, incl. NIT/phone/email/notes/cost.
- Why it matters: with F-API-01 fixed, `vendedor` could still read any invoice/client/product by id.
- Recommended action: fold into the Step 4 matrix — `vendedor` keeps `GET /sales` + `GET /sales/{sale}` (business-wide sales visibility is intended on the web: `routes/web.php:49-50,55-56`), but the write-gated data (item `cost`) is conditionally included; inventory/reports endpoints are role-gated so their IDOR paths disappear by authorization.
- Step 4: **YES** (merged into F-API-01 fix).

### HIGH

**F-WEB-01 — Production cost / margin / stock value visible to every role on `GET /products/{id}`.**
- Affected roles: `vendedor` (and any authenticated user).
- Evidence: `routes/web.php:126` (auth-only); `ProductController.php:73-78`; `products/show.blade.php:68,71,77`.
- Current behavior: UI hides cost metrics behind the sidebar, but direct URL + rendered `<x-metric>` expose `production_cost`, `margin`, margin rate and `stock_value`.
- Why it matters: cost is commercially sensitive; `canWriteProducts()` (admin/encargado) is the design boundary for cost (`User.php:80-83`).
- Recommended action: pass cost metrics to the view only when `canViewCosts()`; otherwise render sale_price/stock-only metrics.
- Step 4: **YES** (web half of the commercial-secrecy fix).

**F-WEB-02 — Dashboard leaks cost-derived `inventory_value` and per-seller revenue to all roles.**
- Affected roles: `vendedor`.
- Evidence: `DashboardController.php:37-42,66-68,103-106`; `dashboard/index.blade.php:49,84-88`.
- Current behavior: `inventory_value = SUM(stock × production_cost)` and the by-seller chart (all sellers' totals) reach the full dashboard data even when the seller role has no business for either.
- Why it matters: exposes both the cost base and every seller's performance.
- Recommended action: gate `inventory_value` and `by_seller` by `canViewCosts()` (admin+encargado); keep operational counts/recent-sales for vendedor.
- Step 4: **YES** (dashboard half of the commercial-secrecy fix).

**F-EXPORT-01 — Sales exports (PDF/Excel) are open to `vendedor` and cover every seller.**
- Affected roles: `vendedor`.
- Evidence: `routes/web.php:40-43`; `SaleController.php:224-246,248-259`; `SalesExport.php:26-33`.
- Current behavior: any seller downloads the full sales ledger (all sellers, client NITs, notes) with no scoping.
- Why it matters: bulk PII + business-wide ledger access, acknowledged as a Step 3 deferral (`docs/phase1-step3-plan.md:79`). The columns themselves are clean (no cost/margin — `SalesExport.php:40-72`).
- Recommended action: keep the column set (already clean); defer scoping to the seller-ownership item (F-DEF-03) rather than change behavior silently. **Re-verify only** in Step 4 tests that the export continues to work for all three roles with unchanged columns.
- Step 4: verify/regression only; behavior change **deferred**.

**F-AUTH-01 — No login/API brute-force throttling.**
- Affected roles: guests/adversaries.
- Evidence: `LoginController.php:19-38` (`POST /login`, `routes/web.php:25`), `Api/AuthController.php:13-33` (`POST /api/auth/login`, `routes/api.php:13`); zero `RateLimiter`/`throttle` in app (`bootstrap/app.php:17-25` has no `throttleApi()`; `config/auth.php:100` throttles only the password-reset broker, not login).
- Why it matters: unmitigated brute-force on both credential entry points.
- Recommended action: `RateLimiter::for('login')` keyed by email+IP, applied to both `POST /login` and `POST /api/auth/login`.
- Step 4: **deferred** to a dedicated authentication step (independent of the API matrix; see §7). Listed here as verified-HIGH.

**F-AUTH-02 — Sanctum tokens never expire and are un-scoped.**
- Affected roles: any token holder.
- Evidence: `config/sanctum.php:55` (`'expiration' => null`); `Api/AuthController.php:27` (`createToken('api')` — ability never checked; no `tokenCan`/`CheckAbilities` anywhere).
- Why it matters: a leaked token is valid indefinitely with full read access.
- Recommended action: set token `expiration` and/or issue per-role tokens (deferred — independent decision work, see §7).
- Step 4: **deferred**.

**F-INTEG-01 — `vendedor` can attribute a sale to any `seller_id`.**
- Affected roles: `vendedor`.
- Evidence: `StoreSaleRequest.php:23` (`exists:users,id`, no ownership); `SaleController.php:72`; seller `<select>` lists all users (`sales/form.blade.php:48-54`).
- Why it matters: sales-attribution integrity (priority 4) — a seller can inflate/skew another seller's or an admin's ledger.
- Recommended action (deferred here): force `seller_id` = `auth()->id()` for `vendedor` on `store()`.
- Step 4: **deferred** (independent of the secrecy scope; preserves single focus).

### MEDIUM

**F-API-03 — Unbounded pagination/`limit` on API list/show endpoints (`per_page`, `limit`).**
- Evidence: `Api/ClientController.php:24,51`; `Api/ProductController.php:23,51`; `Api/SaleController.php:28`; `Api/InventoryController.php:29`.
- Current behavior: `?per_page=1000000` dumps whole tables in one response.
- Recommended action: cap `per_page`/`limit` (e.g. `min(…, 100)`), and paginate report collections (`ReportService.php:54,101,121,160` currently `.get()` over arbitrary date ranges).
- Step 4: **deferred** (independent abuse control; low urgency at MVP scale). Regression tests only.

**F-PII-01 — Client PII (NIT/phone/email/address) visible to every authenticated role on individual views and API.**
- Affected roles: `vendedor`.
- Evidence: `clients/index.blade.php:80`; `clients/show.blade.php:30-49`; `routes/web.php:116-117` (auth-only); `Api/ClientController.php:30-34,58-63`.
- Current behavior: any role can read full client contact data per-record; bulk exports are correctly gated to admin/encargado (`routes/web.php:106-109`, `ClientController.php:99,119`).
- Recommended action (decision): permit NIT (needed on invoices) but consider hiding phone/email/notes from `vendedor` on the show page; keep index minimal.
- Step 4: **deferred** (personal-data policy needs a product decision; client create/view openness was an approved Step 3 decision, `User.php:104-107`).

**F-CONF-01 — `APP_DEBUG=true` in `.env`/`.env.example`; session cookie not `Secure`-forced; `SESSION_ENCRYPT=false`.**
- Evidence: `.env: APP_DEBUG=true`; `.env.example:4`; `config/session.php:50,172` (no `SESSION_SECURE_COOKIE` anywhere).
- Recommended action: `APP_DEBUG=false` in production template; `SESSION_SECURE_COOKIE=true` behind TLS. (Deployment/config concern.)
- Step 4: **deferred** (no app-code change; note in final report).

**F-HDR-01 — No security headers beyond Apache `.htaccess` CSP (with `unsafe-inline`/`unsafe-eval`); no X-Frame-Options/HSTS/X-Content-Type-Options.**
- Evidence: `public/.htaccess:1-6`; no header middleware (`bootstrap/app.php:17-25`).
- Recommended action: header middleware (defense-in-depth). Independent.
- Step 4: **deferred**.

**F-POL-01 — Weak password policy: `Password::min(8)` only.**
- Evidence: `StoreUserRequest.php:27`; `UpdateUserRequest.php:34`; `ResetPasswordController.php:29`.
- Recommended action: add `letters`/`numbers`/`symbols`/`mixedCase` via the `Password` rule (deferred — auth-steps work).

### LOW

- **F-LOW-01** Wide-open FormRequest `authorize()` relying solely on route middleware — 8 requests return `true` (`StoreProductRequest.php:10-13`, `StoreSaleRequest.php:11-14`, `UpdateSaleRequest`, `StoreClientRequest.php:10-13`, `UpdateClientRequest.php:11-14`, `StoreInventoryMovementRequest.php:13-16`, `UpdateSettingsRequest.php:9-12`). Keep as-is (routes are the effective gate); a future route edit is the risk. Deferred.
- **F-LOW-02** Logout revokes only the current token (`Api/AuthController.php:37-45`) — tied to F-AUTH-02.
- **F-LOW-03** Token ability `'api'` meaningless (tied to F-AUTH-02).
- **F-LOW-04** API login uses the session `web` guard (`Api/AuthController.php:20`) — harmless, note only.
- **F-LOW-05** Email verification not enforced (`email_verified_at` exists, no `verified` middleware) — acceptable at MVP.
- **F-LOW-06** `POST /language/{locale}` outside the guest group (`routes/web.php:33`) — changes only the user's `language`, low risk.
- **F-LOW-07** `.env.production` (gitignored) contains a real-looking `APP_KEY`; ensure it is never committed/archived (repo root already ships an `AS-NegocioOS.zip`).

### Positives logged during audit

- No SQL injection (all filters parameterized; `like "%…%"` bound).
- No writable API surface → no CSRF/mass-assignment exposure there.
- All web writes use `validated()`; sensitive fields (`paid`, `subtotal`, `cost`, `role`, `pending_balance`, `user_id`) are not request-settable.
- User model hides `password`/`remember_token` (`User.php:15`); passwords hashed (`User.php:42`).
- `/api/settings` correctly admin-gated; client exports correctly admin/encargado-gated (Step 3).
- Sale-form JS payload carries no cost (`SaleController.php:499-510`; `sales/form.blade.php:21-30`).

---

## 4. Sensitive-data exposure matrix (verified)

| Data | admin | encargado | vendedor | Current surface |
|---|---|---|---|---|
| production cost | YES | YES (write role) | **LEAK** | web `products/show.blade.php:68` (`ProductController.php:74`); API `Api/ProductController.php:34,64`; dashboard `inventory_value` `dashboard/index.blade.php:49` |
| margin | YES | YES | **LEAK** | web `products/show.blade.php:71` (`ProductController.php:75-78`); API `Api/ProductController.php:36,66`; products report (`Api/ReportController.php:53-56`; `ReportService.php:157-158`) |
| seller information | YES | YES | YES (intended) | sales list/show/PDF/Excel seller column (`sales/index.blade.php:100`, `sales/show.blade.php:61`, `sales/export-pdf.blade.php:52`, `SalesExport.php:65`); dashboard by-seller chart (`dashboard/index.blade.php:84-88`); clients show history (`clients/show.blade.php:96`); API (`Api/SaleController.php:38,65`; `Api/ReportController.php:69`; `Api/DashboardController.php:103-106`) |
| client NIT | YES | YES | YES (partial, intended for invoices) | `sales/index.blade.php:96-98`; `sales/invoice-pdf.blade.php:64-66`; `SalesExport.php:64`; `clients/index.blade.php:80`; report blades; API sale/client/report payloads |
| client phone | YES | YES | **LEAK (individual views)** | `clients/show.blade.php:30-49`; `clients/index.blade.php:80`; `Api/ClientController.php:31,59` (bulk export correctly gated: `routes/web.php:106-109`) |
| sales data | YES | YES | YES (business-wide, intended) | `SaleController.php:35-42` (no seller scoping); `SalesExport.php:26-33`; API `Api/SaleController.php:17-47` |
| inventory valuation | YES | YES | **LEAK (cost-derived)** | `dashboard/index.blade.php:49`; `Api/DashboardController.php:81`; report `value` (`ReportService.php:96`, gated admin/encargado) |

`vendedor` cells marked **LEAK** become **NO** after Step 4. Cells marked "intended" stay as-is.

---

## 5. API authorization matrix (target)

| Endpoint | Auth | Roles (target) | Ownership scope | Cost/margin fields | Risk after fix |
|---|---|---|---|---|---|
| POST `auth/login` | none | none (+throttle deferred F-AUTH-01) | n/a | — | unchanged |
| POST `auth/logout` / GET `me` | sanctum | any | own | — | OK |
| GET `dashboard` | sanctum | any | role-scoped view | `inventory_value` only if `canViewCosts()`; `by_seller` only if `canViewCosts()` | resolves F-API-01/F-WEB-02 |
| GET `clients` / `{client}` | sanctum | any (kept from Step 3 decision) | all | — | PII individual view deferred (F-PII-01) |
| GET `products` / `{product}` | sanctum | any (read) | all | `production_cost`,`margin`,`stock_value` only if `canViewCosts()` | resolves F-API-01/F-WEB-01 |
| GET `sales` / `{sale}` | sanctum | any (business-wide read intended) | all | per-item `cost` only if `canViewCosts()` | resolves F-API-01 |
| GET `inventory` | sanctum | **`role:admin,encargado` (new)** | all | — | resolves F-API-01 (gate) |
| GET `reports` | sanctum | **`role:admin,encargado` (new)** | all | margin/value only for those roles anyway | resolves F-API-01 (gate) |
| GET `settings` | sanctum | `role:admin` | all | — | already correct |

New helper: `User::canViewCosts(): bool` → `in_array(role, [admin, encargado])` (mirrors `canWriteProducts`, `User.php:80-83`), used by web + API.

---

## 6. Recommended Step 4 scope

**"Align API authorization with the web role matrix and restrict commercial cost/margin/inventory data to `admin`/`encargado` across all surfaces."**

Why this is the one coherent Step 4:
- **Highest-severity cluster with a single root cause:** the API has one missing-gate family (F-API-01) and the cost/margin secrecy gap is one decision (F-WEB-01 + F-WEB-02 + F-API-01 cost fields = "who sees commercial data"). Fixing them yields one consistent, testable invariant: every sensitive dollar field is admin/encargado-only across web + API.
- Natural continuation of Step 3 (authorization work), bounded: 1 model helper, ~6 controller touch-points, ~4 route lines, ~4 views, no migration.
- Does not require owner-only business decisions (seller ownership scoping, PII visibility policy) — those stay deferred.

Exact work:
1. `User::canViewCosts()` (admin, encargado).
2. API routes: `inventory` + `reports` get `role:admin,encargado` (`routes/api.php`).
3. API controllers: conditionally include `production_cost`/`margin`/`stock_value` (product list+show), per-item `cost` (sale show), `inventory_value` + `by_seller` (dashboard).
4. Web: `ProductController::show` computes cost metrics only when `canViewCosts()` (`ProductController.php:73-78`); `products/show.blade.php` conditionally renders cost/metric cards. `DashboardController`/`dashboard/index.blade.php` gate `inventory_value` KPI and by-seller chart.
5. Tests (see §10) + full regression.

---

## 7. Explicitly deferred findings

| Finding | Why deferred |
|---|---|
| F-AUTH-01 login/API throttling | Independent of data exposure; deserves its own auth step (measures, tests, `RateLimiter::for` config). Highest-ROI standalone item — candidate **Step 5**. |
| F-AUTH-02 token expiry/abilities + F-LOW-02/03 | Requires a token-lifecycle decision (expiry policy, per-role tokens, global revocation); independent of the API matrix. |
| F-INTEG-01 seller attribution (`seller_id`) | Sale-integrity fix; independent of secrecy. Small change; do in its own step to keep Step 4 single-focused. |
| F-EXPORT-01 sales-export scoping | Behavior change tied to a business decision on whether sellers may see each other's ledgers (the web sales index is already business-wide). Columns already clean; regression-only in Step 4. |
| F-PII-01 individual client PII visibility | Product/policy decision on phone/email/notes for `vendedor`; bulk export already restricted (Step 3). |
| F-API-03 pagination/unbounded reports | Abuse control; low urgency at MVP scale; independent. |
| F-CONF-01 / F-HDR-01 | Deployment/config + header defense-in-depth; no app logic. |
| F-POL-01 password complexity | Auth-step item (with F-AUTH-01). |
| F-LOW-01 open FormRequests | Defense-in-depth; current route wiring is sound. |
| F-LOW-04/05/06/07 | Low value; note only. |

---

## 8. Implementation strategy

1. `User::canViewCosts()` (admin/encargado), following the existing helper style (`User.php:80-111`).
2. API routes: add `role:admin,encargado` to `inventory` (`routes/api.php:30`) and `reports` (`routes/api.php:32`).
3. API payload refactor (conditionals keyed on `auth()->user()?->canViewCosts()`):
   - `Api/ProductController` list+show (`:34,36,64-67`);
   - `Api/SaleController::show` item `cost` (`:79`);
   - `Api/DashboardController` `inventory_value` + `by_seller` (`:37-42,66-68,81,103-106`).
4. Web: `ProductController::show` (`:73-78`) + `products/show.blade.php` (`:64-82`) conditional; `DashboardController` (`:37-42,66-68`) + `dashboard/index.blade.php` (`:49,84-88`) conditional.
5. Tests (new + adjusted): role/per-role assertions; negative tests; data-exposure assertions (see §10).
6. `composer test`, `vendor\bin\phpunit -c phpunit.mysql.xml`, `vendor\bin\pint --test`, `php -l` on touched files; document → ACTIVE; commit & push (after approval).

---

## 9. Database impact

**None.** No migrations, no schema change, no seeders. All changes are authorization logic, route middleware, view conditionals and payload shaping.

---

## 10. Test strategy

New/extended tests (feature + API, SQLite suite; extend `ApiEndpointsTest`, `ApiAuthTest`, `RoleAccessTest`, `ProductTest`, `DashboardTest`):

1. **API role matrix (positive/negative)**
   - `vendedor` token → `GET /api/inventory` 403, `GET /api/reports` 403; `admin` + `encargado` → 200.
   - `admin` token → `GET /api/settings` 200 (existing; keep `ApiAuthTest.php:85-94`).
2. **API data exposure**
   - `vendedor`: `GET /api/products` + `/api/products/{id}` JSON **omits** `production_cost`, `margin`, `stock_value`; `admin`/`encargado` **include** them.
   - `vendedor`: `GET /api/sales/{id}` item rows omit `cost`; admin/encargado include it.
   - `vendedor`: `GET /api/dashboard` omits `inventory_value` and `charts.by_seller`; admin/encargado include them.
   - `vendedor`: `GET /api/clients`/`/api/clients/{id}` unchanged (Step 3 decision) — regression assert NIT visible on sale payloads.
3. **Web exposure**
   - `vendedor` GET `products/{id}` → 200, `assertDontSee` production-cost/margin/stock-value metric labels and values; `admin` asserts `assertSee`.
   - `vendedor` GET `dashboard` → 200, `assertDontSee('inventory_value')` and by-seller chart data; `admin`/`encargado` assert see.
   - Regression: reports/inventory/settings/audit/users still forbidden for `vendedor` (existing `RoleAccessTest`, `ApiAuthTest`).
4. **Exports regression** (columns unchanged): sales export still 200 for `admin`, `encargado`, `vendedor`; output contains no cost/margin columns (`SalesExport.php:40-72`).
5. **Model-level** `User::canViewCosts()` unit assertions (admin/encargado true; vendedor false).

Negative/forbidden and API tests are explicit; add `assertForbidden`/`assertJsonMissing` variants for the 403 and omitted-field cases.

---

## 11. Verification strategy

- `composer test` (full SQLite suite; config:clear + phpunit).
- `vendor\bin\phpunit -c phpunit.mysql.xml` (MySQL group: concurrency + lifecycle — 3 tests).
- `vendor\bin\pint` + `vendor\bin\pint --test`.
- `php -l` on all touched files.
- Manual spot-verify with a `vendedor` session + `vendedor` token against the dev DB if convenient.
- Fresh-install verification covered by the suite (fresh migrate per PHPUnit).
- No DB-level migration verification required (no migrations).

---

## 12. Regression risks

- Existing `ApiEndpointsTest` runs as **admin** (`ApiEndpointsTest.php:24`) — admin assertions on product `margin`/`stock_value` and sale-item `cost` must keep passing (fields remain for admin). No existing test asserts those fields for other roles (re-verified during implementation: `ApiEndpointsTest`, `ApiAuthTest`, `RoleAccessTest`, `ProductTest`, `DashboardTest` use admins/managers).
- `DashboardTest` renders metric markup (`DashboardTest.php:33-51`) — keep the admin/manager path identical.
- Web `products/show.blade.php` `@php` block references `$stockValue`/`$marginRate` (`:4-5`) — keep the variables defined (or guard the blade) to avoid `Undefined variable`.
- Reports PDF/Excel: `ReportController::tabular` consumes `ReportService::$margin` for products type (`ReportController.php:121-124`) — unchanged (admin/encargado only).
- `Api\DashboardController` and web `DashboardController` share chart shapes — keep admin output stable for tests.

---

## 13. Complexity estimate

**Small–Medium.**

- Justification: 1 model method, ~6 controller touch-points, 2 route lines, 2 views, ~8-10 new/extended test methods. No migrations, no schema, no new services, no API restructure. Mirrors the mechanical shape of Step 3's authorization wiring. Risk localized to payload/assertion drift, mitigated by explicit admin-path regression tests.

---

## 14. Suggested commit

`feat: scope commercial cost and margin data to authorized roles`

(Covers the API matrix + web/API cost/margin/inventory hiding.)