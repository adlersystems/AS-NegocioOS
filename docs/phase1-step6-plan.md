# Phase 1 — Step 6: Seller Attribution & Sales Data Scoping (F-INTEG-01 / F-INTEG-02) — Implementation Plan

## 1. Status

**ACTIVE** — Rules A and B were approved during plan review, and the step was implemented on 2026-09-19. Implementation record in §16.

## 2. Current Repository State

- Branch: `main`, working tree clean, HEAD `2e012d7` (origin/main in sync).
- Phase 5 (brute-force protection, F-AUTH-01) is implemented, ACTIVE and recorded in `docs/phase1-step5-plan.md` §16 (commit `73840c7`).
- Full feature suite: **232/232 tests passed (1,126 assertions)** re-verified for this audit with `composer test`.
- MySQL concurrency group: 3/3 (21 assertions).
- Pint clean; `php -l` clean on Step 5 files; no open migrations.
- Step 6 is a read-only audit. Baseline was captured before any Step 6 work; no code or config was modified.

## 3. Completed Phase 1 Steps

| Step | Scope | Status |
|---|---|---|
| 1 | Foundation & permission model — admin/vendedor/encargado roles via `role:` middleware and `User::can*` helpers; transaction/lock hardening in sales & inventory; eager-load cleanup | Implemented |
| 2 | Historical sale data integrity — persisted `unit_price`/`tax_rate`/`cost` snapshots; reconcile freezes recorded money | Implemented (`12f8bd9`) |
| 3 | Historical client identity & safe client deletion — `client_name`/`client_nit` snapshots, role-gated guarded delete, NIT unique index | Implemented (`f45cad1`) |
| 4 | API authorization matrix & commercial data secrecy — role gate on every API endpoint; cost/margin/inventory value hidden from non-admin/encargado on web + API | Implemented (`485ef99`) |
| 5 | Authentication hardening — brute-force protection (F-AUTH-01): named `login` rate limiter + `ThrottlesLogins` trait | Implemented (`73840c7`) |

## 4. Audit Scope

Read-only audit of every currently deferred security/data-integrity finding. Covered:

- Web routes/controllers: sales, clients, products, inventory, reports, dashboard, users, settings, audit.
- API routes/controllers: sales, clients, products, inventory, reports, dashboard, settings, auth.
- Authorization model: `RoleMiddleware`, `Can*` methods on `User`, FormRequest `authorize()`/rules.
- Sanctum: token issuance, expiration, abilities, `personal_access_tokens` migration.
- Exports: PDF/Excel for sales, clients, inventory, reports (payloads and role gating).
- Pagination/limits: `per_page`/`limit` across API list/show endpoints; unbounded `->get()` exports.
- Security configuration: headers, session cookie flags, Sanctum config, `bootstrap/app.php`, `.env.example`.
- Password policies: create/update/reset/login rules.

Method: direct first-hand file reads plus a full test-baseline re-run. No code/config modified.

## 5. Findings

Each finding lists Current State / Evidence / Severity / Exploitability / Business-Security Impact / Current Mitigations / Implementation Complexity / Recommendation.

### 5.1 F-INTEG-01 — Sales seller attribution (`seller_id`) is fully client-controlled

- **Current State**: `SaleController::store` persists `seller_id` straight from the request (`SaleController.php:72`); `update` does the same for admins (`SaleController.php:137`). The sale form renders a dropdown of **all** users (`sales/form.blade.php:48-54`) that any operator can change; it merely defaults to `auth()->id()`. Validation only checks the value exists (`StoreSaleRequest.php:23`); nothing binds it to the authenticated principal. `sales.create`/`sales.store` are open to the most numerous role, `vendedor` (`routes/web.php:51-54`).
- **Evidence**: `app/Http/Controllers/SaleController.php:69-75,135-140`; `app/Http/Requests/StoreSaleRequest.php:21-29`; `resources/views/sales/form.blade.php:48-54`; `routes/web.php:49-62`.
- **Severity**: **High** (data integrity).
- **Exploitability**: **Very High** — any `vendedor` submits an arbitrary `seller_id` via plain form/tool tampering; no privilege escalation needed.
- **Business-Security Impact**: Attribution/commission/performance data derived from `User::sales()` (`User.php:47-50`) can be falsified — a seller can claim other sellers' sales or frame a colleague, with no way to distinguish fictitious from real attribution afterwards.
- **Current Mitigations**: None (existence-only validation; no principal binding).
- **Implementation Complexity**: Low — server-side derivation on one controller path + one blade tweak.
- **Recommendation**: Bind `seller_id` to the authenticated principal for `vendedor`; allow override only for roles confirmed in §7. Do **not** rewrite historical records.

### 5.2 F-INTEG-02 — No per-seller scoping: a `vendedor` sees and exports every seller's sales

- **Current State**: Sales index, show, invoice PDF and list/Excel exports are open to `vendedor` (`routes/web.php:40-45,49-56`) and query **globally** — no seller scoping in `index`, `exportListPdf`, `exportExcel`, `exportPdf`, or the API sales endpoints. A `vendedor` token can read all clients' NITs and every seller's revenue. API/Web dashboard return recent sales and top client for every role.
- **Evidence**: `SaleController.php:35-42,224-259`; `app/Exports/SalesExport.php:26-33`; `Api/SaleController.php:19-28,49-51`; `routes/web.php:40-45,49-56`; `routes/api.php:15-29`; `Api/DashboardController.php:57-60`; `DashboardController.php:56-67`.
- **Severity**: **High** (confidentiality of financial + client PII across sellers).
- **Exploitability**: **High** — plain UI browsing, no special action.
- **Business-Security Impact**: Full sales history and client NIT/name data are visible to every seller. Whether global visibility is an intentional small-shop design is a business question; the security posture defaults to least-privilege.
- **Current Mitigations**: Role gate (`role:admin,vendedor,encargado`) applies to the whole sales area, not per-seller data.
- **Implementation Complexity**: Low–Medium — add a scope clause on sales query paths for `vendedor`.
- **Recommendation**: Scope `vendedor`-visible sales (index/show/invoice/list exports + API index/show) to its own `seller_id`, pending confirmation of the business visibility rule in §7.

### 5.3 F-SANCTUM-01 — API tokens never expire and carry all abilities

- **Current State**: `'expiration' => null` (`config/sanctum.php:55`); tokens issued via `$user->createToken('api')` with no abilities → `['*']` (`Api/AuthController.php:36`). The `personal_access_tokens` table already has an indexed `expires_at` column (migration `2026_09_05_160033`, `:21`) but it is never used, and no cleanup job prunes stale rows.
- **Evidence**: `config/sanctum.php:55`; `Api/AuthController.php:36`; migration `:14-23`.
- **Severity**: **High** (confidentiality) — a leaked token grants indefinite read access to its role's full dataset.
- **Exploitability**: Medium — requires a token leak; impact is long-lived when one occurs.
- **Business-Security Impact**: POS mobile clients may legitimately require long-lived tokens (log in once per shift); expiration without a refresh flow could break that workflow.
- **Current Mitigations**: Step 5 rate limiting protects the credential prompt; logout deletes the current token (`Api/AuthController.php:44-55`); no back-channel revocation.
- **Implementation Complexity**: Low (expiration + scheduled model prune), but **requires deployment/business clarification** on token lifetime and client refresh behavior.
- **Recommendation**: Defer to the next step (§14). Infrastructure is already in place.

### 5.4 F-EXPORT-01 — Sales exports carry full cross-seller/client PII; other exports are gated

- **Current State**: Sales list PDF/Excel include client name+NIT for **all** sellers and are open to `vendedor`. Client/Inventory/Report exports are correctly gated to `admin,encargado` (`routes/web.php:106-109,64-82`); costs/margins are gated via `canViewCosts()` (`Api/SaleController.php:82-84`, `Api/DashboardController.php:40-47,71-75`).
- **Evidence**: `app/Exports/SalesExport.php:38-52`; `routes/web.php:40-45,64-82,106-109`; `app/Services/ReportService.php:44-68`.
- **Severity**: Medium (PII/financial disclosure), largely subsumed by F-INTEG-02.
- **Exploitability**: High (one file download).
- **Business-Security Impact**: Any seller can take the full customer + revenue list off the business.
- **Current Mitigations**: None on sales exports.
- **Implementation Complexity**: Low.
- **Recommendation**: Fix together with F-INTEG-02 (scope sales exports to the seller for `vendedor`).

### 5.5 F-PII-01 — Client PII read access open to every authenticated role

- **Current State**: Client `index`/`show` (web `routes/web.php:116-117`) and create/store (`routes/web.php:103-104`) have **no role middleware**; `StoreClientRequest::authorize()` returns `true`. API client index/show return name/email/phone/nit/address for any token (`Api/ClientController.php:27-38,56-65`). Dashboard leaks client names to all roles. Edit/delete/export are admin/encargado only.
- **Evidence**: `routes/web.php:94-118`; `User.php:101-111` (comment: creating clients is intentionally open for the front desk); `Api/ClientController.php:27-38,56-65`; `resources/views/clients/show.blade.php:29-49`; `DashboardController.php:56-67`.
- **Severity**: Medium–High (private business data + personal data).
- **Exploitability**: High (dashboard/search).
- **Business-Security Impact**: Storefront staff need name/NIT lookup to run sales (legitimate); full contact PII across all clients plus balances is broader than the job requires. This is a business-policy question, not purely technical.
- **Current Mitigations**: Client data exports gated; deletion blocked when sales exist.
- **Implementation Complexity**: Medium (policy design + field-level view changes).
- **Recommendation**: **Requires deployment/business clarification** on the role→field matrix. Defer detailed design; F-INTEG-02 already removes the widest leak channel.

### 5.6 F-PAG-01 — Unbounded `per_page`/`limit` and non-chunked exports

- **Current State**: API lists use `paginate($request->integer('per_page', 10))` with no cap (`Api/SaleController.php:28`, `Api/ClientController.php:24`, `Api/ProductController.php:25`, `Api/InventoryController.php:29`); show endpoints accept unbounded `limit` (`Api/ClientController.php:51`, `Api/ProductController.php:56`). Reports/exports use non-chunked `->get()` (`SalesExport.php:32`, `ReportService.php:54`).
- **Evidence**: as above; `Api/DashboardController.php:54,60` use fixed small limits (OK).
- **Severity**: Low–Medium (authenticated availability/performance).
- **Exploitability**: Medium (authenticated operator).
- **Business-Security Impact**: Low at typical POS scale; `per_page=999999` invites slow responses/memory spikes.
- **Current Mitigations**: Pagination exists; heaviest query (reports) is role-limited.
- **Implementation Complexity**: Low.
- **Recommendation**: Defer as maintenance hardening (§14): cap `per_page`/`limit`, chunk exports.

### 5.7 F-HDR-01 — No comprehensive security headers; weak CSP

- **Current State**: The only security header is a CSP in `public/.htaccess:2-4` (Apache-only; `script-src 'self' 'unsafe-inline' 'unsafe-eval'`). No `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, or HSTS anywhere in app code.
- **Evidence**: `public/.htaccess:2-4`; F-HDR-01 inventory in `docs/phase1-step4-plan.md`; header assertions exist only in tests.
- **Severity**: Low–Medium.
- **Exploitability**: Low–Medium (requires another XSS vector; `unsafe-*` weakens the CSP).
- **Business-Security Impact**: Clickjacking/type-confusion margin; low for a small internal POS.
- **Current Mitigations**: Basic `frame-ancestors 'self'` in the only CSP.
- **Implementation Complexity**: Low (middleware or .htaccess).
- **Recommendation**: Defer; bundle with production/header hardening (§14). Re-evaluate `unsafe-*` sources after production build.

### 5.8 F-SEC-01 — Session cookie not Secure; `APP_DEBUG=true` in `.env.example`

- **Current State**: `config/session.php:172` `'secure' => null` (cookie sent over plain HTTP); `http_only` true (`config/session.php:185`); `SESSION_ENCRYPT=false` default; `.env.example` ships `APP_DEBUG=true` and `APP_URL=http://localhost:8000`.
- **Evidence**: `config/session.php:172,185`; `.env.example:4-5,30-34`.
- **Severity**: Low–Medium (session exposure on non-HTTPS transport).
- **Exploitability**: Medium only if served without TLS; HTTPS makes it moot.
- **Business-Security Impact**: Session theft over cleartext.
- **Current Mitigations**: `http_only` cookie; Step 5 rate limiting.
- **Implementation Complexity**: Low, but **requires deployment/business clarification** (TLS termination; `APP_DEBUG=false` in prod).
- **Recommendation**: Defer to production-config hardening (§14). Deployment must enforce HTTPS + debug-off.

### 5.9 F-PASS-01 — Password policy is minimal but non-critical

- **Current State**: Creation uses `Password::min(8)` (`StoreUserRequest.php:27`); reset `min:8|confirmed` (`ResetPasswordController.php:29`); login validates presence only (normal). No complexity/history rules. `UpdateUserRequest` drops the field when blank (benign keep-unchanged pattern).
- **Evidence**: `StoreUserRequest.php:27`; `ResetPasswordController.php:29`; `Auth/LoginController.php:28`.
- **Severity**: Low.
- **Exploitability**: Low.
- **Business-Security Impact**: Minimal; min-8 is the floor for a small staff set.
- **Current Mitigations**: `Password::min(8)`, `BCRYPT_ROUNDS=12`, `hashed` cast.
- **Implementation Complexity**: Low.
- **Recommendation**: Defer optional strengthening (e.g., breached-passwords check); not a priority.

### 5.10 F-PROXY-01 — No trusted-proxy configuration

- **Current State**: `bootstrap/app.php` registers no `trustProxies`. Behind a reverse proxy/LB, `$request->ip()` (used by the Step 5 rate-limiter key and audit) can reflect the proxy instead of the client.
- **Evidence**: `bootstrap/app.php:17-24` (no trustProxies); rate-limiter key uses IP.
- **Severity**: Low (today) / Medium behind a proxy.
- **Exploitability**: Low–Medium (proxy-dependent; affects rate-limit correctness/evasion).
- **Business-Security Impact**: Rate limiter could be mis-keyed or evaded once behind a proxy/NAT.
- **Current Mitigations**: None; not applicable at single-host deployment.
- **Implementation Complexity**: Low (one config array).
- **Recommendation**: **Requires deployment/business clarification**; configure once the real topology is known (§14).

### 5.11 F-OBS-01 — Minor observations (folded into the defer list)

- API `login` uses the default guard, so an API token also starts a server-side session (`Api/AuthController.php:25`) — harmless today; revisit if tokens are ever scoped.
- No audit event for export actions or authorization failures.
- Export/ReportService paths use full-table `->get()` (see F-PAG-01).

## 6. Priority Matrix

Factual/technical criteria only (severity, exploitability, integrity/confidentiality/availability impact, affected users, complexity, regression risk, dependencies).

| Finding | Severity | Exploitability | Impact | Affected users | Complexity | Regression risk | Dependencies | Priority |
|---|---|---|---|---|---|---|---|---|
| **F-INTEG-01** Seller attribution tamper | High | Very High | Integrity (data corruption) | vendedor (all) | Low | Low | Business rule for admin override | **1** |
| **F-INTEG-02** No seller scoping | High | High | Confidentiality (sales + PII) | vendedor (all) | Low–Med | Low–Med | Business visibility rule | **2** |
| F-SANCTUM-01 Token expiration | High | Medium | Confidentiality | API clients | Low | Medium | Token lifetime decision; refresh flow | 3 |
| F-PII-01 Client PII access | Med–High | High | Confidentiality | vendedor | Medium | Medium | Role→PII policy | 4 |
| F-EXPORT-01 Export PII scope | Medium | High | Confidentiality | vendedor | Low | Low | Subsumed by F-INTEG-02 | 5 |
| F-PAG-01 Unbounded limits | Low–Med | Medium | Availability | API users | Low | Low | none | 6 |
| F-HDR-01 Headers/CSP | Low–Med | Low–Med | — | all | Low | Low | none | 7 |
| F-SEC-01 Session/HTTPS | Low–Med | Medium* | Confidentiality* | all | Low | Low | HTTPS deployment | 8 |
| F-PASS-01 Password policy | Low | Low | — | all | Low | Low | optional | 9 |
| F-PROXY-01 Trusted proxies | Low–Med | Low–Med* | Availability* | all | Low | Low | Deployment topology | 10 |

*Only material once deployed behind TLS/proxy. Priorities 1–2 are the recommended Step 6 scope.

## 7. Recommended Phase 1 Step 6

**Seller attribution is derived from the authenticated principal, and sales data is scoped per seller (F-INTEG-01 + F-INTEG-02).**

Rationale (technical):

1. **Data integrity first**: F-INTEG-01 is the only open finding that lets a user *corrupt* business data (attribution/commissions/performance) rather than merely read or exceed it, and it is trivially exploitable by the most numerous role with zero special tooling.
2. **Broadest clean win**: F-INTEG-02 removes the widest confidentiality gap (all financial + client PII across sellers) and closes the F-EXPORT-01 leak on the same code paths.
3. **Low risk, no migration**: logic + view only; no schema change, no settings, no dependency churn; small regression surface covered by the 232-test baseline.
4. **Preserves history**: legacy `seller_id` values stay untouched — audit continuity kept; no destructive backfill.
5. **Sequences well**: the higher-severity leftovers (Sanctum expiration, PII policy, headers/production config) each require a business or deployment decision; Step 6 depends only on one narrow, well-scoped rule.

**Business decision required before implementation** (flag; do not silently decide):

- Rule A — Default posture: a `vendedor` may only register sales attributed to himself/herself; admin (and per policy optionally `encargado`) may select another seller. If management instead requires free reassignment by sellers (e.g., multi-register cashiering under another login), that is an accepted weakening and must be recorded as such.
- Rule B — Whether `vendedor` sales lists/exports/invoices shall be limited to the operator's own sales (default: yes, least-privilege) or remain global (explicit business acceptance required).

Both rules map to the existing permission model (`canSell`, `canWriteSales`, roles in `routes/web.php`). If either is left unanswered at implementation time, write it as "Requires deployment/business clarification".

## 8. Proposed Scope

After approval:

1. **Forced attribution on create** (`SaleController::store`; routes `sales.create`/`sales.store`, vendedor path): set `seller_id = auth()->id()` when the operator is not an override-allowed role; ignore any client-supplied value. Admin/override role retains the request value. No change when the value already equals the principal.
2. **Attribution on update** (`SaleController::update`, already admin-only): keep request-driven `seller_id` validated against `users.id`; no new rights granted.
3. **Form behavior** (`sales/form.blade.php`): for non-override roles render the seller selector fixed to the current user (hidden/disabled input defaulting to `auth()->id()`); admins keep the explicit selector.
4. **Request hardening** (`StoreSaleRequest`): keep `seller_id` `required|integer|exists` for the override path; the non-override path stops trusting it entirely.
5. **Scoping for `vendedor`**: apply `where('seller_id', auth()->id())` to `SaleController::index`, `show`, `exportPdf` (invoice), `exportListPdf`, `exportExcel`, and `Api\SaleController::index`/`show` (and the API dashboard recent-sales/top-client lists), only when the operator is `vendedor`; admin/encargado keep global view pending Rule B.
6. **Tests** (§11) updated/extended to lock the new behavior in.

## 9. Explicitly Out of Scope

- F-SANCTUM-01 (token expiration/abilities) — deferred; requires token-lifetime decision.
- F-PII-01 client role→field policy — deferred; requires business decision.
- F-PAG-01 per_page/limit caps and chunked exports — deferred maintenance hardening.
- F-HDR-01 security headers / CSP rewrite — deferred to a production/build hardening step.
- F-SEC-01 HTTPS, Secure cookie, APP_DEBUG production switch — deployment responsibility.
- F-PASS-01 and F-PROXY-01 — deferred.
- Historical `seller_id` values: no backfill/rewrite. Any data-corruption remediation is deliberately out of scope; the fix prevents future corruption.
- No schema changes, no settings, no composer dependencies, no new routes.

## 10. Proposed Implementation Strategy

- Add a small authorization predicate on `User` (mirroring `canSell`/`canWriteSales`, e.g. `canOverrideSeller()`) so the rule is single-sourced and unit-testable.
- Apply the predicate per request in `SaleController::store`/`update` and, for Rule A confirmation, in the blade.
- Reuse the existing scoped-query helper style used by `search`/`betweenDates` (`Sale.php`) for the seller scope (e.g. a `scopedToSeller(?User)` local scope or an `->forUser($user)` clause) so web, exports, and API share one path.
- `SalesExport` receives the optional user context like the other filters it already accepts.
- No controller restructuring; changes stay within `SaleController`, `Api\SaleController`, `Api\DashboardController`, `StoreSaleRequest`, `User`, and the sale form view.

## 11. Test Strategy

- **Feature (web)**: vendedor creating a sale with a forged `seller_id` is stored with the authenticated user's id; vendedor index/list-export/invoice only return own sales (someone else's sale id → 403/404); admin/override role can still select another seller; `update` keeps existing behavior for admin.
- **Feature (API)**: vendedor token list/show of own sales only; other-seller id → 403/404; admin token unrestricted per Rule B while global.
- **Existing suites**: sales create/update/export/dashboard tests updated where they assumed any-seller attribution; brute-force/login tests (Step 5) untouched.
- **Expected count**: 232 baseline → approx. 238–242 after additions.
- **Commands**: `composer test` (config:clear + artisan test, in-memory sqlite). Group `mysql` if any touched query path needs lock verification (not expected: attribution/scoping adds no locking).

## 12. Verification Plan

1. `composer test` passes fully; new cases cover forged attribution and cross-seller access.
2. Manual check: log in as a `vendedor`, confirm the seller field is fixed to self; create a sale and verify `seller_id`; confirm list/export/invoice contain only that seller; verify the admin override path still works.
3. `vendor\bin\pint` clean on modified files; `php -l` clean.
4. DB: no migrations; verify no column changes via `php artisan migrate:status`.
5. Regression: sales stock reconcile/paid-toggle/report paths (Step 1–4 work) still pass.

## 13. Risks / Edge Cases

- **Returning soft-deleted/legacy sellers**: scoping must use the users' table with active users only, matching today's `sellers` seed lists; no breakage for historical sales whose seller no longer exists (they simply never appear for vendedor queries; admin still sees them).
- **`encargado`/admin default**: before Rule B is answered, keep global visibility for admin/encargado to avoid regressing the permission model; only `vendedor` is scoped.
- **Invoice access hardening**: scoping `exportPdf`/`show` by seller converts a global-ID access into an owned-resource check; ensure the 404/403 semantics match `Route::find` binding (use `findOrFail` on the scoped query so non-owned ids 404).
- **Test drift**: existing tests that fetched or asserted other-sellers' data as a vendedor must be updated deliberately (they currently encode the insecure behavior).
- **Blade + Alpine**: leaving the seller field visible-but-disabled is a UX affordance; disabled inputs are not submitted, so the forced value must come from server-side derivation, never from the form.
- **No history rewrite**: documented so nobody "fixes" past data inadvertently.

## 14. Deferred Findings

| Deferred item | Reason | Unlock condition |
|---|---|---|
| F-SANCTUM-01 token expiration/abilities + prune | Would require refresh-flow rework | Business decision on token lifetime; mobile client behavior |
| F-PII-01 client PII role/field matrix | Policy design, not code alone | Business decision on role→field access |
| F-PAG-01 caps + chunked exports | Low current risk; maintenance item | none (schedule) |
| F-HDR-01 headers/CSP hardening | Tied to production build | Production build + env review |
| F-SEC-01 Secure cookie / HTTPS / debug | Deployment concern | Deployment/business confirmation (TLS) |
| F-PASS-01 strengthening | Low priority | optional |
| F-PROXY-01 trusted proxies | Topology unknown | Deployment topology |
| F-OBS-01 (API session, export audit, memory) | Minor; tracked | Opportunistic |

## 15. Implementation Checklist

- [x] Approve Rules A and B (recorded as approved for this step).
- [x] Add `canOverrideSeller()` predicate on `User` (single source).
- [x] Force `seller_id = auth()->id()` for non-override roles in `SaleController::store`.
- [x] Scoped query helper for sales by user; apply to `index`, `show`, `exportPdf`, `exportListPdf`, `exportExcel` and `Api\SaleController::index`/`show` for `vendedor`.
- [x] Scope API dashboard recent-sales/top-client for `vendedor`.
- [x] Blade: seller select fixed for non-override roles.
- [x] Update `StoreSaleRequest` per new trust boundary.
- [x] Add feature tests (web forged attribution, web/API scoping, 404 semantics, admin override).
- [x] Run `composer test`; run Pint + `php -l`; confirm no migrations.
- [x] Flip status to ACTIVE and add §16 Implementation Record on approval + implementation commit.

## 16. Implementation Record (2026-09-19)

Approved plan executed in full as scoped.

### Public behavior (business rules shipped)

- **Rule A — Forced seller attribution.** On sale creation, `seller_id` is forced server-side to `auth()->id()` for `vendedor` and `encargado`, ignoring any client-supplied value; only `admin` (override-allowed) may select another seller via the explicit selector. Sale `update` remains admin-only and preserves override semantics. No historical `seller_id` values were modified or backfilled.
- **Rule B — Vendedor sales visibility.** Web sales index/show/invoice-PDF/list-export/Excel-export, API sales index/show, and web+API dashboard sale-derived data (recent sales, top client, counts, KPIs, charts) are scoped to `seller_id = auth()->id()` when the operator is `vendedor`; `admin` and `encargado` retain global visibility.
- Direct access to another seller's sale returns **404** (web and API), never 403.

### Files changed

- `app/Models/User.php` — `canOverrideSeller()` (admin-only); `canSell()` extended to include `encargado` so store/create routes open to encargado per approved rule.
- `app/Models/Sale.php` — reusable `scopeVisibleTo(?User $user)` (scopes only `vendedor`; null-safe).
- `app/Http/Requests/StoreSaleRequest.php` — conditional `seller_id` trust: `required|integer|exists` for override roles, `nullable|integer` otherwise.
- `app/Http/Controllers/SaleController.php` — `store` forced attribution; `update` defensive guard; `index`/`show`/`exportPdf`/`exportListPdf`/`exportExcel` scoped (`show`/`exportPdf` use scoped `firstOrFail` → 404).
- `app/Exports/SalesExport.php` — optional `?User $user` constructor param; `collection()` applies `visibleTo`.
- `app/Http/Controllers/Api/SaleController.php` — `index`/`show` scoped for `vendedor` (scoped `firstOrFail` → JSON 404).
- `app/Http/Controllers/DashboardController.php` + `app/Http/Controllers/Api/DashboardController.php` — `salesMonthly`, `revenueTrend`, `salesBySeller`, `recentSales`, `topClient`, `counts.sales`/`receivables`, `kpis` scoped; `topProducts` restricted via `whereHas('sale', seller_id = user)` for `vendedor`.
- `routes/web.php` — `sales.create`/`sales.store` open to `encargado`.
- `resources/views/sales/form.blade.php` — non-override roles get a hidden `seller_id = auth()->id()` and a read-only seller display; admin keeps the `<x-select>`.
- `resources/views/dashboard/index.blade.php` — `canSell` includes `encargado`.

### Tests

- Added `tests/Feature/SalesSellerScopingTest.php` (16 tests / 61 assertions): forged attribution (vendedor + encargado → own id), admin override preserved, create-form fixed-seller UI, vendedor index/show/invoice-PDF/list-export/Excel own-only, admin + encargado global, API index/show scoping (vendedor own-only, other-seller → 404), API + web dashboard recent-sales/KPI/top-client isolation, 404 semantics.
- Updated regressions that encoded any-seller access: `SaleTest` manager-create expectation + actor swaps to admin for index/show/export cases; `RoleAccessTest` manager create OK + seller-dashboard fixture attribution; `ApiAuthorizationTest` seller sale-show and dashboard fixtures attributed to the seller actor.
- Full run: `php artisan test` → **249/249 passed, 1,188 assertions**.
- MySQL concurrency group: `vendor\bin\phpunit -c phpunit.mysql.xml` → **3/3 passed, 21 assertions**.
- `vendor\bin\pint --test` → **passes** (one blank-line-at-EOF fix applied).
- `php -l` clean on all changed PHP files.

### Database impact

- **No migrations added or pending** (`php artisan migrate:status`: all 16 Ran).
- Dev DB data untouched: counts unchanged **180 sales / 450 sale_items / 37 products**; `SUM(subtotal)=131404.99`, `SUM(tax_amount)=15768.60`, `SUM(total)=147173.59` — byte-identical to the pre-implementation baseline. No historical `seller_id` modifications.

### Commits

- Implementation: `657a55f` — `feat: scope sales by seller and harden seller attribution`.

---

Implementation status: IMPLEMENTED (2026-09-19 — see §16)

Approval required before implementation: YES (approved during plan review)