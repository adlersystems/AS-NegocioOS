# Phase 1 — Step 7: API Token Security & Sanctum Hardening

## 1. Status

**ACTIVE** — implemented and merged to `main` on 2026-09-19 (see §20 Implementation Record).

## 2. Current Repository State

Verified directly on 2026-09-19 (fresh checks, not carried over from Step 6):

- Branch: `main`; HEAD `483484c` (`docs: record step 6 implementation evidence (657a55f)`); `origin/main` == `main`, in sync.
- Working tree: **clean** (`git status --short` empty).
- Test baseline (re-run fresh): `composer test` → **249/249 passed, 1,188 assertions** (SQLite in-memory via `phpunit.xml`).
- MySQL concurrency group: `vendor\bin\phpunit -c phpunit.mysql.xml` → **3/3 passed, 21 assertions** (`database/migrations` all applied; `tests/Feature/MysqlConcurrencyTest.php`).
- No custom console commands (`app/Console` does not exist). `routes/console.php` only contains the `inspire` stub — **no scheduler wiring**.
- Dev SQLite DB (`database/database.sqlite`): 3 users, and the `personal_access_tokens` table contains **0 rows** today (no live API tokens are persisted in the dev database).

## 3. Current Sanctum Architecture

Environment: `laravel/sanctum: ^4.3` → locked **v4.3.3** (`composer.lock`).

### Token creation
- `app/Http/Controllers/Api/AuthController.php:36` — `$user->createToken('api')->plainTextToken`.
- `HasApiTokens::createToken($name, $abilities = ['*'], $expiresAt = null)` — so the issued token gets:
  - `name` = `api`,
  - `abilities` = `['*']` (unrestricted),
  - `expires_at` = `NULL` (no explicit expiry).
- Plain text returned to client is `<id>|<random>`; the DB stores `hash('sha256', plain)` only (`PersonalAccessToken::findToken`).
- `Api/AuthController.php:25` authenticates through the session `web` guard (`auth()->attempt`); on success a Sanctum token is minted. The login route is `auth:sanctum`-free and protected only by the Step 5 `ThrottlesLogins` trait.

### Token authentication
- Middleware `auth:sanctum` on the API group (`routes/api.php:15`).
- Sanctum `Guard::isValidAccessToken()`:
  ```php
  (! $this->expiration || $token->created_at->gt(now()->subMinutes($this->expiration)))
  && (! $token->expires_at || ! $token->expires_at->isPast())
  ```
  where `$this->expiration = config('sanctum.expiration')`. With both null, **every token validates forever**.
- `guard => []` in `config/sanctum.php` → no session/cookie fallback for the API guard (token-only, as designed).
- `last_used_at` tracking is enabled (default `true`); SanctumServiceProvider passes `config('sanctum.expiration')` and `config('sanctum.last_used_at', true)` into the guard at boot.

### Expiration
- `config/sanctum.php:55` — `'expiration' => null` (hardcoded, not `env`-driven).
- The `expires_at` column **exists, is nullable and indexed** (`database/migrations/2026_09_05_160033_create_personal_access_tokens_table.php:21`) but is **never written** anywhere in application code.
- No other expiration mechanism exists (no refresh, no rotation, no per-token overrides).

### Abilities
- No route or controller ever calls `$user->tokenCan()`, `tokenCant()`, Sanctum `CheckAbilities` (`abilities:` middleware), `Sanctum::actingAs($user, [...abilities])` beyond defaults, or `PersonalAccessToken::$abilities` reads. Grep across `app/`, `routes/`, `tests/` for `abilities:`, `CheckAbilities`, `tokenCan`, `tokenCant` returns **zero matches**.
- `bootstrap/app.php` registers only the `role` middleware alias; no ability middleware is registered.
- Authorization is 100% application-level: `role:` route middleware + `User::can*()` helpers in controllers (Steps 3, 4, 6).

### Logout / revocation
- `Api/AuthController.php:44-55` — deletes only `currentAccessToken()` (guards against `TransientToken`). This revokes just the single token used for that request.
- No other revocation surface: no admin token-management UI or endpoint, no "revoke all tokens" action, no user-facing token list.

### Routes (API, `routes/api.php`)
| Route | Guard | Extra |
|---|---|---|
| `POST auth/login` | none (web-guard attempt + throttle) | Step 5 limiter |
| `POST auth/logout` | sanctum | — |
| `GET me` | sanctum | — |
| `GET dashboard` | sanctum | — |
| `GET clients`, `GET clients/{client}` | sanctum | — |
| `GET products`, `GET products/{product}` | sanctum | — |
| `GET sales`, `GET sales/{sale}` | sanctum | Step 6 `visibleTo` scoping |
| `GET inventory` | sanctum | `role:admin,encargado` |
| `GET reports` | sanctum | `role:admin,encargado` |
| `GET settings` | sanctum | `role:admin` |

### Token schema (`personal_access_tokens`)
`id, tokenable(morphs), name(text), token(64, unique), abilities(text, nullable), last_used_at(nullable), expires_at(nullable, indexed), timestamps`. Casts: `abilities` json, `expires_at`/`last_used_at` datetime.

## 4. Current Security Findings

**F-SANCTUM-01 — API tokens never expire and carry unrestricted abilities. Confirmed current.**

- Evidence (all re-verified against the current tree):
  - `config/sanctum.php:55` — `'expiration' => null`.
  - `Api/AuthController.php:36` — `createToken('api')` ⇒ `abilities ['*']`, `expires_at null`.
  - `Guard::isValidAccessToken()` — both expiry signals null ⇒ validity is perpetual.
  - `personal_access_tokens.expires_at` — present but never populated; no cleanup routine.
  - Token created with unrestricted `['*']`; abilities are never enforced (nothing checks them).
- Impact: a leaked token grants **indefinite**, unrestricted-by-ability, role-scoped read access to that user's permitted dataset (still bounded by role middleware and Step 4/6 controller gates — the abilities layer adds nothing, but does not limit either).
- Severity: **High** (confidentiality) — the only expiry control is the absence of a leak and the user's own logout.
- Exploitability: Medium — requires a token leak; consequence is long-lived when one occurs.
- Existing mitigations: Step 5 throttling protects the credential prompt; logout revokes only the token presented; no back-channel/administrative revocation; no token cleanup.
- Related minor note (F-LOW-02, carried from Step 4): logout revokes only the current token, with no administrative mechanism to revoke a user's remaining tokens.

## 5. API Client / Usage Evidence

**Conclusion: the repository contains NO persistent API client.**

- No mobile, POS, or SPA code: `resources/` (JS/CSS/views) contains no `fetch`/`axios` calls and no `/api/` URLs. Grep for `axios`, `/api/`, `Bearer`, `createToken` across non-vendor sources finds **only server-side application code, tests, and docs**.
- No integration scripts, no `scripts/` consumer, no Postman/OpenAPI spec, no environment token secrets.
- Documentation (README §"API REST (Sanctum)", user manuals) describes the API surface generically (auth token + dashboard/clients/products/sales/inventory/reports/config) with **no stated client behavior, no expiry expectation, and no refresh flow**.
- The only API "consumers" are the feature tests (`ApiAuthTest`, `ApiAuthorizationTest`, `ApiEndpointsTest`, `ApiAuthThrottleTest`, `SalesSellerScopingTest`) and ad-hoc human testing.
- Any mobile/POS client is hypothetical at this point. Step 6's business note ("POS mobile clients may legitimately require long-lived tokens") is a forward-looking consideration, **not** evidence of an existing client.

This matters: enabling expiration today interrupts **zero** known consumers, but must be flagged as a deployment decision precisely because a future always-on client would be affected.

## 6. Token Lifetime Analysis

Live mechanisms available (verified in v4.3.3):

1. **Global config expiration** — `config('sanctum.expiration')` in minutes. Enforced **at request time** by the guard against `created_at`. When set, it bounds *every* token (including already-issued ones) based on creation time, with no backfill required.
2. **Per-token `expires_at`** — settable via `createToken($name, $abilities, $expiresAt)`. Durable in the DB; ignored by old rows that are null; useful for heterogeneous lifetimes.
3. **No expiration (current).**

Options and consequences:

| Option | Security | Operational impact | Notes |
|---|---|---|---|
| No expiration (status quo) | Indefinite leak window | Zero | Rejected: the finding's core gap. |
| Short-lived (e.g. 8 h = 480 min) | Tight window | Daily re-login; smoke-test friction | Strongest posture; risk if a terminal is used all day across a shift boundary. |
| **Workday-scale (recommended default) — 12 h = 720 min** | Leak window ≤ shift | One login per business day; expired next morning | Balances posture vs. real POS-style usage. |
| Multi-day (e.g. 30 d) | Long window | Rare re-login | Similar exposure to indefinite today; only cosmetic gain. |
| Configurable (`env` + default) | Depends on value | Depends | Recommended plumbing so the business decision is a one-line change. |

**Recommendation:** adopt **config-driven expiration** via `'expiration' => env('SANCTUM_EXPIRATION', 720)` minutes (12 h workday default) in `config/sanctum.php`, so:
- the rule is single-sourced and retroactive (bounds legacy tokens immediately),
- no `expires_at` backfill or per-token writes are needed,
- `sanctum:prune-expired` can then also prune by `created_at`,
- the operator can override with `SANCTUM_EXPIRATION` in the environment without code changes.

**Business decision required (explicit): the exact production lifetime value** (recommended default **720 minutes / 12 hours**, workday-aligned). If a future POS/PDA must run persistent-token sessions across shifts, either raise the value or request a refresh flow later (both out of this step's scope to fix pre-emptively).

Per-token `expires_at` overrides are deliberately **not** introduced now: there is no class of tokens needing a different lifetime, and config-driven expiry is simpler and self-maintaining.

## 7. Token Abilities Analysis

- Current tokens carry `['*']`, but **no code path inspects abilities**, so granting or withholding them changes nothing today. Endpoint access is decided entirely by `role:` middleware and controller `can*()` gates (the Step 4 matrix + Step 6 scoping).
- Introducing ability classes (`sales`, `clients`, `products`, …) and `abilities:` gates would **duplicate** the existing, already-tested authorization system without adding a new trust boundary: a token is bound to a user, and the user's role already fixes the surface. Abilities only earn their keep when *one user* needs *multiple token classes* (e.g. a read-only integration key distinct from the interactive one). **No such consumer exists.**
- Minimal hardening that is still defensible without endpoints: mint tokens with an **explicit, least-privilege ability list** (`['api']` on token creation) so the token's intended scope is intentional and self-documenting, and any future ability gate does not accidentally inherit `['*']`. This is functionally a no-op today (nothing checks it) and is trivially reversible.

**Determination:** abilities are **not justified as a security layer** at this product stage. Optional 1-line tidy: `createToken('api', ['api'])`. Do **not** add `abilities:` middleware or token-class differentiation.

## 8. Token Revocation / Cleanup Analysis

Current state:
- Logout deletes the **presented token only** (`Api/AuthController.php:44-55`), covered by `ApiAuthTest::test_logout_revokes_the_token`.
- No administrative revocation surface (no UI, no endpoint, no "revoke all"). Steps 4/6 noted this as F-LOW-02.
- No cleanup job. Sanctum v4.3.3 ships `sanctum:prune-expired` (`Laravel\Sanctum\Console\Commands\PruneExpired`), which:
  - deletes rows with `expires_at < now() - hours`, **and**
  - when `config('sanctum.expiration')` is set, also deletes rows with `created_at < now() - (expiration + hours)` — i.e. it covers the created_at-window scheme even when `expires_at` is null.
  The command is registered with Sanctum but is **only run if scheduled**; this app currently schedules nothing.

Recommendation for Step 7:
- **Enable scheduler**: register `Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily()` in `routes/console.php` (Laravel 11+ schedule registration; no `Kernel` class in this app). Keep the default 24 h retention so an expired token row remains briefly for audit/debugging.
- **Deployment requirement (documented, not implemented here):** the host must invoke `php artisan schedule:run` every minute (cron `* * * * *`) for pruning to happen. Without this, expiration still protects (the guard rejects expired tokens), but rows accumulate.
- Administrative multi-token revocation is **out of scope**: no evidence a user has more than the token in hand; manual removal via tinker/DB remains available (documented). Token can be rotated by revoking + re-login.

## 9. Backward Compatibility / Deployment Impact

- **Retroactivity**: enabling `config('sanctum.expiration')` bounds **every** token by `created_at` at the moment the config ships — including previously issued tokens with `expires_at = null`. This is the desired behavior and requires no backfill.
- **Current real-world impact**: the dev DB holds **0 tokens** and the repo has **no API clients**, so shipping expiration disrupts no known token in-tree.
- **Rollout**: a one-line config change (+ optional scheduler wiring) on deploy; app code changes limited to `Api/AuthController.php` if the abilities tidy is included. No migration.
- **Re-authentication**: after expiry, clients call `POST /api/auth/login` again (the only available flow; `me` does not refresh). Expired-token requests return 401 with the existing JSON error shape (`shouldRenderJsonWhen` → `Handler`), same as an invalid/missing token; clients treat it as "log in again".
- **User experience**: one login per window (default 12 h). Step 5 throttling is not affected (it keys on credential attempts, not token age), so re-login cadence stays safe.
- **Future always-on client:** a POS/PDA that stays authenticated across shifts would be interrupted at the window boundary and has no refresh mechanism — explicitly a business decision (see §6) and the reason a long default (12 h) is preferred over an 8 h one at this stage.

## 10. Priority Confirmation

F-SANCTUM-01 **remains the correct Phase 1 Step 7.**

- **F-PII-01** (client PII role/field policy): needs a business decision, not primarily a code change; not blocked by anything here. Lower urgency than an unbounded credential leak.
- **F-PAG-01** (pagination caps/chunking): availability hardening at MVP scale; low real risk; no consumer pressure.
- **F-HDR-01 / F-SEC-01** (headers, CSP, HTTPS/Secure-cookie production config): tied to a production build/deployment that does not exist yet (dev + FTP bundle); deployment responsibility already tracked.
- **F-PASS-01** (password policy): low priority, intentionally optional.
- **F-PROXY-01** (trusted proxies): topology unknown, deployment-only.
- **F-SANCTUM-01**: self-contained, high-severity confidentiality gap; tiny diff; uses an existing indexed column; natural continuation of the Step 4–6 authorization hardening; and is cheapest now because the token count is effectively zero. Nothing in the current code makes another finding materially more urgent.

## 11. Recommended Step 7 Scope

Core (implement in Step 7):

1. **Enable token expiration** in `config/sanctum.php`: `'expiration' => env('SANCTUM_EXPIRATION', 720)` (minutes; 12 h workday default). This is the single most effective control and is retroactive.
2. **Explicit, least-privilege token ability** on issuance: `$user->createToken('api', ['api'])` in `Api/AuthController.php:36`. No endpoint enforces abilities; this only pins the token's declared scope (self-documenting, no `['*']`).
3. **Schedule expired-token pruning**: add `Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily()` to `routes/console.php`. Document the `php artisan schedule:run` cron requirement in the implementation record and README note.
4. **Tests** (§15) locking: token minting, valid/expired acceptance, revocation, role/Step-4/Step-5/Step-6 regressions, pruning determinism.

Business/deployment decision required before/at rollout (not a blocker to implementing the mechanics):
- **Exact lifetime value** — recommended default 720 min (12 h); selectable via `SANCTUM_EXPIRATION`.
- **Scheduler cron** on the production host so cleanup actually runs.

Optional-but-declined (explicitly): per-token expiry overrides, ability middleware/gates, refresh tokens, multi-token classes — none justified by current evidence.

## 12. Explicitly Out of Scope

- Refresh-token / token-rotation architecture (no persistent client; re-login flow suffices; re-evaluate only if a long-session POS materializes).
- MFA, password policy (F-PASS-01), client PII policy (F-PII-01).
- Pagination caps/chunking (F-PAG-01), security headers/CSP (F-HDR-01), HTTPS/Secure-cookie production config (F-SEC-01), trusted proxies (F-PROXY-01).
- Administrative token-management UI / "revoke all tokens" endpoint (F-LOW-02 — condition of `logout` retained; documented manual alternative).
- SPA stateful/cookie authentication (`guard => []` stays; this is token-only by design).
- Any unrelated API redesign; per-token heterogeneous expiry.

## 13. Proposed Implementation Strategy

No implementation in this step. Technical design only:

1. `config/sanctum.php`: replace `'expiration' => null` with `'expiration' => env('SANCTUM_EXPIRATION', 720)` and document the semantics in the config comment block.
2. `app/Http/Controllers/Api/AuthController.php`: mint with `$user->createToken('api', ['api'])` (abilities unchanged in effect).
3. `routes/console.php`: import `Illuminate\Support\Facades\Schedule`; add the `sanctum:prune-expired` daily schedule with `--hours=24`.
4. No middleware aliases, no model overrides, no migrations, no controller authorization changes: the existing `role:`/`can*` gates (Steps 3–6) remain the single authorization authority. Expiration complements, never replaces, them.
5. Tests: `tests/Feature/ApiTokenExpirationTest.php` (new) + targeted additions to `ApiAuthTest.php` for the ability-array assertion.

Deterministic time strategy (no `sleep`): drive time with `Carbon::setTestNow()`; mint a token, run requests while "now" is within the window (valid), then advance `created_at` (or reuse `setTestNow` + a token created `expiration + 1` minute ago) for the rejected case; for the row-prune test, insert tokens with controlled `created_at`/`expires_at` and assert deletion sets.

## 14. Database / Migration Impact

- **No migration required.** `personal_access_tokens.expires_at` exists, nullable, indexed (`2026_09_05_160033`, batch [1]). The guard enforces config-driven expiry from `created_at`, so no column write is needed for the core control.
- Dev DB safety: `personal_access_tokens` is empty today; the network `MysqlConcurrencyTest` group keeps using the current schema unchanged. No data backfill or rewrite.

## 15. Test Strategy

Deterministic feature tests (new `tests/Feature/ApiTokenExpirationTest.php`; existing suites stay green):

1. **Token creation** — `POST /api/auth/login` returns `token` + `user` and persists exactly one `personal_access_tokens` row with `abilities = ["api"]` (extend `ApiAuthTest`).
2. **Token authentication** — a freshly minted token reaches `/api/me` (200) and `/api/dashboard` (200) within the window.
3. **Expired token rejection** — token created `config('sanctum.expiration') + 1` minute before "now" (`Carbon::setTestNow`) → 401; no record deletion side effects.
4. **Valid unexpired acceptance** — same setup with age < window → 200 (mirrors #2 with an explicit age).
5. **Logout/revocation** — existing `test_logout_revokes_the_token` retained; assert subsequent request is 401 and row count returns to 0.
6. **Role authorization still enforced (Step 4 regression)** — with a *fresh, valid* token, vendedor hits `GET /api/reports` → 403, admin → 200; `settings` admin-only; cost/margin hidden for vendedor (`ApiAuthorizationTest` still green).
7. **Seller scoping still enforced (Step 6 regression)** — vendedor token lists only own sales; other-seller `GET /api/sales/{id}` → 404 (`SalesSellerScopingTest` still green).
8. **Step 5 throttling unaffected** — `ApiAuthThrottleTest` still green (throttle keys on credential attempts, not token age).
9. **Ability behavior** — assert the persisted token row's `abilities` equals `["api"]` (mint-level contract only; no endpoint enforcement).
10. **Pruning** — with `Carbon::setTestNow`, insert tokens with (a) `expires_at` past beyond retention, (b) `created_at` older than `expiration + retention`, (c) still-valid fresh token; run `php artisan sanctum:prune-expired --hours=24`; assert (a)+(b) gone, (c) kept, and the default `--hours=24` behavior matches the doc.

All time control via `Carbon::setTestNow` / explicit `created_at`/`expires_at` writes — **no sleep-based tests**.

## 16. Verification Plan

1. `composer test` full suite green (249 baseline + new expiration/prune tests); re-run after changes.
2. `vendor\bin\phpunit -c phpunit.mysql.xml` (3/3) — confirms concurrency path unaffected (no touching the locking code).
3. `vendor\bin\pint --test` clean on all modified files.
4. `php -l` on every changed PHP file.
5. `php artisan migrate:status` — unchanged, all Ran, none pending (no new migrations).
6. Manual API check (via `curl`/tinker on a scratch token): login → token works on `/api/me`; confirm created row has `abilities=["api"]`; confirm (via time math on a back-dated `created_at` row) an aged token 401s; confirm prune command removes only the aged row.
7. Confirm dev DB `personal_access_tokens` count after manual checks returns to baseline (cleanup after manual verification).

## 17. Risks / Edge Cases

- **Config is retroactive**: enabling expiration immediately bounds all live tokens by `created_at`; any always-on client would 401 at the boundary with no refresh. Mitigation: workday default + documented re-login flow; flagged as the explicit business decision.
- **Scheduler absence**: without the cron, expired rows accumulate (expiry still enforced by the guard). Document as a deployment requirement, not a code risk.
- **Abilities tidy is cosmetic**: no endpoint gate added, so it cannot break authorization; the worst case is a future developer expecting an unminted ability — avoided by the explicit `['api']` contract + tests.
- **Test determinism**: `Carbon::setTestNow` must be reset in `tearDown`; SQLite/MySQL parity for `expires_at` comparisons (datetime precision) is covered by running both suites.
- **Step5 throttle interplay**: re-login after expiry is throttled like any login; no bypass introduced.
- **Manual/adhoc tokens**: developers' throwaway tokens now expire; negligible, documented in the record.

## 18. Deferred Findings

| Deferred item | Reason | Unlock condition |
|---|---|---|
| F-PII-01 client PII role/field matrix | Policy design, not code alone | Business decision on role→field access |
| F-PAG-01 per_page/limit caps + chunked exports | Low current risk | none (schedule) |
| F-HDR-01 headers/CSP hardening | Tied to production build | Production build + env review |
| F-SEC-01 Secure cookie / HTTPS / debug | Deployment concern | Deployment/business confirmation (TLS) |
| F-PASS-01 password policy | Low priority | optional |
| F-PROXY-01 trusted proxies | Topology unknown | Deployment topology |
| F-LOW-02 admin multi-token revocation | No evidence of multi-token users; manual tinker path available | Product need, or an always-on client |
| Refresh-token / token-rotation flow | No persistent client exists | Only if a long-session POS/PDA materializes |
| Token management UI (list/revoke own tokens) | No consumer; low value today | Product request |
| F-OBS-01 (API session, export audit, memory) | Minor; tracked | Opportunistic |

## 19. Implementation Checklist

(For the future implementation prompt — do not run now.)

- [x] Approve the token lifetime default (recommended `SANCTUM_EXPIRATION=720`, i.e. 12 h) or set an explicit business value.
- [x] `config/sanctum.php`: set `'expiration' => env('SANCTUM_EXPIRATION', 720)`; update the config comment.
- [x] `Api/AuthController.php`: mint via `$user->createToken('api', ['api'])`.
- [x] `routes/console.php`: register `Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily()`.
- [x] Add `tests/Feature/ApiTokenExpirationTest.php` (items 2–5, 9–10 of §15); the `abilities=["api"]` contract is asserted there (`test_login_creates_a_token_with_the_explicit_api_ability`) — no separate `ApiAuthTest` extension was needed.
- [x] Confirm existing regressions: `ApiAuthorizationTest` (Step 4), `SalesSellerScopingTest` (Step 6), `ApiAuthThrottleTest` (Step 5), `ApiAuthTest` (revocation), `MysqlConcurrencyTest` (MySQL group).
- [x] Verify: `composer test`, `phpunit.mysql.xml`, `pint --test`, `php -l`, `migrate:status`, manual API expiry check; restore dev token count to baseline.
- [x] Document deployment requirement: `* * * * * php artisan schedule:run` (or equivalent scheduler) on the host; note the re-login cadence in the README/API note.
- [x] Flip status to ACTIVE and add §20 Implementation Record (this doc) on approval + implementation commit.

---

## 20. Implementation Record

| Field | Value |
|---|---|
| Implemented on | 2026-09-19 |
| Scope approval | Yes — decisions per §13; scope per §11; exclusions per §12 confirmed |
| Implementation commit | `96f1946` (`feat: harden sanctum token lifetime and cleanup`) |

### What shipped

1. **`config/sanctum.php`** — `'expiration' => env('SANCTUM_EXPIRATION', 720)` (720 minutes = 12 h). Comment documents the retroactive effect: validity derives from `created_at`, so previously issued tokens (including `expires_at = NULL`) fall under the configured window without any backfill. All other config keys unchanged.
2. **`app/Http/Controllers/Api/AuthController.php`** — token mint changed to `$user->createToken('api', ['api'])`. Abilities are explicit (`['api']`) rather than the Sanctum default `['*']`. No ability middleware or endpoint-level ability checks were added (per scope).
3. **`routes/console.php`** — `Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily()`. `schedule:list` shows `0 0 * * * php artisan sanctum:prune-expired --hours=24`.
4. **`tests/Feature/ApiTokenExpirationTest.php`** — 9 tests (62 assertions), all deterministic via `Carbon::setTestNow()` (no `sleep()`), reset in `tearDown()`:
   - Login mints a token whose abilities are exactly `["api"]` (hash + id match asserted via `PersonalAccessToken`).
   - Fresh token → `/api/me` 200 and `/api/dashboard` 200.
   - Token minted then advanced 10 min into the 720-min window remains valid.
   - Token whose `created_at` is `expiration + 1` minute old → 401 on `/api/me` and `/api/dashboard` (Guard's retroactive `created_at` window).
   - Logout revokes the current token (DB count → 0) and follow-up request → 401.
   - Role authorization intact: vendedor 403 vs admin 200 on `/api/reports` and `/api/settings`.
   - Commercial-data secrecy intact: vendedor lacks `production_cost`/`stock_value`, admin sees them.
   - Seller scoping intact: vendedor sees only own sales (other seller 404), encargado sees all.
   - Pruning removes both an `expires_at`-past token and a config-window-aged token, keeping the fresh one (`sanctum:prune-expired --hours=24`).
5. **No database migrations** — `expires_at` already exists (nullable + indexed). `migrate:status` unchanged (all Ran, no pending).

### Test & verification results

| Check | Result |
|---|---|
| `composer test` (PHPUnit SQLite) | 258/258 passed, 1,250 assertions |
| `vendor\bin\phpunit -c phpunit.mysql.xml` (MySQL group) | 3/3 passed, 21 assertions |
| `vendor\bin\pint --test` | passed |
| `php -l` on all changed PHP files | all clean |
| `php artisan migrate:status` | unchanged, all Ran, none pending |
| `php artisan schedule:list` | `0 0 * * * sanctum:prune-expired --hours=24` registered |
| Dev SQLite DB before/after | sales=180, sale_items=450, products=37; subtotal 131,404.99 / total 147,173.59 / tax 15,768.60 (identical) |
| `personal_access_tokens` before/after | 0 / 0 (no test or manual tokens left) |

### Deployment requirements

- The host scheduler **must** invoke `php artisan schedule:run` every minute (e.g. `* * * * * php artisan schedule:run`), otherwise expired tokens are never pruned (Laravel's scheduler drives the daily `sanctum:prune-expired`).
- Token expiry itself is enforced at request time by the Sanctum guard (config-driven), so expiry works even before the scheduler ships — only the cleanup of stale rows requires it.
- `SANCTUM_EXPIRATION` env var is optional; the shipped default is 720 minutes (12 h). The value may be tuned via `.env` on any host without code changes.
- Skill: POS/PDA/third-party clients must re-login every ≤ 12 h. Login is throttled (Step 5) — failures are per credential, not per token age.

### Regression notes

- Step 4 role/commercial-data authorization, Step 5 rate limiting, and Step 6 seller scoping all remain green and authoritative; token expiry is authentication-only and never weakens role middleware.
- `feel-free` note: multi-token assertions in tests require `$this->app['auth']->forgetGuards()` when switching principals (Sanctum's `RequestGuard` caches the resolved user per guard instance). Single-token flows are unaffected.

---

Implementation status: IMPLEMENTED (2026-09-19)

Approval required before implementation: YES (approved)