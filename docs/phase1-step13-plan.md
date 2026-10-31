# Phase 1 — Step 13 Plan

Status: IMPLEMENTED AND VALIDATED — AWAITING USER REVIEW; NOT COMMITTED OR PUSHED
Author: audit performed on `main` at `50610d1`; implementation completed on 2026-10-31
Date: 2026-10-31
Approval: the earlier plan-doc approval was accidental and remains withdrawn. The user later explicitly
authorized implementation; the four limits in §7 are now in effect in the working tree.
Scope type: API rate limiting (D-6 only; no migration)

---

## 1. Objective, business context and scope

Audit the pre-implementation API rate-limiting posture and implement bounded protection against excessive
requests, abuse and resource exhaustion **without disrupting legitimate business operations**.

### 1.1 Business context and confirmed account/audit requirements

- NegocioOS is operated as a centralized business-management system. Business operations must be
  attributable to the authenticated employee who performed them.
- **Every employee must have an individual user account.** Shared employee accounts are **not** part of
  the intended operating model.
- Individual accounts are required for reliable audit attribution. Operations must be attributable to a
  specific employee, not to a shared terminal or team login.
- This confirmed policy supports **user-based rate limiting** for authenticated API routes. The
  unauthenticated login route remains **IP-based**, because no user is authenticated yet.
- These are confirmed business decisions, not inferred implementation assumptions.

### 1.2 Deployment architecture (confirmed)

- NegocioOS uses a **centralized server architecture**. Clients connect to a central NegocioOS server per
  installation; the application is not designed as a distributed or edge-deployed system.
- Three hosting scenarios must be supported:
  1. A physical server managed by the client.
  2. Hosting contracted and managed by the client.
  3. Hosting provided and managed by Adler Systems.
- The application topology is centralized, but reverse-proxy, load-balancer and TLS-termination details
  differ between these scenarios. `TrustProxies` configuration must therefore be established per
  deployment environment; no universal proxy configuration is assumed. See §3.7 and §9.4.
- The hosting scenarios are deployment inputs, not changes to the application in this step.

### 1.3 Future external integrations (confirmed architectural requirement)

- NegocioOS must be designed to support integrations with external systems and applications in the
  future, even though **no external API consumers are currently identified or required for the initial
  release**.
- The API should remain suitable for future consumption by external applications and systems. Do not
  assume that the API will only ever be consumed by NegocioOS's own frontend or internal components.
- This step preserves the existing API architecture and Sanctum authentication approach. **No additional
  authentication mechanisms are implemented now.**
- Future integrations must be identifiable and manageable independently. They must **not** use or share
  employee credentials; integration credentials and employee credentials must remain conceptually and
  operationally separate. The future design should allow identifying which integration originated a
  request.
- Future external integrations may require dedicated rate-limit policies, quotas or request budgets.
  Those policies must be evaluated when actual integrations, expected traffic and operational
  requirements are known. **No integration-specific limits are introduced in Step 13.**
- Future API changes should consider compatibility with existing external integrations. API versioning
  and integration lifecycle management may be evaluated in a future dedicated architectural step; they
  are not implemented now.
- These are **confirmed architectural requirements for future readiness**, not implemented functionality
  in this step. See §15 for the deferred design considerations.

### 1.4 In scope (D-6 only)

- `routes/api.php` and the API middleware stack.
- Existing throttling and Laravel rate-limiter usage.
- Sanctum authentication and token behavior as it relates to throttling.
- Public, authenticated, read-only and (currently non-existent) write API endpoints.
- Endpoint-category and role considerations.
- POS compatibility (sales and inventory workflows).
- Regression tests, validation and acceptance criteria for the change.

This document began as a plan. The authorized implementation is now in the working tree; the files changed
are listed in §10 and the validation evidence is in §12. **No schema, migration, configuration, dependency
or web-route change has been made**, and nothing has been committed or pushed.

---

## 2. Verified baseline

Observed directly before this audit:

- Branch `main`, working tree **clean**; `git status -sb` → `## main...origin/main`.
- `HEAD = 50610d1` (`docs: record step 12 implementation evidence`); local and remote synchronized.
- MySQL is listening on `127.0.0.1:3306` (verified with `netstat`).
- Recorded test baseline (end of Step 12): SQLite **333 passed / 1,582 assertions**; `mysql` group
  **8 passed / 70 assertions**.
- Recorded migrations (end of Step 12): **16**, all `Ran`, none pending.
- Recorded dev-database metrics (end of Step 12): sales 180; sale_items 450; products 37;
  products_trashed 0; clients 22; `SUM(subtotal)` 131404.99; `SUM(total)` 147173.59;
  `SUM(tax_amount)` 15768.60; `SUM(inventory_movements.quantity)` 3732; inventory_movements 487;
  `personal_access_tokens` 0; `audit_logs` 0.
- Framework/runtime: Laravel `^13.17`, PHP `^8.3`, PHPUnit `^12.5.12` (`composer.json`).
- Test environment: `CACHE_STORE=array`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (`phpunit.xml`).
- Application environment: `CACHE_STORE=database`, `SESSION_DRIVER=database`, `DB_CONNECTION=mysql`,
  `DB_DATABASE=as_negocios` (`.env`).

The recorded test and dev-DB values were the Step 12 end state and were used as the before/after reference.

Post-implementation re-check (2026-10-31): branch `main`; `HEAD = 50610d1`; the only untracked file before
implementation was this plan. MySQL was listening; migrations remained **16**, all `Ran`, none pending; the
dev-DB metrics were **identical** to the values above. The final SQLite suite passed **340 tests / 1,909
assertions**; the MySQL group passed **8 / 70**; Pint and PHP syntax checks were clean. Full evidence is in
§12.

---

## 3. API route and middleware inventory (pre-implementation baseline)

### 3.1 Route inventory (`routes/api.php`, 13 routes)

| # | Method | Path | Route name | Auth | Role restriction | Category | Relative cost |
|---|---|---|---|---|---|---|---|
| 1 | POST | `/api/auth/login` | `api.auth.login` | Public | — | Auth | High (bcrypt + token creation) |
| 2 | POST | `/api/auth/logout` | `api.auth.logout` | `auth:sanctum` | Any authenticated | Auth | Low (token delete) |
| 3 | GET | `/api/me` | `api.me` | `auth:sanctum` | Any authenticated | Auth/read | Low |
| 4 | GET | `/api/dashboard` | `api.dashboard` | `auth:sanctum` | Any authenticated | Dashboard | **High** (many aggregates) |
| 5 | GET | `/api/clients` | `api.clients.index` | `auth:sanctum` | Any authenticated | Read | Moderate (correlated subqueries) |
| 6 | GET | `/api/clients/{client}` | `api.clients.show` | `auth:sanctum` | Any authenticated | Read | Moderate |
| 7 | GET | `/api/products` | `api.products.index` | `auth:sanctum` | Any authenticated | Read | Moderate |
| 8 | GET | `/api/products/{product}` | `api.products.show` | `auth:sanctum` | Any authenticated | Read | Moderate |
| 9 | GET | `/api/sales` | `api.sales.index` | `auth:sanctum` | Any authenticated (sellers scoped) | Read | Moderate |
| 10 | GET | `/api/sales/{sale}` | `api.sales.show` | `auth:sanctum` | Any authenticated (sellers scoped) | Read | Moderate |
| 11 | GET | `/api/inventory` | `api.inventory.index` | `auth:sanctum` | `role:admin,encargado` | Read | Moderate |
| 12 | GET | `/api/reports` | `api.reports.index` | `auth:sanctum` | `role:admin,encargado` | Reports | **Very high** (unbounded scans) |
| 13 | GET | `/api/settings` | `api.settings.index` | `auth:sanctum` | `role:admin` | Read | Low |

Line references: login `routes/api.php:13`; authenticated group `:15`; logout `:16`; me `:17`;
dashboard `:19`; clients `:21-22`; products `:24-25`; sales `:27-28`; inventory `:30-31`;
reports `:33-34`; settings `:36-37`.

**Observed:** the API is **read-only**. There are no API endpoints that create/update/delete sales,
inventory, clients or products. POS sale creation (`POST /sales`), inventory updates (`POST /inventory`),
client/product writes and exports are **web (session) routes** in `routes/web.php`, not API routes.

### 3.2 Middleware inventory

- Global: no custom global middleware relevant to API throttling.
- `web` group: `SetLocale` appended (`bootstrap/app.php:18-20`).
- `api` group: framework default only — `SubstituteBindings` (and `EnsureFrontendRequestsAreStateful` only
  if `statefulApi()` were called, which it is not). **No `throttle:api` middleware.**
- Route middleware: `auth:sanctum` on all non-login API routes; `role:admin,encargado` on inventory and
  reports; `role:admin` on settings (`routes/api.php:15-37`).
- `bootstrap/app.php` never calls `$middleware->throttleApi()`, so `$apiLimiter` is `null` and the `api`
  group contained no throttle before this step (`vendor/.../Foundation/Configuration/Middleware.php:495-499,743-752`).
- `route:list --path=api -v` (observed) shows only `api`, `Authenticate:sanctum` and `RoleMiddleware`;
  **no `throttle` entry on any API route**.

### 3.3 Existing rate limiting (only login, and it is not middleware)

- `AppServiceProvider::boot()` registers a named limiter `login`:
  `Limit::perMinutes(5, 5)->by(LoginThrottle::key($request))` (`app/Providers/AppServiceProvider.php:26-29`).
- `LoginThrottle` defines `MAX_ATTEMPTS = 5`, `DECAY_MINUTES = 5`, `DECAY_SECONDS = 300`, and
  `key() = strtolower(email).'|'.$request->ip()` (`app/Support/LoginThrottle.php:9-18`).
- The **actual enforcement** is controller-level, not middleware: `ThrottlesLogins` calls
  `RateLimiter::tooManyAttempts()`, `hit()` and `clear()` manually
  (`app/Http/Controllers/Concerns/ThrottlesLogins.php:12-33`).
- Both API login (`app/Http/Controllers/Api/AuthController.php:18,26,33`) and web login
  (`app/Http/Controllers/Auth/LoginController.php:24,34,41`) use the trait.
- Behavior: only **failed** attempts are counted; malformed validation requests do not consume attempts;
  a successful login clears the key; the 6th failure within 5 minutes returns **429** with
  `errors.email` (`auth.throttle`), not a generic 429 body.
- The named `login` limiter registered in the provider is **not applied as middleware**; the controller
  calls `RateLimiter` directly. Only `ApiAuthThrottleTest::test_named_login_limiter_is_registered`
  references the named limiter.
- There is **no throttling at all** on dashboard, clients, products, sales, inventory, reports, settings,
  me or logout.
- Web password reset uses Laravel's password-broker throttle (`config/auth.php:100`, 60 seconds); this is
  unrelated to the API.

### 3.4 Authentication and Sanctum

- API authentication is token-only: `auth:sanctum`; `config/sanctum.php` sets `'guard' => []` and
  `'expiration' => env('SANCTUM_EXPIRATION', 720)` minutes (12 hours).
- Login creates a token with the explicit `api` ability:
  `$user->createToken('api', ['api'])->plainTextToken` (`Api/AuthController.php:36`).
- Routes do **not** enforce token abilities (`abilities:api` is not used); `auth:sanctum` only checks
  that the token is valid and unexpired.
- Tokens are per-login. A user can hold multiple tokens (one per device/terminal); there is no token-count
  limit in the code.
- `Sanctum::actingAs()` is used in feature tests; it installs a transient token and authenticates the
  request as the user.

### 3.5 Endpoint cost profile (why category limits matter)

- `Api/DashboardController` (`:22-44`) runs 6 monthly count queries plus 12 monthly sum queries, then
  several aggregate queries (by seller, top products, recent sales, top client, inventory value), counts,
  KPIs and alerts (`:46-105`). One dashboard request is materially heavier than a simple list request.
- `Api/ReportController::index` delegates to `ReportService` (`Api/ReportController.php:34`).
  `ReportService::salesReport()` loads **all matching sales** with `->get()` (`ReportService.php:54`);
  `inventoryReport()` loads all matching movements (`:81`); `clientsReport()` loads all clients with
  correlated counts/sums (`:121`); `productsReport()` aggregates all matching sale items (`:146`).
  Reports are **not paginated** and are the highest-cost API surface.
- `Api/ClientController::index` adds `withCount` plus two `withSum` correlated subqueries per page
  (`:21-23`), then paginates (`:26`).
- `Api/InventoryController::index`, `Api/ProductController::index/show` and `Api/SaleController::index/show`
  are paginated or bounded; Step 12 already clamps `per_page`/`limit` to `[1, 100]`
  (`Api/Concerns/PaginatesToJson.php`). Request **frequency** remains unbounded.

### 3.6 Cache, test and deployment facts relevant to rate limiting

- Rate limiting uses Laravel's cache-backed `RateLimiter`. Production cache is the **database** store
  (`config/cache.php:18,42-48`); the `cache` table migration exists
  (`0001_01_01_000001_create_cache_table.php`).
- Tests use the **array** cache (`phpunit.xml`), so limiter state is isolated per test process/app.
- No `trustProxies` configuration exists anywhere in `app/`, `bootstrap/`, `config/` or `routes/`
  (verified by search). This is not by itself a defect: the supported hosting scenarios have different
  proxy topologies. If an installation is behind a reverse proxy or load balancer, `$request->ip()` will
  be the proxy address unless `TrustProxies` is configured. If it is directly exposed, forwarded IP
  headers must not be trusted.
- `resources/js` contains **no** `/api/` calls; the only `fetch()` is `language.js:13` to the web
  `/language/{locale}` route. The current web UI does not consume the JSON API.
- Dev DB `personal_access_tokens = 0`; there is no observed API client traffic in the development
  database. Limit values therefore cannot be calibrated from observed production usage in this repo.

### 3.7 Trusted proxy configuration is environment-specific

- The confirmed centralized-server architecture does **not** imply a single reverse-proxy topology. An
  installation may be directly exposed, behind a client-managed proxy, or behind an Adler Systems-managed
  proxy.
- `TrustProxies` must be established **according to each deployment environment**:
  - identify the immediate reverse proxy/load balancer and its IP addresses or CIDRs;
  - identify which forwarded headers that proxy actually sets;
  - trust only those proxy addresses and only the required forwarded headers.
- **Do not blindly trust forwarded IP headers.** Trusting all proxies or accepting `X-Forwarded-*` from
  arbitrary clients allows a caller to spoof `$request->ip()` and evade or poison IP-based limits.
- **Do not impose a universal proxy configuration.** The three hosting scenarios may have different proxy
  layers; the correct values are deployment-specific.
- If an installation has no reverse proxy, it should not trust forwarded headers at all. If TLS terminates
  at a proxy, the trusted-proxy configuration must match that termination point.
- Misconfiguration directly affects the login IP bucket: too narrow a trust list collapses clients behind
  the proxy into one bucket; too broad a list permits IP spoofing.
- This plan does **not** add or change `TrustProxies`; establishing it is an operational prerequisite for
  relying on the IP-based login limit. Exact per-installation values remain deployment documentation.

---

## 4. Verified findings (pre-implementation audit)

1. **No API rate limiting exists today**, except the login failure throttle described in §3.3. Every
   authenticated endpoint can be called without a frequency cap.
2. **Login is partially protected**: 5 failed attempts per email+IP per 5 minutes, with a 429 and a
   localized `errors.email` message. Successful logins and malformed requests do not consume attempts.
   There is no per-IP flood cap, so high-volume login traffic (including bcrypt work) is not bounded by
   the existing failure counter.
3. **The API is read-only**; POS and inventory writes are web routes. API throttling therefore cannot
   block a legitimate sale or stock update.
4. **Dashboard and reports are materially more expensive** than list/detail endpoints; reports are
   unbounded full-table reads. They warrant a stricter category limit.
5. **Step 12 already bounds response size** (`per_page`/`limit` in `[1, 100]`), but not request frequency.
   D-6 complements D-7.
6. **Auth runs before throttle** in Laravel's middleware priority list
   (`Foundation/Http/Kernel.php:103-115`: `AuthenticatesRequests` precedes `ThrottleRequests`). This means
   a user-keyed throttle applied after `auth:sanctum` can safely use `$request->user()`. It also means a
   PHP-level throttle does **not** protect against unauthenticated/invalid-token floods, because
   authentication runs first and rejects those requests before the throttle.
7. **IP-based limits require environment-specific proxy awareness.** With no `TrustProxies`
   configuration, IP-keyed login throttling can collapse many clients behind one reverse proxy into a
   single bucket. Because the three supported hosting scenarios have different proxy topologies, trusted
   proxies must be configured per deployment; there is no safe universal proxy configuration.
8. **No observed API consumers in dev** (`personal_access_tokens = 0`), so recommended limits are
   operationally inferred, not measured from real API traffic.

---

## 5. Identified security and operational risks (pre-implementation audit)

| Risk | Impact | Evidence / reasoning |
|---|---|---|
| Unlimited authenticated API reads | Resource exhaustion via dashboard/reports/list loops; DB and PHP saturation | No throttle on any authenticated route; dashboard/reports are heavy |
| Login brute force / credential stuffing | Account compromise, bcrypt CPU exhaustion | Existing failure limiter is per email+IP and failure-only; no per-IP flood cap |
| Runaway or misconfigured API client | Retry storms, accidental loops, DB pressure | No frequency cap; tokens expire in 12h but can be recreated |
| Reports full-table scans | A single request can load the entire sales/movements/client tables | `ReportService` uses unpaginated `->get()` |
| Dashboard polling | Repeated multi-aggregate queries | 18 month loops plus several aggregates per request |
| Shared NAT / reverse proxy | IP-keyed limits throttle unrelated users together | No `TrustProxies` configuration; proxy topology varies by hosting scenario |
| Shared employee account used despite policy | Audit attribution is unreliable; terminals share one rate-limit bucket | Individual accounts are required; shared accounts are outside the operating model |
| Incorrect or over-broad `TrustProxies` | Spoofed client IP or all clients collapsed into one login bucket | Trust only known proxy IPs/CIDRs and required forwarded headers per deployment; never trust all by default |
| Invalid-token floods | Each request reaches Sanctum's token lookup before throttle | Auth middleware precedes throttle in priority |
| Database cache hotspot | Rate-limiter/session writes add DB load under pressure | `CACHE_STORE=database`, `SESSION_DRIVER=database` |
| No API usage visibility | Cannot tune limits or detect abuse | No 429 metrics/logging; dev token count is 0 |
| Future API writes | A read-oriented limit could be wrong for transactional writes | No write endpoints today; future design needed |
| Future integration uses employee credentials | Audit attribution and rate-limit isolation are compromised | Confirmed requirement for independent integration identity/credentials; not implemented in Step 13 |
| Future integration traffic has no dedicated budget | Integration traffic could be unfairly limited or consume employee-oriented capacity | Integration-specific quotas deferred until actual integrations and traffic are known |

### 5.1 Confirmed operating assumptions

- Every employee has an individual user account; shared employee accounts are not supported by the
  intended operating model.
- NegocioOS uses a centralized server architecture, deployed under one of the three confirmed hosting
  scenarios in §1.2.
- `TrustProxies` is configured per deployment environment. Forwarded IP headers are trusted only from
  known proxies, and no universal proxy configuration is assumed.
- The API remains read-only in this step; POS sale and inventory writes remain web routes.
- Authenticated API limits are keyed by user; the unauthenticated login limit is keyed by IP.
- The four limits are implemented: general 120/min/user, dashboard 60/min/user, reports 10/min/user and
  login 30/min/IP.
- NegocioOS must be ready for future external integrations, but no external API consumers are currently
  identified or required for the initial release. Existing API architecture and Sanctum authentication
  remain unchanged in this step.
- Future external integrations must have independent identities and credentials; they must not use or
  share employee credentials. No integration identity or credential mechanism is implemented now.
- No integration-specific rate limits, quotas or request budgets are introduced in Step 13. Those are
  future design considerations to be evaluated when actual integration traffic and requirements are known.

---

## 6. Rate-limiting strategy and implementation

### 6.1 Approach

Use Laravel's named rate limiters (the project already uses `RateLimiter::for()` for login) plus
**route-level `throttle` middleware applied after `auth:sanctum`**:

- Authenticated routes are keyed by **user ID**. The confirmed individual-account policy makes this
  reliable: limits are per employee and do not penalize other users sharing an IP/NAT.
- Login is keyed by **IP**, because there is no authenticated user yet. The individual-account policy
  does not change this pre-authentication distinction.
- Shared employee accounts are not a supported assumption; per-user keys depend on each employee having
  an individual account.
- Expensive categories get **separate buckets** in addition to the general authenticated bucket, so a
  dashboard/report request consumes both its category budget and the general budget, but ordinary
  requests do not consume the dashboard/report budgets.
- Existing login failure behavior is left unchanged; the new login flood limiter is additive.
- The authenticated limits are **user-based**, and the login limit remains **IP-based**. No
  integration-specific limiter is introduced in Step 13. Future external integrations may need dedicated
  policies, quotas or request budgets, but those are deferred until actual integrations, expected traffic
  and operational requirements are known (see §15).

### 6.2 Named limiters

Register in `AppServiceProvider::boot()` using a new `App\Support\ApiRateLimit` helper (mirroring the
existing `LoginThrottle` convention):

| Limiter name | Applied to | Key | Limit |
|---|---|---|---|
| `api` | All routes inside the `auth:sanctum` group | `api:user:{id}` (IP fallback) | 120 / minute |
| `api-dashboard` | `GET /api/dashboard` | `api-dashboard:user:{id}` | 60 / minute |
| `api-reports` | `GET /api/reports` | `api-reports:user:{id}` | 10 / minute |
| `api-login` | `POST /api/auth/login` | `api-login:ip:{ip}` | 30 / minute |

All four values are **implemented and in effect in the working tree**. The individual-account policy
supports user-based authenticated limits; the login limiter remains IP-based and separate. `api-reports`
also returns `Limit::none()` for users who are not admin/encargado so that forbidden requests do not consume
the reports bucket; see §6.4 for the framework ordering reason.

Distinct key prefixes are required: Laravel's named-limiter middleware uses the key returned by `by()`
directly, so sharing a key between limiters would merge their buckets.

### 6.3 Route middleware changes

```php
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:api-login')
    ->name('api.auth.login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // ... existing routes ...

    Route::get('dashboard', DashboardController::class)
        ->middleware('throttle:api-dashboard')
        ->name('api.dashboard');

    Route::get('reports', [ReportController::class, 'index'])
        ->middleware(['role:admin,encargado', 'throttle:api-reports'])
        ->name('api.reports.index');

    // inventory/settings and all other authenticated routes inherit throttle:api
});
```

`bootstrap/app.php` does **not** need `throttleApi()`; keeping the throttle on the authenticated group
makes the auth-before-throttle ordering explicit and avoids applying a general limiter to the public
login route.

### 6.4 Middleware order

- `auth:sanctum` runs before `throttle:api` so `$request->user()` is available. Laravel's priority list
  places `AuthenticatesRequests` before `ThrottleRequests`; the route list also declares auth first for
  clarity. Verified with `php artisan route:list --path=api -v` and `gatherRouteMiddleware()`.
- **Verified deviation:** Laravel's priority sort places both `ThrottleRequests` instances before the custom
  `RoleMiddleware`, even though §6.3 declares `role` before `throttle:api-reports`. At runtime the reports
  order is `auth:sanctum` → `throttle:api` → `throttle:api-reports` → `SubstituteBindings` → `role`.
  To preserve the acceptance criterion, the `api-reports` limiter returns `Limit::none()` when the
  authenticated user is not admin/encargado. Unauthorized requests therefore receive the role middleware's
  403 without consuming the reports bucket; authorized requests are counted normally. The general
  `throttle:api` still runs before role and counts unauthorized authenticated requests.
- `throttle:api` and `throttle:api-dashboard`/`throttle:api-reports` are separate middleware and separate
  buckets.

### 6.5 Response contract

On limit exceeded, return HTTP **429** with JSON and the standard Laravel headers:

- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `Retry-After` (seconds)
- `X-RateLimit-Reset` (Unix timestamp)

Implemented body (consistent with the app's bilingual error surface):

```json
{ "message": "Too many requests. Please try again in 42 seconds." }
```

Implementation: `ApiRateLimit::limit()` uses
`Limit::response(fn (Request $request, array $headers) => response()->json([...], 429, $headers))` with the
new `api.throttle` translation key (`lang/en/api.php`, `lang/es/api.php`). Tests verify the localized body
and all four headers. The existing login failure throttle keeps its current `errors.email` body and 429
status; the new `api-login` middleware is a coarse flood response and is documented separately.

### 6.6 Alternatives considered

| Alternative | Assessment |
|---|---|
| `$middleware->throttleApi('api')` on the whole `api` group | Applies one limiter to login too and hides the auth-ordering intent; less explicit category control. Not recommended for this step. |
| One `api-expensive` limiter for dashboard and reports | Simpler, but gives reports (unbounded full-table scans) the same budget as the dashboard. Separate limits are more operationally accurate. |
| Role-based limits (admin > encargado > vendedor) | Adds arbitrary differentiation; expensive endpoints are already role-gated. Uniform per-user limits are simpler and fairer. Deferred. |
| Strict 422 validation of pagination parameters | Already decided in Step 12: clamp, do not reject. Not revisited here. |
| Pre-auth IP limiter / custom middleware | Could cap invalid-token floods, but PHP-level throttling still boots the framework; edge/WAF is the correct layer for volumetric attacks. Deferred; see §12. |
| Integration-specific limits or quotas in this step | No external consumers or traffic profiles are currently known. Adding them now would broaden scope and create unused policy. Deferred to future integration architecture; see §15. |
| Redis-backed limiter | Better under load, but adds infrastructure/dependency. Current database cache is adequate for the observed scale. Out of scope. |

---

## 7. Implemented limits and justification

All values below are **implemented and in effect in the working tree**.

| Limiter | Limit | Decay | Justification |
|---|---|---|---|
| `api` (general authenticated) | **120 requests/minute/user** | 60s | 2 requests/second sustained. A human UI typically makes well under 30 API calls/minute; 120 gives 4x headroom for navigation, retries and bursts. At the Step 12 maximum of 100 items/page, 120 requests/min still allows 12,000 records/minute for a legitimate sync. It caps runaway loops without affecting normal use. |
| `api-dashboard` | **60 requests/minute/user** | 60s | One dashboard request runs 18 month-loop queries plus several aggregates. 1 request/second is ample for human navigation or auto-refresh (a 2-second refresh interval is 30/min); it caps abusive polling while leaving headroom. |
| `api-reports` | **10 requests/minute/user** | 60s | Reports perform unbounded `get()` scans over sales, movements, clients or sale items. One report every 6 seconds is far above normal manual use and prevents report-driven DB exhaustion. Reports are already restricted to admin/encargado. |
| `api-login` | **30 requests/minute/IP** | 60s | Login performs bcrypt hashing and token creation. 30/minute/IP caps sustained credential-stuffing/CPU abuse while allowing a small office behind one NAT to log in several terminals during shift start. The existing 5-failed-attempts/5-minutes per email+IP limiter remains the primary credential-stuffing defense; this is an additive flood cap. |

Notes:

- The general `api` bucket applies to dashboard and reports too; the stricter category limit wins.
- Authenticated limits are user-based; the login limit is IP-based. This distinction is preserved by the
  confirmed individual-account policy and does not change the pre-authentication login behavior.
- No per-role maximum is implemented. All authenticated roles share the same general budget; category
  restrictions already separate reports/inventory/settings.
- Limits are centralized as public constants in `ApiRateLimit` so they can be tuned without schema or
  config changes. No per-environment config file is implemented in this step.
- The four values are adopted for this implementation; tuning remains possible through the centralized
  constants.
- No integration-specific limit is implemented in Step 13. The existing user-based authenticated limits
  and IP-based login limit are preserved unchanged; future integration quotas are deferred (see §15).

---

## 8. Role and endpoint considerations

| Role | Accessible API surfaces | General limit | Category limits | Notes |
|---|---|---|---|---|
| **Admin** | All 13 routes | 120/min | dashboard 60/min; reports 10/min | No special elevation; admin reports remain comfortably above manual use. |
| **Encargado** | All except `settings` | 120/min | dashboard 60/min; reports 10/min; inventory general | Same as admin for inventory/reports. |
| **Vendedor** | `me`, `logout`, `dashboard`, clients, products, sales | 120/min | dashboard 60/min | Inventory/reports/settings remain 403; seller scoping (`visibleTo`) is unchanged. |
| **Unauthenticated** | `login` only | — | login 30/min/IP | All other API routes return 401 via `auth:sanctum` before the throttle. |

- **Authenticated limits assume individual employee accounts.** Each employee must use their own account;
  shared employee accounts are outside the intended operating model and would undermine both audit
  attribution and per-user limit fairness.
- **External integrations are not employee accounts.** Future integrations must have independent
  identities and credentials and must not use employee credentials. No integration identity, credential
  mechanism or integration-specific limit is implemented in this step (see §15).
- **Role-based maxima considered and rejected for now.** The expensive endpoints are already role-gated,
  and a uniform per-user budget avoids arbitrary differences. If monitoring shows admins/encargados
  routinely need more dashboard/report capacity, the constants can be raised or a role-aware limiter can
  be introduced later.
- **Settings/inventory** are covered by the general limiter; they are paginated/bounded and role-gated.
- **`me`/`logout`** are cheap and covered by the general limiter.
- **Token abilities** are not enforced today; this plan does not change authorization. Rate limiting is
  per user, so a user cannot bypass it by using a different valid token (unless token-based keying is
  adopted later).

---

## 9. POS compatibility considerations

### 9.1 POS writes are not API routes

The critical compatibility fact: **sale creation and inventory updates are web routes**:

- `POST /sales` — `SaleController@store`, session auth, `role:admin,vendedor,encargado`
  (`routes/web.php:53-54`).
- `POST /inventory` — `InventoryController@store`, session auth, `role:admin,encargado`
  (`routes/web.php:70`).
- Client creation, product writes, paid toggles and exports are also web routes.

Applying throttles only to `routes/api.php` therefore **cannot block a legitimate POS sale or inventory
update**. Web login already has the failure-based throttle; this step does not change web behavior.

### 9.2 API reads during a sale

If a future POS/mobile client uses the API for lookups during checkout, a typical sale might make:

- 1 product list/search request,
- 1 client search/creation lookup,
- 1 sale detail request,
- optionally 1 dashboard request.

At 10 sales/minute and 5 API calls/sale, that is 50 requests/minute — well under the 120/minute general
budget. Even 20 sales/minute with 5 calls each is 100/minute. The dashboard has its own 60/minute budget.

### 9.3 Concurrent terminals and individual accounts

- Every employee must use an individual account. Shared employee accounts are **not** part of the
  intended operating model.
- Per-user keying means terminals logged in as **different employees** each get their own budget. If one
  employee uses multiple terminals, those terminals share that employee's 120/minute bucket, which is
  acceptable for normal POS use.
- Shared accounts are not a supported capacity assumption. If a shared account is observed in operation,
  the correct response is to correct account provisioning, not to design limits around shared identities.
- Per-token keying was considered but not chosen: it is more terminal-friendly if shared accounts were
  allowed, but it lets a user multiply budgets by minting tokens, and it does not align as directly with
  employee-level audit attribution. Per-user is the safer default and matches the confirmed policy.

### 9.4 Shift-start logins

- `api-login` allows 30 requests/minute/IP. A small office with several terminals behind one NAT can log
  in comfortably; more than 30 login requests/minute from a single IP will throttle.
- If the deployment is behind a reverse proxy/load balancer, configure `TrustProxies` **for that
  environment** so `$request->ip()` reflects the client. Because the three hosting scenarios differ, there
  is no universal proxy configuration: trust only the known proxy IPs/CIDRs and the forwarded headers that
  proxy actually sets. Do not blindly trust forwarded IP headers. If the installation is directly
  exposed, do not trust them at all. See §3.7.
- Otherwise all clients can share the proxy IP and throttle together, or a client can spoof its address if
  forwarded headers are trusted too broadly. Establishing the correct per-deployment configuration is an
  operational prerequisite, not a code change in this plan.

### 9.5 Client behavior on 429

API clients should honor `Retry-After` and back off. Because POS writes are web routes, an API 429 during
read-side polling cannot lose a sale. No retry loop should be added to web POS forms as part of this step.

---

## 10. Files changed

| File | Change |
|---|---|
| `app/Support/ApiRateLimit.php` | **New.** Public constants (`GENERAL_PER_MINUTE = 120`, `DASHBOARD_PER_MINUTE = 60`, `REPORTS_PER_MINUTE = 10`, `LOGIN_PER_MINUTE = 30`), a scoped key helper (`scope:user:{id}` / `scope:ip:{ip}`), and a `limit()` factory that applies the localized 429 response. Mirrors `LoginThrottle`. |
| `app/Providers/AppServiceProvider.php` | Register `api`, `api-dashboard`, `api-reports` and `api-login` named limiters in `boot()`; keep the existing `login` limiter untouched. The `api-reports` limiter returns `Limit::none()` for non-admin/encargado users (see §6.4). |
| `routes/api.php` | Add `throttle:api-login` to login; wrap the authenticated group with `throttle:api`; add `throttle:api-dashboard` to dashboard; add `throttle:api-reports` after the existing role middleware declaration on reports. |
| `lang/en/api.php` | **New.** `throttle` message with `:seconds` placeholder. |
| `lang/es/api.php` | **New.** Spanish `throttle` message with `:seconds` placeholder. |
| `tests/Feature/ApiRateLimitTest.php` | **New.** Seven regression tests for per-user, per-IP, category buckets, forbidden-report quota isolation, headers, localized 429 body and named-limiter registration. |

No changes to:

- controllers, models, services or business logic;
- `bootstrap/app.php` (no `throttleApi()` call needed);
- `config/cache.php`, `config/sanctum.php`, `config/auth.php`;
- migrations or database schema;
- web routes or middleware;
- external integration identities, credentials, management, versioning or integration-specific rate
  limits;
- dependencies.

Both translation files are included, so 429 responses use the localized `api.throttle` message rather
than Laravel's default body.

---

## 11. Regression tests and results

Implemented file: `tests/Feature/ApiRateLimitTest.php` (`RefreshDatabase`; `CACHE_STORE=array` from
`phpunit.xml`; `Cache::flush()` in `setUp()` for isolation; `Sanctum::actingAs()` for authenticated
requests; `withServerVariables(['REMOTE_ADDR' => ...])` for IP tests). It contains **7 tests / 327
assertions** and passes.

### 11.1 Test matrix

| Test | Setup / action | Expected |
|---|---|---|
| General limit is per user | User A makes `ApiRateLimit::GENERAL_PER_MINUTE` requests to `/api/me`; then one more; user B from the same IP calls `/api/me` | A's first 120 are 200; A's 121st is **429** with `Retry-After`; B is 200 |
| Dashboard has its own bucket | User makes `DASHBOARD_PER_MINUTE` requests to `/api/dashboard`; then one more; then `/api/me` | Dashboard 60 are 200; 61st is 429; `/api/me` is 200 (separate bucket) |
| Reports have their own stricter bucket | Admin/encargado makes `REPORTS_PER_MINUTE` requests to `/api/reports`; then one more; then `/api/dashboard` | Reports 10 are 200; 11th is 429; dashboard is 200 |
| Forbidden reports do not consume quota | Seller makes `REPORTS_PER_MINUTE + 1` requests; user is promoted to admin; then `REPORTS_PER_MINUTE` more requests | All seller attempts are 403; after promotion the 10 requests are 200; the 11th is 429 |
| Login is per IP | 30 malformed login requests from IP A (`email` only, no password); 31st; then IP B | IP A's first 30 are 422; 31st is **429**; IP B is not 429 |
| Login failure guard unchanged | 5 wrong-password attempts, then a 6th | 6th is 429 with `errors.email` (existing behavior; no preemption by `api-login` at 30/min) |
| 429 response contract | Trigger any limiter | 429 JSON; `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining` headers; localized `message` |
| Success headers | One normal authenticated request | 200 with `X-RateLimit-Limit` / `X-RateLimit-Remaining` |
| Middleware wiring | Inspect API route middleware (or assert behavior) | `throttle:api` on authenticated routes; `throttle:api-login` on login; `throttle:api-dashboard` on dashboard; `throttle:api-reports` on reports |

Notes:

- The general test issues ~120 HTTP requests; the dashboard test ~60; the login test ~30. This is
  acceptable for a feature test but should be measured. If runtime is a concern, the bucket can be
  pre-filled with `RateLimiter::hit()` using `ApiRateLimit::key()` and then a single request made; the
  behavior tests above are preferred for end-to-end coverage.
- No random factory assertions; use deterministic users and roles.
- The existing `ApiAuthThrottleTest` (7 tests) and `LoginThrottleTest` (7 tests) must remain green
  unchanged; they cover the failure-based login limiter.
- `ApiAuthorizationTest` remains green (403s for sellers on inventory/reports). Because Laravel sorts the
  reports throttle before the custom role middleware, the `api-reports` limiter is role-aware and returns
  `Limit::none()` for sellers; the new test proves forbidden attempts do not consume the reports bucket.
- `ApiTokenExpirationTest`, `ApiEndpointsTest`, `ApiPaginationBoundsTest` and `ClientSellerScopingTest`
  remain green; the new middleware does not change successful payloads.
- Full suite result: **340 passed / 1,909 assertions**. MySQL group: **8 passed / 70 assertions**.

### 11.2 Existing tests that stayed green

- `tests/Feature/ApiAuthThrottleTest.php` — all 7 tests.
- `tests/Feature/LoginThrottleTest.php` — all 7 tests.
- `tests/Feature/ApiEndpointsTest.php` — endpoint payload/status assertions.
- `tests/Feature/ApiAuthorizationTest.php` — role restrictions.
- `tests/Feature/ApiTokenExpirationTest.php` — token validity/logout/role behavior.
- `tests/Feature/ApiPaginationBoundsTest.php` — Step 12 pagination bounds (42 tests).
- `tests/Feature/ClientSellerScopingTest.php` — client caps and seller scoping.
- `tests/Feature/MysqlConcurrencyTest.php` — MySQL concurrency group (8 tests).

No existing assertion is to be weakened or removed.

---

## 12. Validation commands and evidence

Executed from the repository root (Windows PowerShell) on 2026-10-31:

```powershell
php -l app\Support\ApiRateLimit.php
php -l app\Providers\AppServiceProvider.php
php -l routes\api.php
php -l lang\en\api.php
php -l lang\es\api.php
php -l tests\Feature\ApiRateLimitTest.php

vendor\bin\pint --test

php artisan test
php artisan test --configuration=phpunit.mysql.xml

php artisan route:list --path=api -v
php artisan migrate:status
```

Results:

- `php -l`: **clean** for all changed PHP files.
- `vendor\bin\pint --test`: **passed**.
- Focused `ApiRateLimitTest`: **7 passed / 327 assertions**.
- Full SQLite suite: **340 passed / 1,909 assertions** (108s). One earlier full run hit an unrelated
  flaky `ProductTest` assertion caused by the factory's random expired date; the targeted test passed on
  rerun and the next full run was green.
- MySQL group: **8 passed / 70 assertions**.
- `composer test` was started but the shell's 120s command cap interrupted it; the equivalent
  `php artisan test` completed successfully when given a larger timeout.
- `route:list --path=api -v`: **13 routes**; login has `api-login`; authenticated routes have `api`;
  dashboard adds `api-dashboard`; reports adds `api-reports`.
- `migrate:status`: **16 migrations**, all `Ran`, none pending.
- Read-only dev-DB metrics probe: **identical** to §2 (sales 180; sale_items 450; products 37;
  products_trashed 0; clients 22; `SUM(subtotal)` 131404.99; `SUM(total)` 147173.59;
  `SUM(tax_amount)` 15768.60; `SUM(inventory_movements.quantity)` 3732; inventory_movements 487;
  `personal_access_tokens` 0; `audit_logs` 0).

No migration or write path is involved; tests use in-memory SQLite and `as_negocios_test`, and the probe is
SELECT-only. Rate limiting itself uses the `cache` table in the dev environment, but the validation workflow
did not issue API traffic against the dev database.

---

## 13. Risks and acceptance criteria

### 13.1 Risks and mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Limits too low for legitimate API clients/POS reads | Medium | Generous 120/min general budget; per-user keys; separate dashboard/report buckets; constants centralized for quick tuning; monitor 429s after rollout. |
| Limits too high to stop abuse | Medium | Reports 10/min and login 30/min/IP are strict; edge/WAF remains necessary for volumetric attacks. |
| Shared NAT/proxy causes collateral throttling | Medium | Configure `TrustProxies` per deployment after documenting the proxy topology; trust only known proxy IPs/CIDRs; 30/min login headroom; per-user keys for authenticated routes. |
| Incorrect or over-broad `TrustProxies` | High if wildcard/all trusted | Establish trusted proxies per environment; trust only required forwarded headers from known proxies; never trust all by default. |
| Shared employee account used despite policy | Low/Medium | Individual accounts are required for audit attribution and per-user limits; correct provisioning rather than designing around shared identities. |
| Invalid-token floods bypass per-user throttle | Medium | Auth runs before throttle; PHP cannot cheaply stop this. Use edge/WAF or a pre-auth limiter (deferred); document the limitation. |
| Database cache hotspot under load | Low/Medium | Current scale is small; consider Redis/cache-store change later (out of scope). |
| 429 body differs between login failure throttle and middleware | Low | Document both; optionally align the login limiter response. Tests cover each path. |
| Middleware ordering accidentally keys by IP for authenticated users | High if wrong | Auth-first ordering is guaranteed by Laravel priority and declared explicitly; behavior test proves user A blocked/user B allowed from the same IP. |
| Reports throttle runs before the custom role middleware | Medium | Laravel's priority sort places `ThrottleRequests` before `RoleMiddleware`. The `api-reports` limiter returns `Limit::none()` for non-admin/encargado users, so forbidden requests still return 403 without consuming the reports bucket; test coverage added. |
| Limiter key collisions merge buckets | High if wrong | Distinct `api`, `api-dashboard`, `api-reports`, `api-login` prefixes; tests prove bucket independence. |
| New middleware breaks tests making many requests | Low | Full suite run; existing tests make well under 120 requests per user. |
| Cache table missing in a deployment | Low | Migration `0001_01_01_000001_create_cache_table.php` exists; verify `migrate:status` before deploy. |
| Future API writes inherit read limits | Medium | No write endpoints today; design a separate write limiter/idempotency when writes are added. Explicit exclusion. |
| Future integration uses employee credentials | Audit attribution and rate-limit isolation are compromised | Confirmed design requires independent integration identity and credentials; not implemented in Step 13; enforce in the future integration design. |
| User-based limits do not fit future integration traffic | Integration traffic could be unfairly throttled or leave no dedicated budget | Defer integration-specific quotas until actual integrations and traffic are known; do not add unused policy now. |
| Future API changes break external consumers | Compatibility and lifecycle risk | Future API changes should consider compatibility; versioning and integration lifecycle management may be evaluated in a future dedicated architectural step. |

### 13.2 Acceptance criteria

All criteria below were validated for the working-tree implementation.

1. `POST /api/auth/login` is covered by `throttle:api-login`, keyed by IP, 30/minute; the existing
   5-failed/5-minute email+IP limiter and its `errors.email` 429 body are unchanged.
2. Every route inside the `auth:sanctum` group is covered by `throttle:api`, keyed by user, 120/minute.
3. `GET /api/dashboard` has an additional 60/minute per-user bucket.
4. `GET /api/reports` has an additional 10/minute per-user bucket. The route declares role before the
   reports throttle, but Laravel's priority sort executes the throttle first; the limiter is role-aware so
   unauthorized attempts return 403 without consuming the bucket.
5. General, dashboard, report and login buckets are independent (a dashboard/report block does not block
   ordinary requests, and vice versa).
6. Limit responses are HTTP 429 with `Retry-After` and `X-RateLimit-*` headers and a localized JSON body.
7. Existing tests remain green; new tests cover per-user, per-IP, category buckets, headers, 429 and
   forbidden-report quota isolation.
8. No migration, schema, config, dependency or web-route change; dev-DB metrics identical before/after.
9. POS sale and inventory writes are demonstrably unaffected (they remain web routes).
10. Scope remains limited to D-6.

---

## 14. Explicit exclusions

Not part of this step:

- **Web route throttling** (POS sales, inventory writes, web login, exports, password reset). Web login
  already has the failure throttle; no web changes were made.
- **Pre-auth IP/volumetric protection** (custom pre-auth middleware, WAF/CDN rules, reverse-proxy rate
  limiting). Acknowledged as the correct layer for invalid-token floods; deferred.
- **Role-based limit differentiation** and **per-token keying** (deferred until operational need is
  observed).
- **Redis or alternate cache store** for rate limiting; **config-based per-environment limits**.
- **API write limiters** (no write API endpoints exist today).
- **External integration identities, credentials, management, and integration-specific rate limits,
  quotas or request budgets.** No external API consumers are currently identified; this is future work.
- **Additional authentication mechanisms for external integrations.** The existing Sanctum approach is
  preserved in this step.
- **API versioning and integration lifecycle management.** These may be evaluated in a future dedicated
  architectural step.
- **Token ability enforcement**, Sanctum expiration changes, token-count limits.
- **TrustProxies configuration** (environment-specific operational prerequisite; no universal proxy
  configuration is prescribed, and it is not changed in this step).
- **Supporting shared employee accounts** or designing rate limits around shared identities.
- **Report pagination/chunking (E-1)**, dashboard query optimization, sargable date filters, indexes.
- **D-7 pagination work** (already implemented in Step 12) and any other Phase 1 finding.
- **Monitoring/alerting dashboards** for 429s or API usage.
- Editing existing tests to weaken assertions; reopening Steps 1–12.
- Committing or pushing anything; the implementation remains uncommitted for review.

---

## 15. Future API and integration considerations

**Status: future design considerations — not part of the Step 13 implementation.**

### 15.1 Integration readiness (confirmed requirement)

- The API should remain suitable for future consumption by external applications and systems. Do not
  assume that only NegocioOS's own frontend or internal components will ever consume it.
- The existing API architecture and Sanctum authentication approach are preserved for this step. No
  additional authentication mechanism is introduced now.

### 15.2 Independent integration identity (future requirement)

- Future integrations must be identifiable and manageable independently of employee accounts.
- External integrations must **not** use or share employee credentials. Integration credentials and
  employee credentials must remain conceptually and operationally separate.
- The future design should make it possible to identify which integration originated a request.
- No integration identity, credential store or management interface is implemented in Step 13.

### 15.3 Future rate-limit strategy (deferred)

- The current authenticated limits remain **user-based**, and the login limit remains **IP-based**.
- No integration-specific rate limit, quota or request budget is introduced in Step 13.
- Future external integrations may require dedicated rate-limit policies, quotas or request budgets.
  Those should be evaluated when actual integrations, expected traffic and operational requirements are
  known. This future consideration must not expand the current implementation scope.

### 15.4 API compatibility and lifecycle (future)

- Future API changes should consider compatibility with existing external integrations.
- API versioning and integration lifecycle management may be evaluated in a future dedicated
  architectural step. They are not implemented now.

### 15.5 Current scope boundary

- Step 13 remains limited to the rate-limiting work described in this plan. None of the future
  integration capabilities above are implemented, tested or configured in this step.

---

## Observed facts vs inferred risks vs confirmed decisions vs unresolved questions

- **Observed facts:** all file/line references; the pre-implementation `route:list --path=api -v` showing
  no throttle; the `bootstrap/app.php` middleware configuration; the existing `login` limiter and manual
  `ThrottlesLogins` enforcement; the absence of `TrustProxies`; `CACHE_STORE=database` and test
  `CACHE_STORE=array`; the read-only API route set; the absence of `/api/` calls in `resources/js`; the dev
  DB token count of 0; the recorded test/dev-DB baselines; Laravel's middleware priority placing
  `AuthenticatesRequests` before `ThrottleRequests` and both `ThrottleRequests` before the custom role
  middleware; and the cost profiles of the dashboard and `ReportService` were read from the current
  repository and framework source. Post-implementation facts: 13 API routes show the intended throttle
  middleware, 7 new rate-limit tests pass, the full SQLite suite is green, and the dev-DB metrics are
  unchanged.
- **Inferred risks:** the practical impact of unbounded request frequency, the expected request volumes
  of a POS/UI, the shared-NAT and per-installation proxy concerns, the database-cache hotspot at scale,
  the shared-account policy violation risk, the future integration credential-separation risk, and the
  invalid-token flood vector follow from the observed code paths and deployment assumptions. They are not
  measured from production traffic in this repository.
- **Confirmed business/deployment decisions:** every employee has an individual account; shared employee
  accounts are not part of the operating model; audit attribution is required; NegocioOS uses a
  centralized server architecture; the three hosting scenarios in §1.2 must be supported; `TrustProxies`
  is environment-specific and forwarded IP headers must not be blindly trusted; authenticated limits are
  user-based while login remains IP-based; the four limit values are implemented as specified in §6.2.
- **Confirmed future-integration requirements:** NegocioOS must be designed to support future external
  integrations, but no external API consumers are currently identified or required for the initial
  release; the existing API architecture and Sanctum authentication are preserved in this step; future
  integrations must have independent identities and credentials separate from employee credentials; no
  integration-specific limits are introduced in Step 13; future API compatibility must be considered, with
  versioning and lifecycle management deferred to a future architectural step.
- **Future recommendations, not implemented:** integration identity and credential management, dedicated
  integration quotas, API versioning and integration lifecycle management are design considerations only.
  They are not implemented, tested or configured in this step.
- **Unresolved questions:** (a) the per-installation reverse-proxy topology and the exact trusted proxy
  IPs/CIDRs and forwarded headers (the centralized architecture and three hosting scenarios are confirmed,
  but each deployment's values must be established); (b) the expected number of terminals/users per
  installation for capacity planning (individual accounts are confirmed; shared accounts are excluded);
  (c) the specific future external integrations, their expected traffic profiles, credential model and
  whether they require dedicated quotas (none are currently identified; to be evaluated when known);
  (d) whether a pre-auth IP limiter/WAF rule should be added in a follow-up; (e) whether
  admins/encargados need higher dashboard/report limits. The localized 429 question is resolved:
  `api.throttle` messages are implemented in English and Spanish. None of these block the implemented
  step; user review of the working-tree changes is pending.

---

IMPLEMENTATION STATUS: IMPLEMENTED AND VALIDATED IN THE WORKING TREE — NOT COMMITTED
LIMITS IN EFFECT: API 120/min/user; DASHBOARD 60/min/user; REPORTS 10/min/user; LOGIN 30/min/IP
REVIEW STATUS: AWAITING USER REVIEW
