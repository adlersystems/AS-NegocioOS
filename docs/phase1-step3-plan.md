# Phase 1 — Step 3: Historical Client Identity & Safe Client Deletion (Implementation Plan)

Status: ACTIVE (implemented and merged to `main` on 2026-09-16).
Scope: preserve the buyer identity recorded at sale time against later Client edits/deletes, and gate client mutation/export by role.
No soft-delete UI, no API writes for clients, no ledger/tax/currency features.

---

## 1. Current system findings

- `sales` carries money snapshots from Steps 1–2 (`unit_price`, `cost`, `tax_rate`) but **no buyer-identity snapshot**. Client name/NIT are resolved live via `Sale::client()` (`app/Models/Sale.php:31-34`) in every view, PDF, export, report and API payload.
- `sales.client_id` FK is `nullOnDelete` (`database/migrations/2026_01_01_000030_create_sales_table.php:16`). `ClientController::destroy:78-85` hard-deletes with **no sales guard** → deleting a client nulls `client_id` on every historical invoice, which then renders as "Cliente mostrador" (`lang/es/app.php:266`).
- Deleting a client with **unpaid** sales also drops those invoices from receivables (live `whereNotNull('client_id')` at `DashboardController.php:75`; `ReportService::clientsReport` below).
- Client rename retroactively rewrites the displayed identity of all past invoices (no snapshot retained).
- `clients.nit` uniqueness is enforced **only** in FormRequests (`StoreClientRequest.php:23`, `UpdateClientRequest.php:31`); no DB unique index.
- Client write + export routes have **no role gate** (`routes/web.php:103-105`) — the only module whose mutation surfaces are completely unprotected.

## 2. Authorization findings (verified read-only)

### Established authorization model
- Three roles (`User.php:21-31`): `admin` (full), `vendedor` (seller), `encargado` (warehouse/operations).
- Pattern = **route `role:` middleware + defense-in-depth `abort_unless(auth()->user()->canX(), 403)` in controllers**; FormRequests return `authorize() => true`; no Policies/Gates exist (`app/Policies` absent, `AppServiceProvider::boot` empty).
- Permission helpers (`User.php:80-107`):
  - `canWriteProducts()` → **admin, encargado** (product CRUD/stock).
  - `canWriteSales()` → **admin only** (edit/update/delete existing sales).
  - `canSell()` → **admin, vendedor** (create new sale).
  - `canTogglePaid()` → **admin, encargado**.

### What `encargado` can currently do (from `routes/web.php`)
- **Read:** dashboard, sales index/show + per-invoice PDF + sales exports (`:40-45,49-56`), clients index/show (currently every role), products index/show.
- **Write:** products create/edit/update/delete (`:109-120`), inventory movements + inventory exports (`:64-73`), reports + report exports (`:75-82`), toggle sale paid (`:46-47`).
- **Cannot:** create sales (`:51-54` is `admin,vendedor` only; `canSell()` excludes encargado), edit/update/delete sales (admin), settings/audit/users (admin).

### Consistency analysis
- **Delete:** every destructive operation in the app maps to admin/encargado (products) or admin-only (sales). Clients are reference data — **admin, encargado is consistent** (mirrors product authority).
- **Update (rename/core fields):** reference-data maintenance, mirrors products — **admin, encargado is consistent**. Snapshots mean a rename no longer touches history.
- **Create:** keeping it open to **all authenticated roles** is deliberately **inconsistent with the product-mirror** (where create is admin,encargado), but it is consistent with the front-desk/sales workflow: a `vendedor` already holds the more sensitive `canSell()` power and must be able to register a new customer while making a sale. Approved decision.
- **Exports (client lists incl. NIT/phone):** bulk personal-data extraction; restricted to **admin, encargado**. (This diverges from sales exports, which remain open to vendedor — an existing inconsistency deferred to Step 4 secrecy work.)

### Final permission matrix (approved)

| Operation | Current | Granted roles (final) |
|---|---|---|
| client index / show | any auth | **any auth** (unchanged) |
| client create | any auth | **any auth** (preserves front-desk sales workflow) |
| client update | any auth | **admin, encargado** |
| client delete | any auth | **admin, encargado** + hard block if sales exist |
| client PDF export | any auth | **admin, encargado** |
| client Excel export | any auth | **admin, encargado** |

Implementation via a new `User::canManageClients()` helper (admin, encargado) + `abort_unless` in `ClientController` for store/update/destroy/exportPdf/exportExcel, plus route `role:` middleware on the write/export routes (`routes/web.php:103-105`), mirroring the Product/Sale pattern (both layers).

## 3. Remaining risks after Steps 1–2

| # | Risk | Impact |
|---|---|---|
| R1 | Client hard-delete strips identity from every historical sale (invoices become "Cliente mostrador"; client totals under-report; receivables lost if unpaid) | Data integrity — HIGH |
| R2 | Client rename rewrites identity of all past invoices | Data integrity — HIGH |
| R3 | Client delete/update/export open to all roles incl. `vendedor` | Authorization — HIGH |
| R4 | NIT uniqueness not DB-enforced | Consistency — MEDIUM |
| R5 | Sale search can't find recorded client names after rename | Usability/correctness — MEDIUM |

## 4. Proposed Step 3 scope

1. **Schema (migration A)** — `sales.client_name` string nullable after `client_id`; `sales.client_nit` string nullable after `client_name`. `up()` backfills from `clients` for rows where `client_id IS NOT NULL` (documented **reconstruction**); `down()` drops both columns (reversible).
2. **Schema (migration B)** — unique index on `clients.nit` (safe with nullable; MySQL/SQLite allow multiple NULLs); `down()` drops the index.
3. **Fill policy** — add `client_name`, `client_nit` to `Sale::$fillable`; `SaleController::store()` snapshots both from the selected client; `update()` refreshes the snapshot **only when the submitted `client_id` differs** (notes/seller/paid-only and unchanged-client edits never touch it — same guarantee as Step 2).
4. **Rendering fallback** — central `buyerName()` / `buyerNit()` on `Sale` returning snapshot first, live relation as fallback; apply in: `sales/index.blade.php`, `sales/show.blade.php`, `sales/invoice-pdf.blade.php`, `SalesExport`, sales report rows (`ReportController`/`ReportService::salesReport`), `Api/SaleController` (index+show; **additive** `client_name`/`client_nit` keys; keep the `client` object).
5. **Search** — `Sale::scopeSearch` matches snapshot `client_name`/`client_nit` **or** live client (correctly grouped), so renamed clients remain findable by their recorded name.
6. **Safe deletion** — `ClientController::destroy` (a) `abort_unless(canManageClients())` and (b) refuse with a flash error + redirect when `$client->sales()->exists()`; zero-sales clients remain deletable by an authorized role.
7. **Authorization wiring** — per the matrix above: new `User::canManageClients()` (admin, encargado); route `role:` gate on client update/delete/export routes; create and index/show stay open.
8. **API** — stays read-only; only additive snapshot keys on the sales payload.

## 5. Explicitly excluded

- No client soft-delete/archive UI, no `restore` (block-guard chosen; consistent with products having no restore UI).
- No snapshot beyond name/NIT (phone/address are not printed on invoices).
- No API write endpoints for clients.
- **Step 4 (deferred):** API role matrix, cost/margin/seller-data secrecy (incl. sales exports open to vendedor), login throttling, Sanctum token abilities/expiry, web product-page cost exposure to sellers, seller sales scoping.
- **Step 5 (deferred):** user-delete 500 (`sales.seller_id` restrict), inactive-product edit-block (`StoreSaleRequest::withValidator`), soft-deleted products sellable, `Product::scopeSearch` OR-precedence, `tax_rate`-not-fillable gap.
- No payments/accounting/taxes/currencies/versioning/event sourcing/CQRS; no new libraries; no unrelated refactors.

## 6. Database/migration impact

Two additive, reversible migrations (`*_add_client_identity_snapshots_to_sales_table`, `*_add_unique_nit_to_clients_table`). No data loss; dev DB fingerprint (180 sales / 450 items / 37 products; sums 131404.99 / 15768.60 / 147173.59) must remain identical pre/post. Existing `tax_rate`/`cost` snapshot behavior untouched.

## 7. Security/authorization implications

Closes the currently unprotected client mutation/export operations, while intentionally preserving authenticated client creation for the sales workflow. Destructive client operations (update remainder, delete) and client-list exports are restricted to admin, encargado; `destroy` gains a sales-existence hard block as a second guard. No new attack surface; the API gains additive read-only keys only.

## 8. Data-integrity implications

Completes the Steps 1–2 promise: invoice money **and** buyer identity are immutable snapshots. Deletion can no longer sever history or drop receivables; rename no longer rewrites past invoices. Backfilled snapshots are documented reconstructions of the current client master.

Legacy caveat (corrected): if `client_id` was already NULL **before** this migration runs (the client was deleted in a past session), the original client identity **cannot be reconstructed** — those sales keep a NULL snapshot, keep the live-relation fallback (which resolves to nothing), and retain the existing "Cliente mostrador" behavior. Rows with `client_id` set at migration time copy the current name/NIT.

## 9. Test strategy (SQLite feature suite + adjusted existing tests)

- `store` snapshots name/NIT; walk-in sale (null client) → null snapshot.
- Edit: notes-only and same-client edits keep the snapshot byte-identical; changing `client_id` refreshes it.
- Rename client → sales index/show/PDF-labels/export/API still show the recorded name; search finds the old name via snapshot.
- `destroy`: with sales → blocked (client persists, flash error, receivables unchanged); zero-sales → deleted for an authorized role; `vendedor` → 403.
- Role matrix: `vendedor` → 403 on client update/delete/exports, 200 on create and index/show; admin/encargado → allowed on update/delete/exports.
- Backfill test (reconstruction + NULL cases incl. legacy-null-client rows + reversibility).
- NIT unique index present at DB level; duplicate rejected at request layer.
- Update existing expectations: `RoleAccessTest.php:72-91` (seller `clients.index` stays OK), `ClientTest` destroy/export acting roles, any `SaleTest` asserting live client name after a rename.

## 10. Regression risks

- HTML/API assertions on client names after client mutation — audited and aligned to snapshot-fallback.
- `SalesExport` column source swap (snapshot-fallback) — format unchanged, values stable for unchanged clients.
- Dashboard `topClient`/receivables intentionally stay **live** (current-state semantics) — untouched.
- Search change is additive; existing search tests must pass.

## 11. Verification strategy

`composer test` (SQLite), `vendor\bin\phpunit -c phpunit.mysql.xml`, `vendor\bin\pint --test`; dev DB `as_negocios` fingerprint equality pre/post; rollback + re-migrate restores identical state.

## 12. Estimated complexity

Small–Medium. 2 migrations, 1 model (+helper methods), 1 controller, ~6 view/export/API touchpoints, 1 scope method, ~12–15 new tests.

## 13. Why Step 3 (not deferred)

Steps 1–2 made invoice money immutable; the buyer identity is the last remaining thing a later client action can silently rewrite. A single delete or rename currently falsifies every historical invoice's most load-bearing attribution line — the same "silent alteration" class the prior steps eliminated. It is bounded, data-first, and commercially demonstrable (print an old invoice after renaming/removing a client). Broader security work is important but decision-heavy and best kept as Step 4.

## 14. Suggested commit

Single commit: `feat: snapshot client identity for historical integrity`

## 15. Implementation order

1. Migration A (snapshot columns + backfill) → run against dev DB + fresh DB; verify fingerprint + reconstructable rows; rollback/re-migrate.
2. Migration B (NIT unique index).
3. `Sale::$fillable` + `Sale::buyerName()`/`buyerNit()`; snapshot writes in `store()`/`update()`.
4. Rendering fallback in views, invoice PDF, `SalesExport`, sales report rows, API payload.
5. `Sale::scopeSearch` snapshot-or-live.
6. `User::canManageClients()` + `ClientController` guards + route middleware.
7. New tests + adjust affected existing tests.
8. `composer test`, `phpunit.mysql.xml`, Pint; document → ACTIVE; commit & push.

## 16. Implementation evidence (commit hash)

- Commit: `feat: snapshot client identity for historical integrity` (single commit on `main`).
- Full SQLite suite green: **204 passed / 204 (888 assertions)**; MySQL group: 3/3 (21 assertions).
- Pint clean (`vendor\bin\pint --test`); `php -l` clean on all changed files.
- Dev DB `as_negocios` migration/re-migration round-trip verified: 180/180 sales backfilled from their referenced client, walk-in rows (`client_id IS NULL`) left NULL, `clients.nit` unique index physically present, rollback → re-migrate reproduces identical state and fingerprint (180 sales / 450 items / 37 products; sums 131404.99 / 15768.60 / 147173.59).
- New/updated tests: `SaleHistoricalSnapshotTest` (store snapshots name+NIT, walk-in NULL snapshot, same-client notes-only edit preserves snapshot, `client_id` change refreshes snapshot, renamed client renders/ searches by recorded snapshot, identity backfill reconstruction + walk-in NULL, `clients.nit` DB unique index), `ClientTest` (management routes denied for sellers, client with sales cannot be deleted, update/destroy/exports now require admin+encargado), `SaleTest`/`RoleAccessTest` expectations aligned to snapshot-fallback and new permission matrix.