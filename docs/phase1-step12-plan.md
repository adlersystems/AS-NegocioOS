# Phase 1 — Step 12 Plan

Status: ACTIVE — IMPLEMENTED
Author: audit performed on `main` at `d57cc27`
Date: 2026-10-30
Scope type: API pagination-parameter hardening (D-7 only; no migration)

---

## 1. Objective and scope

Harden the API pagination parameters (`per_page`, `limit`) against invalid, negative, zero and
excessively large values on the four controllers that read them from the request:

- `app/Http/Controllers/Api/ProductController.php`
- `app/Http/Controllers/Api/SaleController.php`
- `app/Http/Controllers/Api/InventoryController.php`
- `app/Http/Controllers/Api/ClientController.php`

The goal is a single, consistent rule for page sizes so that no request can produce an unbounded
query or an unexpectedly large response, while keeping the existing response envelope and defaults.

This document records the approved plan and its implementation (see §13 for the implementation
record and verification evidence).

---

## 2. Verified repository baseline

Observed directly (not assumed):

- Branch `main`, working tree **clean**, no uncommitted or staged changes.
- `HEAD = d57cc27` (`docs: record step 11 implementation evidence`); `git status -sb` →
  `## main...origin/main` (synchronized).
- 16 migrations, all `Ran`, none pending (Step 11 end state).
- Recorded test baseline (end of Step 11): SQLite **291 passed / 1,470 assertions**; `mysql` group
  **8 passed / 70 assertions**.
- Recorded dev-database metrics (end of Step 11): sales 180; sale_items 450; products 37;
  products_trashed 0; clients 22; `SUM(subtotal)` 131404.99; `SUM(total)` 147173.59;
  `SUM(tax_amount)` 15768.60; `SUM(inventory_movements.quantity)` 3732; inventory_movements 487;
  personal_access_tokens 0; audit_logs 0.

---

## 3. Findings verified against the current code

### 3.1 How the request values are read

`$request->integer($key, $default)` is `(int) $this->data($key, $default)`
(`vendor/.../Illuminate/Support/Traits/InteractsWithData.php:288-291`). Consequences:

- Parameter **absent** → the supplied default is returned.
- Parameter **present but non-numeric** (e.g. `abc`, empty) → `(int)` yields **0** (the default is
  *not* used, because the key exists).
- Parameter **present and negative** → the negative integer is returned unchanged.
- Only a plain integer string is meaningful; other numeric forms are PHP-cast then treated as an int.

### 3.2 List endpoints (`per_page`, default 10)

| Controller | Line | Current expression |
|---|---|---|
| `Api/ProductController.php` | `:25` | `->paginate($request->integer('per_page', 10))` — no bound |
| `Api/SaleController.php` | `:29` | `->paginate($request->integer('per_page', 10))` — no bound |
| `Api/InventoryController.php` | `:29` | `->paginate($request->integer('per_page', 10))` — no bound |
| `Api/ClientController.php` | `:31` | `->paginate(min($request->integer('per_page', 10), self::MAX_ITEMS))` — upper cap only |

`Api/ClientController.php:19` defines `private const MAX_ITEMS = 100;`.

### 3.3 Nested `limit` endpoints (default 20)

| Controller | Line | Current expression |
|---|---|---|
| `Api/ProductController.php` (`recent_movements`) | `:56` | `->limit($request->integer('limit', 20))` — no bound |
| `Api/ClientController.php` (`sales`) | `:61` | `->limit(min($request->integer('limit', 20), self::MAX_ITEMS))` — upper cap only |

`Api/SaleController::show()` has no `limit` parameter (it returns the sale's own items); it is not a
pagination surface and is out of scope.

### 3.4 Actual framework behavior (the important correction)

Reading the framework source changes the risk model:

- `Query\Builder::limit($value)` only applies the value **when `$value >= 0`**
  (`vendor/.../Illuminate/Database/Query/Builder.php:3229-3238`). A **negative** limit is *silently
  ignored* → the query has **no `LIMIT` at all**.
- `Query\Builder::offset()` clamps to `max(0, ...)` (`:3203-3208`), so a negative page offset is
  neutralized.
- `Query\Builder::forPage($page, $perPage)` = `offset(($page-1)*$perPage)->limit($perPage)`
  (`:3263-3266`).
- `Eloquent\Builder::paginate()` does `$perPage = value($perPage, $total) ?: $this->model->getPerPage();`
  (`vendor/.../Illuminate/Database/Eloquent/Builder.php:1154,1160`). Because `0` is falsy, a
  `paginate(0)` falls back to the model default **15** (`Model::$perPage = 15`,
  `vendor/.../Illuminate/Database/Eloquent/Model.php:121`), **not** zero rows.
- `LengthAwarePaginator` stores `perPage` verbatim (`:60`) and computes
  `lastPage = max(ceil($total / max($perPage, 1)), 1)` (`:61`).

Net current behavior:

| Input | `per_page` (list) today | `limit` (nested) today |
|---|---|---|
| absent | 10 | 20 |
| `0` | `paginate(0)` → **15** | `limit(0)` → **0 rows** |
| `-5` | negative limit ignored → **all rows** | negative limit ignored → **all rows** |
| `100` | 100 | 100 |
| `9999` | products/sales/inventory: **9999** (clients: 100) | products: **9999** (clients: 100) |
| `abc` | `(int)` → 0 → `paginate(0)` → **15** | `(int)` → 0 → **0 rows** |

**Correction of a prior assumption:** the Step 8 audit recorded that `per_page=-1` “compiles to
`LIMIT -1`”. That is not what happens on this framework version: negative values are ignored, so the
result is an *unbounded* result set, not a SQL error and not `LIMIT -1`. The real defects are
(a) **unbounded responses** for negative values, (b) **zero-row** nested results for `limit=0`/non-numeric,
and (c) **oversized responses** for large values on three of the four list endpoints.

### 3.5 Existing conventions and tests

- `Api/Concerns/PaginatesToJson.php` (`:14-22`) already centralizes the `data`/`meta` envelope
  (`current_page`, `per_page`, `total`, `last_page`) but defines **no bounds**. All four controllers
  use this trait, so it is the natural home for a shared clamp helper.
- API list responses are always `{ data, meta }` with HTTP 200; the shape must not change.
- Existing tests that already pin the client cap and must stay green:
  - `tests/Feature/ClientSellerScopingTest.php:274-286` — `per_page=9999` → 100 items / `meta.per_page=100`.
  - `tests/Feature/ClientSellerScopingTest.php:288-300` — `limit=9999` on client detail.
  - `tests/Feature/ApiEndpointsTest.php:51-63` — client index `data`/`meta` structure.
- No existing test exercises `per_page`/`limit` on products, sales or inventory, nor zero/negative
  values on any endpoint.
- Validation convention in this project is Laravel's standard `$request->validate(...)` →
  HTTP **422** with `errors` (e.g. `Api/AuthController.php:20-30`; asserted with
  `assertUnprocessable()` + `assertJsonValidationErrors()` in `tests/Feature/ApiAuthTest.php`).
  Read endpoints currently perform **no** query-parameter validation.

---

## 4. Recommended bounds and policy

### 4.1 Bounds

- **Minimum: 1** — a page size below 1 is meaningless and is the root of the negative/zero defects.
- **Maximum: 100** — reuse the existing `MAX_ITEMS = 100` ceiling already established for clients
  (`Api/ClientController.php:19`; Step 8 “Rule G”). Generalize it to all four controllers.
- **Defaults unchanged:** list `per_page` = **10**; nested `limit` = **20**.

### 4.2 Policy: normalize (clamp), do not reject

Recommended: **clamp every value into `[1, 100]`** (keeping the existing defaults), i.e. the effective
page size is `max(1, min(value, 100))`. Absent parameters still use 10 / 20.

Justification:

1. **Preserves the existing contract.** Clients already *cap* oversized `per_page`/`limit` to 100 and
   return HTTP 200 (`ClientSellerScopingTest.php:274-300`). Rejecting oversized values with 422 would
   break that contract, those tests, and any consumer that already sends large page sizes.
2. **One coherent rule.** Rejecting negatives but clamping oversized values would be a mixed policy
   (“invalid below, accepted above”). A single clamp is easier to document and reason about.
3. **Read-only endpoint.** For a GET list, silently reducing an out-of-range page size to a safe value
   is friendlier and less disruptive than failing the request; the response remains self-describing via
   `meta.per_page`.
4. **Consistent with the existing codebase pattern.** The project already normalizes (`min(..., MAX_ITEMS)`)
   rather than validating these read parameters.

**Alternative considered and rejected:** strict validation
`['per_page' => ['integer','min:1','max:100']]` returning 422. It is explicit, but it (a) changes the
established “cap to 100 / 200 OK” behavior, (b) breaks the two existing cap tests and potentially
existing consumers, and (c) introduces a new error surface on endpoints that never validated reads.
It is noted here so the trade-off is explicit; the recommendation is to clamp.

**Resulting behavior after the change:**

| Input | list `per_page` | nested `limit` |
|---|---|---|
| absent | 10 | 20 |
| `0` | 1 | 1 |
| `-5` | 1 | 1 |
| `100` | 100 | 100 |
| `9999` | 100 | 100 |
| `abc` | 1 | 1 |

(Note: `0`, `abc` and negatives all become **1**; they are treated as “present but unusable”. This is
deliberate and avoids the current 15/0/all-rows inconsistency.)

---

## 5. Files expected to change

| File | Change |
|---|---|
| `app/Http/Controllers/Api/Concerns/PaginatesToJson.php` | Add `MIN_ITEMS = 1`, `MAX_ITEMS = 100`, and a shared clamp helper (e.g. `pageSize(Request $request, string $key, int $default): int`). |
| `app/Http/Controllers/Api/ProductController.php` | `index` (`:25`) and `show` (`:56`) use the helper. |
| `app/Http/Controllers/Api/SaleController.php` | `index` (`:29`) uses the helper. |
| `app/Http/Controllers/Api/InventoryController.php` | `index` (`:29`) uses the helper. |
| `app/Http/Controllers/Api/ClientController.php` | `index` (`:31`) and `show` (`:61`) use the helper; **remove** the now-duplicated `private const MAX_ITEMS = 100;` (`:19`) to avoid a trait/class constant collision. |
| `tests/Feature/ApiPaginationBoundsTest.php` | New regression test file (see §8). |

No routes, models, migrations, config, middleware, Blade views, web controllers, reports or exports
change. No new dependency.

---

## 6. Proposed implementation details

### 6.1 Shared helper in `PaginatesToJson`

```php
use Illuminate\Http\Request;

private const MIN_ITEMS = 1;
private const MAX_ITEMS = 100;

private function pageSize(Request $request, string $key, int $default): int
{
    return max(self::MIN_ITEMS, min($request->integer($key, $default), self::MAX_ITEMS));
}
```

- `$request->integer()` keeps the existing default behavior when the key is absent.
- `max(1, ...)` fixes negative, zero and non-numeric (which casts to 0).
- `min(..., 100)` fixes oversized values.
- The trait is already used by all four controllers, so this centralizes the rule with no new class.

### 6.2 Call sites

- `ProductController::index`: `->paginate($this->pageSize($request, 'per_page', 10))`
- `ProductController::show`: `->limit($this->pageSize($request, 'limit', 20))`
- `SaleController::index`: `->paginate($this->pageSize($request, 'per_page', 10))`
- `InventoryController::index`: `->paginate($this->pageSize($request, 'per_page', 10))`
- `ClientController::index`: `->paginate($this->pageSize($request, 'per_page', 10))`
- `ClientController::show`: `->limit($this->pageSize($request, 'limit', 20))`

### 6.3 `ClientController` cleanup

Remove `private const MAX_ITEMS = 100;` and its docblock; the ceiling now lives in the trait. It is
`private` and referenced only inside that class, so removal is safe.

No other production logic changes. Defaults, ordering, filters and payload mapping are untouched.

---

## 7. API compatibility considerations

- **Envelope unchanged.** Responses remain `{ data, meta }` / `{ data }`; HTTP status stays 200 for all
  requests (no new 422s under the recommended clamp policy).
- **Valid requests unchanged.** Any `per_page`/`limit` in `1..100` (and absent parameters) behave
  exactly as today.
- **Oversized values on products/sales/inventory:** previously returned up to the requested count
  (e.g. `per_page=500` → 500); now capped at **100**. This is the only behavior change for otherwise
  well-formed requests. It aligns these endpoints with the already-documented client ceiling and is
  the intended hardening. Clients (`per_page=9999`) already returned 100, so no change there.
- **Invalid values (negative/zero/non-numeric):** previously produced unbounded results, 15, or 0 rows
  depending on endpoint; now consistently produce **1**. No legitimate consumer should depend on the
  old inconsistent behavior.
- **`meta.per_page`** is now always an integer in `[1, 100]`, which is strictly more predictable for
  consumers that read it.
- No field is renamed or removed; no `meta` key is added or dropped.

---

## 8. Regression test plan

New file: `tests/Feature/ApiPaginationBoundsTest.php` (uses `RefreshDatabase`,
`Sanctum::actingAs(User::factory()->admin()->create())`; deterministic fixture counts — no random
assertions). Endpoints and auth come from `routes/api.php` (`/api/clients`, `/api/products`,
`/api/sales`, `/api/inventory`; inventory is admin/encargado).

### 8.1 List endpoints — `per_page`

For each of `/api/clients`, `/api/products`, `/api/sales`, `/api/inventory`, create enough rows
(e.g. 15) and assert:

| Case | Assertion | Pre-fix expectation |
|---|---|---|
| absent | `meta.per_page === 10` | passes (guard) |
| `per_page=0` | `meta.per_page === 1` | clients 15; others 15 → **fails** |
| `per_page=-5` | `meta.per_page === 1` **and** `count(data) === 1` | all rows → **fails** |
| `per_page=100` | `meta.per_page === 100` | passes (guard) |
| `per_page=9999` | `meta.per_page === 100` and `count(data) <= 100` | clients passes; products/sales/inventory 9999 → **fails** |
| `per_page=abc` | `meta.per_page === 1` | 15 → **fails** |

### 8.2 Nested `limit`

- `/api/products/{id}?limit=...` with several `recent_movements`:
  - absent → count 20 (guard); `0` → count 1; `-5` → count 1 (pre-fix: all); `100` → up to 100;
    `9999` → ≤ 100 (pre-fix: all); `abc` → count 1 (pre-fix: 0).
- `/api/clients/{id}?limit=...` with several sales: same cases (`0` → 1, `-5` → 1, `9999` → ≤ 100,
  `abc` → 1; `100` guard).

### 8.3 Must remain green (not weakened)

- `ClientSellerScopingTest::test_api_client_listing_caps_page_size_at_one_hundred_items`
- `ClientSellerScopingTest::test_api_client_detail_caps_sale_limit_at_one_hundred_items`
- `ApiEndpointsTest::test_clients_index_supports_search_and_pagination_meta`

The new tests must fail against the pre-fix code for the negative/zero/non-numeric and
products/sales/inventory-oversized cases (as marked above), and pass after the fix. Guards document
that defaults and the 100 ceiling are preserved.

---

## 9. Validation commands

Run from the repository root (Windows PowerShell):

```powershell
php -l app\Http\Controllers\Api\Concerns\PaginatesToJson.php
php -l app\Http\Controllers\Api\ProductController.php
php -l app\Http\Controllers\Api\SaleController.php
php -l app\Http\Controllers\Api\InventoryController.php
php -l app\Http\Controllers\Api\ClientController.php
php -l tests\Feature\ApiPaginationBoundsTest.php

vendor\bin\pint --test

composer test                                # expect 291 + new tests, all green
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml   # expect 8/8 unchanged
php artisan migrate:status                   # expect 16 migrations, all Ran, none pending
```

Also re-run the read-only dev-database metrics probe and confirm the values in §2 are **identical**
before and after (no migration, no write path, tests use in-memory SQLite and `as_negocios_test`).

Expected: SQLite **291 + N passed**, MySQL **8 passed / 70 assertions**, Pint clean, `php -l` clean,
16 migrations all `Ran`, dev-DB metrics unchanged.

---

## 10. Risks and mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Capping products/sales/inventory at 100 truncates a consumer that relied on large pages | Medium | Intentional hardening; aligns with the existing client ceiling; documented in the plan/record. Consumers should paginate. |
| Trait constant collides with `ClientController::MAX_ITEMS` | High if missed | Remove the class constant in the same change; verify no other references (it is `private`). |
| Changing zero/non-numeric from 15/0 to 1 surprises a caller | Low | These are invalid inputs; a single predictable value (1) is safer and self-describing via `meta.per_page`. |
| `integer()` truncation of odd numeric strings (`1e3`, `1.9`) | Low | Values are cast then clamped to `[1,100]`; only plain integers are meaningful. |
| Assuming a DB error for negatives | Low | Verified: Laravel ignores negative limits, so the fix must be a PHP clamp (this plan does that); no DB-specific code. |
| Accidentally changing defaults (10/20) | Medium | Guards assert defaults; helper receives the same defaults as today. |
| Test flakiness from random factory counts | Low | Use deterministic counts and assert `meta.per_page` / explicit `count(data)`. |

---

## 11. Acceptance criteria

1. All four list endpoints clamp `per_page` to `[1, 100]` with default 10.
2. Both nested endpoints clamp `limit` to `[1, 100]` with default 20.
3. Negative, zero and non-numeric values never yield an unbounded result or a zero-row nested result;
   they resolve to 1.
4. Values above 100 resolve to 100; values in range are unchanged.
5. Response envelope and HTTP status codes are unchanged (no new 422s).
6. Existing tests remain green; the new regression tests fail pre-fix for the documented cases and
   pass post-fix.
7. No migration, dependency, route, config or write-path change; dev-DB metrics identical before/after.
8. Scope remains limited to D-7.

---

## 12. Explicit exclusions

Not part of this step:

- **D-6** API rate limiting.
- Dependency upgrades (framework / symfony / league advisories).
- Deployment/production-default hardening, seeders, upload validation, logging, docs ops items.
- Chunked exports and report collection pagination (E-1).
- Sargable date filters, indexes, dashboard aggregation, `paid_at`, audit redaction, CHECK constraints.
- Any change to web controllers (they use fixed page sizes, no user input), models, routes, config or
  migrations.
- Reopening Steps 1–11; editing existing tests to weaken assertions.
- Redesigning the API payload or error envelope.

---

## 13. Implementation record

### 13.1 Changes applied

- `app/Http/Controllers/Api/Concerns/PaginatesToJson.php`: added `MIN_ITEMS = 1`,
  `MAX_ITEMS = 100`, `use Illuminate\Http\Request;`, and the shared helper
  `pageSize(Request $request, string $key, int $default): int` returning
  `max(self::MIN_ITEMS, min($request->integer($key, $default), self::MAX_ITEMS))`.
- `ProductController::index` (`:25`) and `ProductController::show` (`:56`) now call the helper.
- `SaleController::index` (`:29`) now calls the helper.
- `InventoryController::index` (`:29`) now calls the helper.
- `ClientController::index` (`:26`) and `ClientController::show` (`:56`) now call the helper, and the
  duplicated `private const MAX_ITEMS = 100;` was removed.
- New `tests/Feature/ApiPaginationBoundsTest.php` (42 tests, data-provider matrix over the four list
  endpoints and both nested endpoints).
- No routes, models, migrations, config, middleware, web controllers, reports or exports changed. No
  dependency added. Defaults (10 / 20), ordering, filters, payload mapping and the `{data, meta}` /
  `{data}` envelope with HTTP 200 are unchanged.

### 13.2 Test-first evidence

- **Pre-fix (red):** `php vendor\phpunit\phpunit\phpunit --filter ApiPaginationBoundsTest` →
  **42 tests, 20 passed, 22 failed, 93 assertions**. The 22 failures were exactly the predicted cases:
  `per_page`/`limit` `0` (4 list + 2 nested), negative (4 + 2), non-numeric (4 + 2), and oversized on
  products/sales/inventory (3 list + 1 nested: product `recent_movements`). The two existing
  client-oversized guards passed pre-fix, as expected.
- **Post-fix (green):** same command → **42 tests, 42 passed, 112 assertions**.

### 13.3 Verification results (actually executed)

| Check | Command | Result |
|---|---|---|
| New regression suite | `php vendor\phpunit\phpunit\phpunit --filter ApiPaginationBoundsTest` | **42 passed / 42**, 112 assertions |
| Guard tests | `php vendor\phpunit\phpunit\phpunit --filter "ClientSellerScopingTest\|ApiEndpointsTest"` | **21 passed / 21**, 180 assertions |
| Full SQLite suite | `composer test` | **333 passed / 333**, 1,582 assertions (was 291 / 1,470) |
| MySQL concurrency group | `php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml` | **8 passed / 8**, 70 assertions (unchanged) |
| Formatting | `vendor\bin\pint --test` | passed |
| Syntax | `php -l` on the 6 changed PHP files | no syntax errors |
| Migrations | `php artisan migrate:status` | 16 migrations, all `Ran`, none pending |

### 13.4 Development database integrity

Read-only metrics captured immediately before and after the work against the dev MySQL database
(`as_negocios`); they were **identical** (tests run against in-memory SQLite and `as_negocios_test`
only):

```
sales 180 · sale_items 450 · products 37 · products_trashed 0 · clients 22
SUM(subtotal) 131404.99 · SUM(total) 147173.59 · SUM(tax_amount) 15768.60
SUM(inventory_movements.quantity) 3732 · inventory_movements 487
personal_access_tokens 0 · audit_logs 0
```

### 13.5 Deviations from the plan

- None. The clamp policy, bounds (1–100), defaults (10 / 20), call sites, trait constant removal and
  test matrix match the approved plan. No existing assertion was weakened or edited; no formatter
  churn beyond the changed lines.

---

## Observed facts vs inferred risks vs unresolved questions

- **Observed facts:** all file/line references, the request-cast behavior, the framework `limit()`
  negative-value behavior, `paginate(0)` fallback to 15, the existing client cap and its tests, the
  422 validation convention, the git baseline and the recorded test/dev-DB metrics were read from the
  current repository in this audit.
- **Inferred risks:** the practical impact (unbounded responses for negative values, oversized
  responses for large values) follows directly from the observed code paths; the consumer-impact
  estimate for >100 page sizes is inferred because external consumers are not visible from this repo.
- **Unresolved questions:** (a) whether to clamp (recommended) or reject with 422 — this plan
  recommends clamping for compatibility; (b) whether the 100 ceiling should be configurable
  (e.g. `config('api.max_page_size')`) rather than a trait constant — out of scope for D-7;
  (c) whether chunked exports (E-1) should follow in a later step.

---

IMPLEMENTATION STATUS: IMPLEMENTED
APPROVAL REQUIRED: NO
