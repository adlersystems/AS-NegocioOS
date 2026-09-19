# Phase 1 — Step 5: Authentication Hardening — Brute-Force Protection (F-AUTH-01)

## 1. Status

ACTIVE — plan approved by the user and implemented (implementation record in §16).

## 2. Scope

**In scope (F-AUTH-01 only).** Add brute-force / login rate limiting to the two credential
authentication endpoints of the application:

- `POST /login` (web, `App\Http\Controllers\Auth\LoginController@login`).
- `POST /api/auth/login` (API, `App\Http\Controllers\Api\AuthController@login`).

This step was delivered as a plan first (read-only) and then **implemented and verified** after the
user's explicit approval (implementation record in §16). No scope was expanded beyond F-AUTH-01.

**Expressly out of scope** (documented, not implemented here, see §14):
- CAPTCHA, MFA/2FA, account lockout (persistent state), password policy/reset rules.
- Sanctum token expiration / abilities.
- Password-reset endpoint throttling (already broker-throttled via `config/auth.php`).
- Session lifetime changes, security headers, audit/logging enhancements.
- Seller attribution, sales export scoping, client PII policy, pagination limits.

## 3. Current Authentication Architecture

- No Fortify or Breeze. Two hand-rolled controllers guard all authentication:
  - Web: `app/Http/Controllers/Auth/LoginController.php` (login at lines 19-38) — views
    `auth.login`; validates `email` + `password`, `Auth::attempt`, regenerates session,
    redirects to `route('dashboard')` on success; throws `ValidationException` on failure.
  - API: `app/Http/Controllers/Api/AuthController.php` (login at lines 13-33) — validates,
    `auth()->attempt`, issues a Sanctum token (`$user->createToken('api')->plainTextToken`).
    The API login uses the default **session** guard (no `auth:sanctum` on this route).
- Routes: `routes/web.php:23-31` places `POST login` (and GET login, forgot/reset ×2) inside the
  `guest` middleware group; `routes/api.php:13` exposes `POST auth/login` under the `api` group.
- Guards: `config/auth.php` defines only the `web` session guard as default.
- Passwords hashed with bcrypt (BCRYPT_ROUNDS=12 in `.env.example`).
- Rate limiting infrastructure exists but is **unused for login**:
  - `app/Providers/AppServiceProvider.php` `boot()` is empty — no `RateLimiter::for(...)` is
    registered anywhere in the project.
  - `bootstrap/app.php` does **not** call `throttleApi()`; therefore the generated `api` group is
    `array_filter([null, SubstituteBindings])` (vendor
    `Illuminate\Foundation\Configuration\Middleware.php:495-498`) — i.e. **no throttle** on the API.
  - `config/auth.php:100` (`'throttle' => 60`) only bounds the **password-reset broker**
    (`Password::sendResetLink` / `ResetPasswordController`); it does not affect login.
  - `auth.throttle` translation strings exist in `lang/en/auth.php` and `lang/es/auth.php`
    (line 7) but are never emitted.

## 4. Current Security Findings

- **F-AUTH-01 (HIGH, the target of this step):** Both login endpoints have **no attempt
  limiting**. An attacker can submit unlimited credential guesses:
  - Web `/login`: unbounded `Auth::attempt` calls; no counter, no 429, no reset policy.
  - API `/api/auth/login`: unbounded attempts returning structured 422 responses that expose
    whether credentials were invalid — trivially scriptable for stuffing/spraying.
- No route-level `throttle:` middleware is applied to `POST login` or `POST api/auth/login`.
- `bootstrap/app.php` configures **no trusted proxies** (`trustProxies` never called), so
  `$request->ip()` is the direct peer IP everywhere today — relevant to keying design (§6, §13).
- Non-credential requests (missing/invalid email/password) fail validation **before** any
  throttling could fire; only well-formed but wrong credentials are guesses worth counting.
- Opacity: the API 422 body (`errors.email = auth.failed`) is the only signal; there is no
  `Retry-After`/`RateLimit-*` header or 429 anywhere in the application today.

## 5. Threat Model

Assumed attacker capabilities: network access to `/` and `/api/auth/login`, ability to script
arbitrary request volumes and to rotate source IPs (botnet / proxies). Defended assets: the
`users` credential set (admin/encargado/vendedor accounts) and the session/token issuance.

Primary attacks against the login endpoints:

| Attack | Pattern | Best counter |
|---|---|---|
| Credential stuffing | Many guesses against **one account** from **one source** | Per (`email\|ip`) rate limit |
| Password spraying | Few guesses (mostly one password) against **many accounts** from one source | Broad per-IP cap (secondary) |
| Account lock-out DoS | Deliberately fail enough times on a target email to block the victim | **Do not** key per-email alone; require `email\|ip` and auto-clear on success |
| Validation-field abuse | Flood malformed payloads to burn resources | Count only validated, wrong-credential attempts (see §6) |

Decisions the design must answer explicitly (all answered in §6/§7):
1. Route-level, action-level, or both? → **Action-level** for the counting logic; no route
   middleware (success/failure both respond 302 on web, so the middleware cannot distinguish and
   would count successful logins; its stored keys are md5-hashed, making success-reset fragile).
2. IP-only? → **Insufficient** (single public IP shared by office/NAT; also trivially evaded by IP
   rotation).
3. Email-only? → **Rejected** (enables easy DoS: attacker locks any account from distributed IPs).
4. Combined `email\|ip`? → **Adopted** as the key (blocks the dominant stuffing pattern while
   allowing legitimate access from any other source).
5. HTTP behavior after excessive attempts → 429 semantics (see §6.2).
6. Successful authentication resets the limiter → **Yes** (via `RateLimiter::clear`).
7. API vs web different limits → Single shared `login` limiter for both (same guard, same
   credentials); differentiation is a one-line follow-up if the future mobile client needs more headroom.

## 6. Proposed Solution

### 6.1 Named rate limiter (single source of truth)

Register in `App\Providers\AppServiceProvider::boot()`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('login', function (Request $request) {
    return Limit::perMinutes(5, 5)->by(
        strtolower((string) $request->string('email')).'|'.$request->ip()
    );
});
```

- **5 attempts per 5 minutes** (decay 300 s) per **combined `email|ip`** key
  (email lowercased; IP from `$request->ip()`).
- This mirrors the official Laravel rate-limiting pattern; the limit is centralized so tuning
  (or adding a separate `login.api` limiter) is a one-line change.

### 6.2 Shared trait `ThrottlesLogins`

Add `app/Http/Controllers/Concerns/ThrottlesLogins.php`. Per the plan-review correction, the
authentication code does **not** retrieve/execute the registered limiter closure
(`RateLimiter::limiter('login')($request)`); instead the key and policy are computed
deterministically from `App\Support\LoginThrottle` — the same constants and key formula the
named limiter uses, so the named limiter remains the single policy definition:

```php
trait ThrottlesLogins
{
    protected function throttleLogin(Request $request): void
    {
        $key = LoginThrottle::key($request);

        if (RateLimiter::tooManyAttempts($key, LoginThrottle::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $seconds])],
            ])->status(429);
        }
    }

    protected function hitLoginThrottle(Request $request): void
    {
        RateLimiter::hit(LoginThrottle::key($request), LoginThrottle::DECAY_SECONDS);
    }

    protected function clearLoginThrottle(Request $request): void
    {
        RateLimiter::clear(LoginThrottle::key($request));
    }
}
```

Flow in both controllers (web `LoginController::login` and API `AuthController::login`):
1. `$this->throttleLogin($request)` — before validating, throw the 429 throttling error if the
   key is exhausted.
2. Validate `email`/`password` (unchanged; malformed payloads do **not** consume attempts).
3. On failed `Auth::attempt` → `$this->hitLoginThrottle($request)` then throw the existing
   `auth.failed` validation error.
4. On success → `$this->clearLoginThrottle($request)` (resets attempts), then unchanged behavior
   (web: session regenerate + redirect; API: token creation + JSON response).

### 6.3 Response behavior after excessive attempts

- **Web** `POST /login`: `ValidationException->status(429)` is rendered by
  `Handler::invalid()` (vendor `Illuminate\Foundation\Exceptions\Handler.php:883-888`) as a
  **redirect back to the login page** with `errors.email = __('auth.throttle')`; the message
  embeds the retry seconds. This is exactly the canonical Laravel trait behavior and reuses the
  existing `auth.throttle` es/en translations and the login form's error rendering. (In this
  Laravel 13 the web path returns 302 with the in-form message; the JSON path honors the 429
  status. No custom 429 page is required.)
- **API** `POST /api/auth/login`: `shouldRenderJsonWhen(api/*)` → `Handler::invalidJson()`
  (lines 897-902) returns **HTTP 429** with `Retry-After`-style guidance in the body:
  ```json
  { "message": "Too many login attempts. Please try again in 123 seconds.",
    "errors": { "email": ["Too many login attempts. Please try again in 123 seconds."] } }
  ```

### 6.4 Storage

`RateLimiter` uses the default cache store, which is `database`
(`config/cache.php:18` → `CACHE_STORE=database` in `.env`/`.env.example`). The `cache` table
migration already exists (`0001_01_01_000001_create_cache_table.php`) — the limiter persists
across requests/workers with **no schema change**. Tests set `CACHE_STORE=array` (`phpunit.xml`),
making throttling deterministic without sleeps. If Redis is introduced later, this works unchanged.

## 7. Why This Design

- **Deterministic and testable**: counters are manipulated via the `RateLimiter` facade with
  explicit `hit`/`tooManyAttempts`/`clear` calls; the array cache store in tests means no
  `sleep()` and no clock manipulation.
- **Counts failures only, resets on success**: an attacker is blocked after 5 consecutive failed
  attempts on an `email|ip` key, while a legitimate user who finally enters the right password is
  instantly cleared and can log in again immediately. Route-level `throttle:login` middleware was
  rejected because it counts *every* request (including successes), cannot distinguish web
  success from failure (both are HTTP 302), and stores keys hashed as
  `md5('login'.$key)` (vendor `ThrottleRequests.php`), which makes success-reset fragile and
  internal-coupling-heavy.
- **Combined `email|ip` key** directly mitigates credential stuffing (the dominant attack) without
  the two lock-in failure modes: per-IP alone bricks an entire office/NAT/proxy source and is
  trivially rotated; per-email alone turns the limiter into an account-lockout DoS vector.
- **One policy for web and API**: both endpoints authenticate the same `users` credentials through
  the same guard, so they share one `login` limiter — uniform, predictable, easy to tune, and
  trivially splittable later.
- **Minimal surface**: pure Laravel-native `RateLimiter`/`Limit`/`ValidationException`; new code is
  one provider registration + one small trait + two controller call sites; reuses existing
  translations and error rendering; zero migrations, zero config changes, zero new dependencies.

## 8. Files Expected to Change

- `app/Providers/AppServiceProvider.php` — register `RateLimiter::for('login', ...)` in `boot()`.
- `app/Http/Controllers/Concerns/ThrottlesLogins.php` — **new** shared trait (§6.2).
- `app/Support/LoginThrottle.php` — **new** policy/key definition shared by the limiter and the
  authentication code (constants + `key()`).
- `app/Http/Controllers/Auth/LoginController.php` — call `throttleLogin`/`hitLoginThrottle`/
  `clearLoginThrottle` in `login()`.
- `app/Http/Controllers/Api/AuthController.php` — same trait calls in `login()`.
- `tests/Feature/LoginThrottleTest.php` — **new** web throttling tests (§11).
- `tests/Feature/ApiAuthThrottleTest.php` — **new** API throttling tests (§11).

No changes to: routes, migrations/schema, `config/*`, `.env*`, views, language files, models.

## 9. Files NOT in Scope

- All of Step 4 (commercial data secrecy / role scoping) — already shipped (`485ef99`).
- Password reset (`ForgotPasswordController`, `ResetPasswordController`, `auth.forgot-password`/
  `auth.reset-password` views) — already broker-throttled at 60/min (`config/auth.php:100`).
- Sanctum configuration (`config/sanctum.php`) — token behavior out of scope.
- `bootstrap/app.php` middleware config — trusted-proxy handling deferred (§14).
- API/export controllers, dashboards, products, sales — no relation to login.
- `routes/*` — no route changes required.

## 10. Database / Migration Impact

**None.** `RateLimiter` persists to the existing `cache` table
(`database/migrations/0001_01_01_000001_create_cache_table.php`); no new table, column, or
migration is required. The MySQL test group must remain green (it is unaffected — no schema
changes, and it runs against SQLite in-memory for the default suite).

## 11. Test Plan

All new tests are deterministic (no `sleep`, no `Carbon::setTestNow` needed — the `array` cache
store in `phpunit.xml` persists counters across requests within a test method and resets between
methods). Keep each test assertion under/over-limit with well-chosen counts.

`tests/Feature/LoginThrottleTest.php` (web):
1. `test_web_login_blocks_after_five_failed_attempts` — 5 wrong-password posts → each returns the
   `auth.failed` session error; the 6th post asserts the session error message is the
   `auth.throttle` message and the user stays a guest.
2. `test_web_login_throttle_message_includes_retry_seconds` — force the throttle and assert the
   session error contains the localized `seconds` placeholder text (stabilize locale, e.g.
   `App::setLocale('en')`, since the app default locale is `es`).
3. `test_web_successful_login_resets_throttle` — 4 failures, then correct credentials → success
   redirect + authenticated; next wrong-password post yields `auth.failed` (not throttled).
4. `test_web_throttle_is_keyed_by_email_and_ip` — failures on email A do not throttle email B from
   the same IP (assert B still gets `auth.failed`, not the throttle message); isolating with
   distinct `REMOTE_ADDR` via `withServerVariables`/request IP override.
5. `test_web_repeated_successful_logins_never_throttle` — 5 correct-password posts in a row all
   succeed (successes never increment).

`tests/Feature/ApiAuthThrottleTest.php` (API):
6. `test_api_login_returns_429_after_five_failed_attempts` — 5 wrong-credential posts → 422 with
   `errors.email`; the 6th asserts **status 429** and `errors.email` = throttle message.
7. `test_api_successful_login_resets_throttle` — mirror of web test (success clears, next failure
   is 422 not 429).
8. `test_api_throttle_is_keyed_by_email_and_ip` — per-email independence; per-IP isolation.
9. `test_api_login_limiter_is_registered` — `RateLimiter::limiter('login')` is a `Closure`.

Regression: existing `tests/Feature/AuthTest.php` and `tests/Feature/ApiAuthTest.php` must remain
green unchanged (well under limits). Sanity: malformed requests (missing email) do not consume
attempts.

## 12. Verification Plan

- `composer test` — full suite (`config:clear` + `php artisan test`): current 217 tests / 953
  assertions + new throttle tests, all green, SQLite in-memory.
- `vendor\bin\pint --test` on changed files (Pint must pass; `pint` will be run non-dry in
  implementation if needed).
- `php -l` on every changed PHP file.
- No migration required → confirm `php artisan migrate:status` unchanged.
- Confirm no `RateLimiter`/`throttle` references appear anywhere else in app code (grep) that
  would indicate a second, unintended policy.
- Empty-`git diff` guarantee for code in this planning step (read-only already honored).

## 13. Risks / Edge Cases

- **Shared/NAT public IP or misconfigured proxy**: without `trustProxies` in `bootstrap/app.php`,
  all clients behind a reverse proxy present the same IP, so `email|ip` keys collapse to `email|
  proxy}` — per-account protection is preserved (the email half still separates), but per-source
  differentiation is lost. Mitigation: rely on the combined key; add `trustProxies` only once a
  known proxy topology exists (§14).
- **Rotating-IP credential stuffing**: an attacker who rotates IPs still gets up to 5 attempts per
  IP per window against an account. Accepted at this scale; a global per-email counter (Redis + a
  DoS trade-off decision) is deferred (§14).
- **Locale**: app default is `es`; throttle tests must stabilize locale or assert against the es
  string (see §11 #2).
- **Malformed floods**: requests that fail validation consume no attempts by design; a resource
  flood of malformed payloads is out of this step's scope (no global request cap today).
- **False positives**: rare for legit users who log in correctly (immediate clear) or use several
  sources; documented 429 guidance (message + seconds) shown in the login form and API body.
- **Web 429 is delivered as a 302 redirect** carrying the message (verified in
  `Handler.php:883-888`); scripts reading HTTP status alone on web won't see 429. Accepted,
  matches Laravel's canonical behavior; a dedicated 429 view is a documented easy follow-up.
- **Concurrency**: DB cache is atomic per key (`add`/`increment` via `DatabaseStore`); no cache
  lock is needed for the counter pattern used here.

## 14. Deferred Findings

Out of scope now; each is an independent decision/PR later:

- **CAPTCHA**, **MFA/2FA**, **account lockout with persistent state** (users table flag /
  `failed_logins` tracking) — business decisions, not technically inseparable from throttling.
- **Password policy** (complexity/rotation/breached-password check) and reset-link rate
  tiers (currently broker-throttled at 60/min).
- **Trusted proxies** (`$middleware->trustProxies(at: [...])` or
  `Request::setTrustedProxies`) — required before any per-IP broad cap is meaningful in
  production behind a proxy.
- **Specialized broad per-IP spraying cap** (e.g., `Limit::perMinute(60)->by($request->ip())` on
  top of the combined key) — blocked on the trusted-proxies decision above.
- **Global per-email counter / distributed limits via Redis** — solves rotating-IP stuffing at
  the cost of an account-lockout DoS surface; needs a deliberate product decision.
- **Sanctum token expiration / abilities**; **session lifetime**; **security headers**; audit
  logging of auth events.
- **Step 4 follow-ups** already documented in `docs/phase1-step4-plan.md` §14.

## 15. Implementation Checklist

- [x] Register `RateLimiter::for('login', ...)` (`Limit::perMinutes(5, 5)`, key
      `strtolower(email)|ip`) in `AppServiceProvider::boot()`.
- [x] Create trait `App\Http\Controllers\Concerns\ThrottlesLogins` with
      `throttleLogin` / `hitLoginThrottle` / `clearLoginThrottle`.
- [x] Wire trait into `Auth\LoginController::login` (throttle → validate → attempt → hit on
      failure, clear on success).
- [x] Wire trait into `Api\AuthController::login` (same flow, issuing Sanctum token on success).
- [x] Add `tests/Feature/LoginThrottleTest.php` (8 tests per the required coverage).
- [x] Add `tests/Feature/ApiAuthThrottleTest.php` (7 tests per the required coverage).
- [x] Run `composer test` (full suite, SQLite in-memory) — all green, no sleeps.
- [x] Run `vendor\bin\pint` on changed files; `php -l` on every changed file.
- [x] Confirm `php artisan migrate:status` unchanged; MySQL test group still green.
- [x] Grep for `RateLimiter|throttle` to confirm exactly one policy in app code (limiter
      registration in `AppServiceProvider` + the trait; the `config/auth.php` reset-broker
      throttle is pre-existing and out of scope).
- [x] Commit (`feat: add brute-force protection to web and api login`) and push — done after
      approval.

## 16. Implementation Record

- **Implementation approach (as approved, with the review correction applied):** the named
  `login` rate limiter is the single policy definition; the authentication code computes the
  limiter key/policy deterministically from `App\Support\LoginThrottle` (key =
  `strtolower(email).'|'.$request->ip()`, `MAX_ATTEMPTS = 5`, `DECAY_MINUTES = 5`) without
  retrieving or executing the registered limiter closure (`RateLimiter::limiter('login')`
  is never called from application code). No route-level `throttle:` middleware is used.
- **Behavior (exact):**
  - 5 failed credential attempts per 5 minutes per `email|ip`; the 6th attempt is blocked.
  - Only failed `Auth::attempt` results are counted (`hit`); successes call `clear`, so a
    successful login resets the counter; malformed validation requests never consume attempts.
  - Web: blocked attempts render the existing validation/error flow — a redirect back to the
    login page carrying `errors.email` = `auth.throttle` (retry seconds embedded), reusing the
    existing es/en translations and the form's error component. Successful login keeps session
    regeneration + redirect to dashboard.
  - API: blocked attempts return HTTP **429** with the existing auth error structure
    (`{"message": ..., "errors": {"email": ["auth.throttle ..."]}}`); success keeps token
    creation + existing JSON response.
  - Same email from a different IP remains independent (not an email-only lockout); same IP
    across different emails remains independent.
- **Files changed:** `app/Providers/AppServiceProvider.php`,
  `app/Http/Controllers/Auth/LoginController.php`, `app/Http/Controllers/Api/AuthController.php`,
  `app/Http/Controllers/Concerns/ThrottlesLogins.php` (new), `app/Support/LoginThrottle.php`
  (new), `tests/Feature/LoginThrottleTest.php` (new, 8 tests),
  `tests/Feature/ApiAuthThrottleTest.php` (new, 7 tests).
- **Tests executed:**
  - `vendor\bin\phpunit tests\Feature\LoginThrottleTest.php` — 8/8 passed, 101 assertions.
  - `vendor\bin\phpunit tests\Feature\ApiAuthThrottleTest.php` — 7/7 passed, 72 assertions.
  - `composer test` (full suite) — **232/232 passed, 1126 assertions** (217 pre-existing + 15 new).
  - `vendor\bin\phpunit -c phpunit.mysql.xml` — **3/3 passed, 21 assertions** (unchanged).
- **Pint:** `vendor\bin\pint` applied formatting (eof newlines, strict-type import), then
  `vendor\bin\pint --test` **passed**.
- **PHP syntax:** `php -l` clean on all 7 changed files.
- **Database/migration impact:** **none** — no migrations added/edited; `php artisan
  migrate:status` unchanged; the limiter persists through the existing `cache` table
  (`0001_01_01_000001_create_cache_table.php`) via the default `database` cache store; tests use
  the `array` store (`phpunit.xml`) so they are deterministic and sleep-free.
- **Commit:** `73840c7 feat: add brute-force protection to web and api login` (implementation;
  this internal docs record is a follow-up docs commit, mirroring the Step 4 pattern of
  `485ef99` + `edffdd1`).
- **Push result:** pushed to `origin main` (see final report).

---

Implementation status: IMPLEMENTED

Approval status: APPROVED (user instruction) and implementation verified