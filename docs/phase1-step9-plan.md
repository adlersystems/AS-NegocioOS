# Phase 1 — Step 9 Plan

Status: ACTIVE — IMPLEMENTED

## 1. Executive summary

A fresh, independent audit of sales-write and inventory-write integrity was performed on
`main` @ `f125dd2` (after Steps 1–8). **Steps 1–8 are intact; the audit found no regression
in any of them**, and the full existing suite passes (`269` tests, `1,344` assertions).

The audit re-verified the seven candidate findings from the Step 8 plan (B-1…B-7) against the
**current** code, not their original descriptions. Result:

- **B-1 — CONFIRMED (HIGH).** Cancelling a sale whose product has been soft-deleted silently
  loses the restored stock while still writing an `in` inventory movement, leaving the ledger
  asserting that stock returned when it did not.
- **B-2 — CONFIRMED (HIGH).** Product stock is a directly editable form field written without a
  transaction or a row lock, on both create and update.
- **B-3 — CONFIRMED (HIGH, narrowed).** Sale `update()`/`destroy()` never lock the sale row. The
  stale-*form-resubmit* case is actually safe (the controller re-reads sale items inside the
  transaction), but two genuinely interleaved transactions can read the same item baseline before
  the product lock and apply conflicting deltas.
- **B-4 — CONFIRMED (HIGH).** Soft-deleted products whose `is_active` is still `true` remain
  sellable, because validation reloads with `withTrashed()` and gates only on `is_active`.
- **B-5 — CONFIRMED (MEDIUM).** The configured IVA is cast with `(int)` during sale creation,
  truncating a legitimate decimal rate (e.g. `12.5` → `12`).
- **B-6 — REJECTED.** The create-vs-edit rounding divergence is structurally impossible under the
  current schema (`unit_price` is `decimal(12,2)`, `quantity` is an integer) and could not be
  reproduced.
- **B-7 — MODIFIED (LOW–MEDIUM).** Deletion is an intentional hard cancellation. The sale-level
  audit record, the inventory movements and the invoice numbering all survive; only per-line
  `sale_items` lose their trail (DB cascade fires no Eloquent events).

The single coherent root cause behind the highest-severity findings is that **Step 1 hardened only
two of the stock writers (sale create and manual inventory) and left the cancel path, the product
form and the sale row itself outside that discipline**. This is exactly the "inventory ledger
integrity still has holes" theme recorded in the Step 8 plan.

**Recommended Step 9: “Complete the stock-write hardening — cancel path, product form,
sale-row locking and archived-product guards.”** No schema migration is required.

---

## 2. Current repository state

- Branch `main`, HEAD `f125dd2e5750239950c2e3a1d0b70e2c84981de2`, synchronized with `origin/main`.
- Working tree clean at the start of the audit (the throwaway probe used during the audit has been
  removed; no application file was touched).
- Laravel Framework 13.29.0, PHP 8.5, Sanctum 4.x. SQLite for dev/tests (`phpunit.xml`); MySQL for
  the `mysql` group (`phpunit.mysql.xml`).
- **16 migrations**, all applied. Soft deletes on `products`.
- **23 feature test files, 269 tests, 1,344 assertions — all passing.**
- Web routes include sale store/update/destroy (admin for update/destroy), product
  store/update/destroy (admin/encargado), and manual inventory store (admin/encargado).
- `routes/api.php` exposes **read-only** product/inventory/sale/report endpoints — there is **no
  API write path that mutates stock**.
- Roles: `admin`, `encargado`, `vendedor`. Authorization is route-middleware string based; there is
  no `app/Policies` directory. No `app/Observers`, `app/Jobs`, `app/Events` or `app/Listeners`.
- Scheduler contains only `sanctum:prune-expired --hours=24`. Queued work: none.

---

## 3. Fresh sales/inventory integrity audit

### 3.1 Sale creation (`SaleController::store` → `applyItems`)

| Aspect | Finding |
|---|---|
| Transaction | Whole store is wrapped in `DB::transaction` (`SaleController.php:70`). |
| Product loading | `Product::withTrashed()->whereIn('id', …)->lockForUpdate()` (`:425-429`). |
| Stock validation | Both `StoreSaleRequest::withValidator` and a second in-transaction check in `applyItems` (`:452-459`). |
| Row locking | Products locked `FOR UPDATE`. |
| Movements | One `out` movement per line. |
| Rollback | Verified by `SaleTest::test_store_…` and inventory-failure tests; a movement failure rolls back the whole sale. |
| Tax | `$iva = (int) Setting::get('iva_percentage', 12)` (`:422`) — **B-5 defect**. |
| Snapshots | `tax_rate`, `unit_price`, `cost`, client identity snapshot all written. |
| Seller attribution | Present, with `canOverrideSeller()` guard. |
| Archived products | Accepted if `is_active` is `true`, even when soft-deleted — **B-4 defect**. |

Net: sale creation is otherwise transactional and row-locked, consistent with Step 1.

### 3.2 Sale update (`SaleController::update` → `reconcileItems`)

| Aspect | Finding |
|---|---|
| Transaction | Wrapped in `DB::transaction` (`:124`). |
| Sale row lock | **None** — `$sale->load('items')` reads without `lockForUpdate` (`:125`) — **B-3**. |
| Product lock | `Product::withTrashed()->…->lockForUpdate()` (`:306`), acquired **after** the item snapshot is taken. |
| Delta basis | `$oldByProduct` is derived from the pre-lock snapshot (`:293`). |
| Stock apply | Increment/decrement deltas (`:340-357`), with movements. |
| sale_items replacement | `$sale->items()->delete()` then `createMany` (`:360-387`) — a **mass delete** that fires no Eloquent events. |
| Totals/tax | Recomputed from summed rounded line totals using the stored `tax_rate` (`:389-399`). |

Sequential stale-form resubmission is safe (verified): the second request re-reads the current
items inside its transaction and reconciles correctly (stock `92`, qty `8`, ledger `out 8` — all
consistent). The residual risk is true interleaving, described in §6.

### 3.3 Sale cancellation (`SaleController::destroy`)

Sequence audited: create sale → soft-delete product → cancel sale.

| Artifact | Result |
|---|---|
| Product stock | **NOT restored.** `$item->product?->increment(...)` (`:196`) resolves through the default (non-trashed) `belongsTo`, returns `null`, and the nullsafe call is a silent no-op. Stock stays at the reduced value. |
| inventory_movements | An `in` movement **is still written** (`:198-205`), so the ledger claims the stock returned. |
| Sale | Hard-deleted (`:208`); `Sale` does not use `SoftDeletes`. |
| sale_items | Removed by the DB-level `cascadeOnDelete`; no Eloquent `deleted` events fire. |
| Audit trail | A `deleted` audit row for the `Sale` retains the full monetary snapshot; `created`/`updated` rows survive. Orphaned `SaleItem` audit rows remain. |

Net: **logically inconsistent** — the ledger says stock returned, the product row says it did not,
and the sale that could be used to recompute it is gone. This is B-1.

### 3.4 Totals, tax and snapshots

- `sales.tax_rate` is `decimal(5,2)` and the schema fully supports decimals.
- `UpdateSettingsRequest` accepts `numeric` (decimals allowed, `:28`).
- But creation truncates with `(int)` (`SaleController.php:62`, `:422`), while editing reads the
  stored rate with `(float)` (`:114`, `:391`). A configured `12.5` is stored as `12.00` — B-5.
- Rounding (B-6) is analysed in §5 and §9.

---

## 4. Verification of B-1 through B-7

| ID | Step 8 description | Current status | Evidence |
|---|---|---|---|
| **B-1** | Cancel sale with soft-deleted product silently loses stock and records an `in` movement | **CONFIRMED (HIGH)** | `SaleController.php:196` uses `$item->product?->increment`, `SaleItem::product()` (`app/Models/SaleItem.php:33`) is a plain `belongsTo` that excludes trashed rows → `null`; `:198-205` writes the movement unconditionally. Contrast `reconcileItems` `:301` which correctly uses `withTrashed()`. Audited end-to-end. |
| **B-2** | Product stock written without transaction/lock | **CONFIRMED (HIGH)** | `ProductController.php:56-58` (create) and `:105-110` (update) call `update()`/`create()` then `logInitialStock`/`logAdjustment` with **no** `DB::transaction` and **no** lock. `stock` is fillable and present in `resources/views/products/form.blade.php:26`. Audited: a movement-write failure left `stock = 99` persisted with no rollback and no movement. |
| **B-3** | No sale-row lock on update/cancel | **CONFIRMED (HIGH, narrowed)** | `SaleController.php:124-152` locks products but never the `Sale`; `:188-209` locks nothing. The pre-lock `$oldByProduct` snapshot (`:293`) is the stale-read window. Sequential replay converges correctly; true concurrency needs the MySQL test in §15. |
| **B-4** | Archived products remain sellable | **CONFIRMED (HIGH)** | `StoreSaleRequest.php:27` `exists:products,id` ignores soft-delete; `:57` reloads `withTrashed()`; `:62` gates only on `is_active`. `ProductController::destroy` (`:117-126`) only calls `delete()` and never clears `is_active`. Audited: soft-deleted + `is_active=true` → HTTP 302, sale created, stock decremented. |
| **B-5** | IVA cast to int | **CONFIRMED (MEDIUM)** | `SaleController.php:422` `(int) Setting::get('iva_percentage', 12)` (and `:62`); `UpdateSettingsRequest.php:28` allows decimals; `sales.tax_rate` is `decimal(5,2)`. Audited: setting `12.5` stored `tax_rate = '12.00'`, tax computed on `12`. |
| **B-6** | Create vs update round totals differently | **REJECTED** | `applyItems` accumulates unrounded (`:438`) and `reconcileItems` sums rounded line totals (`:389`), but `unit_price` is `decimal(12,2)` (`sale_items` migration `:19`) and `quantity` is an integer, so every line total is exact to 2 decimals in both paths. Audited with sub-cent line prices: store and edit produced identical subtotal/tax/total. |
| **B-7** | Deleting a sale cascades `sale_items` without a cancellation trail | **MODIFIED (LOW–MEDIUM)** | `sale_items` is `cascadeOnDelete` (`2026_01_01_000040`), so the DB removes lines without Eloquent events. However the `Sale` `deleted` audit row (`RecordsActivity.php:57-59`) preserves the full monetary snapshot, movements are retained, and invoice numbers are not reused. The only real loss is per-line detail. |

---

## 5. Complete inventory mutation map

Every runtime path that can change `products.stock` was located. No API write path mutates stock,
and there are no observers/events/jobs/commands/listeners that touch stock.

| # | Path | Caller | Auth | Transaction | Row lock | Movement row | Rollback | Concurrency |
|---|---|---|---|---|---|---|---|---|
| 1 | Sale create — `applyItems` (`SaleController.php:474`) | `store` | admin/vendedor/encargado | Yes | Product `lockForUpdate` | `out` per line | Full | Safe |
| 2 | Sale update — `reconcileItems` (`:340-357`) | `update` | admin | Yes | Product `lockForUpdate` | `in`/`out` deltas | Full | **Currency hole (B-3)** |
| 3 | Sale cancel — `destroy` (`:196`) | `destroy` | admin | Yes | **None** | `in` per line | **Broken for trashed product (B-1)** | Unsafe (no lock) |
| 4 | Manual inventory — `InventoryController::store` (`:74-76`) | `store` | admin/encargado | Yes | Product `lockForUpdate` | `in`/`out` | Full | Safe |
| 5 | Product create — `ProductController::store` (`:56-58`) | `store` | admin/encargado | **No** | **None** | `in` (initial) | **None (B-2)** | Lost update possible |
| 6 | Product update — `ProductController::update` (`:105-110`) | `update` | admin/encargado | **No** | **None** | `in`/`out` (adjustment) | **None (B-2)** | Lost update possible |
| 7 | `SampleDataSeeder` (`database/seeders/SampleDataSeeder.php:92`) | artisan | n/a | No | None | `in` opening + per-sale `out` | n/a | Dev-only |
| 8 | `ProductFactory` / test setup | tests | n/a | No | None | None | n/a | Test-only |

Read-only writers that must **not** be mistaken for stock writes: `togglePaid`
(`SaleController.php:177-186`) writes only `paid`; API controllers are read-only.

### 5.1 Inventory ledger invariant

The application **intends** the invariant:

```
products.stock == Σ (inventory_movements where type = in)
                - Σ (inventory_movements where type = out)
```

`ProductController::store` writes the initial stock as an `in` movement, and every other runtime
writer pairs its stock change with a movement. The invariant holds for the *code paths* that are
transactional (§5 rows 1, 2, 4) but can be broken by rows 3, 5 and 6.

**Known divergences that are NOT application bugs:**

- The `SampleDataSeeder` deliberately writes the opening `in` movement as `initial + 20`
  (`SampleDataSeeder.php:38`). Every seeded product therefore differs from the ledger by exactly
  `20` (or `21` when sold down). This is demo-data construction, not a runtime defect, and is why a
  naive dev-DB reconciliation shows all products "off by 20".
- Factory-created products in tests set `stock` with no opening movement.

The invariant is therefore **intended but not enforced**, and Step 9 must make rows 3, 5 and 6
honour it.

---

## 6. Concurrency analysis

### 6.1 Existing coverage

`tests/Feature/MysqlConcurrencyTest.php` (group `mysql`) covers:

1. `test_sale_lifecycle_works_on_mysql` — sequential store → update → cancel.
2. `test_oversell_is_rejected_on_mysql` — sequential oversell rejection.
3. `test_lock_for_update_blocks_a_competing_transaction` — two PDO connections; `FOR UPDATE NOWAIT`
   proves the product row lock blocks a competitor.

### 6.2 What is NOT covered

- Two sales **created concurrently** against the same product.
- Sale create **concurrent with a manual inventory movement**.
- Two manual stock edits concurrently.
- **Two admins updating the same sale concurrently** (B-3).
- Sale cancellation concurrent with an update or another cancellation.
- Product archival concurrent with sale creation (B-4).
- Product form edits concurrently (B-2).

### 6.3 The B-3 stale-read window (why it is real)

Inside `update()` the order is: begin transaction → `$sale->load('items')` (no lock) → compute
`$existing` → **then** `reconcileItems()` locks the product rows. Two transactions can both read the
same `$oldByProduct` baseline before either writes. The product lock serialises the increments, but
each transaction applies a delta computed from its own stale baseline, so the second commit can
double-deduct (or double-restore) stock while replacing `sale_items` wholesale. The final
`products.stock` then no longer matches the surviving sale's quantities. Sequential HTTP cannot
reproduce this because the second request observes the first commit — which is exactly why the
existing suite passes and the defect has stayed latent.

### 6.4 Recommended minimum deterministic tests (not implemented in this step)

Add to the MySQL group:

1. Two concurrent admins update the same sale from a known baseline; assert final `stock` equals the
   final surviving `sale_items` quantities (with a fixed test rate).
2. Two concurrent cancellations of the same sale; assert stock is restored exactly once.
3. Two concurrent sale creations against one product with stock for only one; assert no oversell and
   an exact final stock.
4. Archival concurrent with sale creation; assert the archived product is never sold (after B-4).

---

## 7. Remaining findings ranked by priority

Ranking is driven by corruption potential, then concurrency/integrity, then historical correctness,
then operational impact — not by the previous labels.

| Rank | ID | Finding | Corruption class | Severity | Complexity |
|---|---|---|---|---|---|
| 1 | **B-1** | Cancel sale with soft-deleted product: stock lost, ledger falsified, sale gone | Permanent, unrecoverable inventory corruption | **HIGH** | Low |
| 2 | **B-4** | Archived/soft-deleted products remain sellable (also the enabler of B-1) | Integrity breach on retired goods | **HIGH** | Low |
| 3 | **B-2** | Product stock written without transaction or lock | Stock/ledger divergence + lost updates | **HIGH** | Low–Med |
| 4 | **B-3** | No sale-row lock on update/cancel | Concurrent lost update / inconsistent sale_items | **HIGH** | Low |
| 5 | **B-5** | IVA truncated to int on creation | Systematic under-taxing | MEDIUM | Low |
| 6 | B-7 | Sale deletion cascades lines without per-line trail | Auditability gap | LOW–MEDIUM | Med |
| 7 | **B-6** | Create vs edit rounding divergence | — | **REJECTED** | n/a |

No new security/authorization findings were found in this scope; Steps 4, 6 and 7 remain intact.

---

## 8. Recommended Step 9

**Step 9 — Complete the stock-write hardening Step 1 began: cancel path, product form,
sale-row locking and archived-product guards.**

Rationale:

1. **Ranks 1–4 share one root cause.** Step 1 brought `applyItems` and the manual inventory writer
   under transaction + `lockForUpdate`, but left the cancel path, the product form and the sale row
   outside that discipline. Fixing them together closes the whole class instead of one symptom.
2. **B-1 and B-4 are two faces of the same semantics gap** — inconsistent handling of
   soft-deleted products across the cancel path and the sale validator.
3. **B-2 and B-3 are the same write-discipline gap** on the product writer and the sale row.
4. **No migration, no new concept, low risk.** Every fix is a query/locking/validation change in
   existing controllers and FormRequests.
5. **It is the only theme producing permanent, unrecoverable corruption.** B-5 is real but bounded
   and reversible; B-7 is a definitional audit question, not corruption.

B-5 (tax) is deliberately split out as a separate follow-up (§19) because it has a different root
cause (a type cast, not a write path). B-6 is rejected. B-7 is documented but not redesigned.

---

## 9. Exact business invariants

Step 9 must establish and defend:

1. **Ledger invariant.** For every product,
   `stock == Σ in movements − Σ out movements`, including when the product is soft-deleted.
2. **Cancellation invariant.** Cancelling a sale restores exactly the quantity sold, on the *same*
   product row, even if that product has since been archived, and records the matching `in`
   movement.
3. **Atomicity invariant.** A stock change and its corresponding movement row commit together or
   not at all.
4. **Mutual-exclusion invariant.** Concurrent writers to the same product cannot lose an update;
   concurrent writers to the same sale operate on a consistent baseline.
5. **Lifecycle invariant.** A soft-deleted (archived) product may remain on existing sale lines but
   may **not** be added to a new sale or as a new line on an existing sale.
6. **Preserved invariants (Steps 1–8).** Historical monetary snapshots, client identity snapshots,
   seller attribution, seller sales/client scoping, API authorization and audit logging must remain
   unchanged.

---

## 10. Proposed scope

### In scope

- **B-1 — cancellation.** Restore stock for archived products and pair every restoration with its
  movement inside the transaction (load products via `withTrashed()->lockForUpdate()` in
  `destroy`, mirroring `reconcileItems`).
- **B-2 — product form.** Wrap `ProductController::store` and `update` in `DB::transaction`, lock
  the product row, and write the movement inside the same transaction so stock and ledger cannot
  diverge; ensure a movement failure rolls back the stock change.
- **B-3 — sale row.** Take a `lockForUpdate()` on the `Sale` row at the start of the `update()` and
  `destroy()` transactions so concurrent operators serialise on a consistent baseline.
- **B-4 — archived products.** In `StoreSaleRequest::withValidator` (and therefore
  `UpdateSaleRequest`), reject soft-deleted products, allowing them only on lines that already
  belong to the sale being edited.
- Focused regression tests for each (see §15).

### Proposed behavior for archived products

- **New sale:** only active, non-deleted products are acceptable.
- **Existing sale edit:** a line whose product is now archived may be **retained or reduced**; the
  product may not be added as a new line and may not be increased (consistent with
  `sellableProducts()`’s documented intent and
  `ProductTest::test_archived_products_still_appear_when_editing_a_sale_that_uses_them`).

---

## 11. Explicit out-of-scope items

- **B-5 (IVA truncation).** Real but a different root cause; deferred to a dedicated tax step (§19).
- **B-6.** Rejected; no action.
- **B-7 / sale cancellation model.** The hard-cancel model is preserved. No soft-deletable sale, no
  schema change, no audit redesign.
- Reporting/export performance, deployment configuration, API pagination, rate limiting,
  authentication, indexes and CHECK constraints — all remain deferred to their own steps.
- Concurrency **implementation**: only the minimum tests in §6.4 are in scope; no new concurrency
  primitives beyond row locks already used by the codebase.

---

## 12. Implementation strategy

1. **Cancel path (B-1).** Rewrite `SaleController::destroy` to load the affecting products with
   `Product::withTrashed()->whereIn(...)->lockForUpdate()`, lock the sale row, restore stock by
   product id, and write one `in` movement per restored line inside the existing transaction.
2. **Product writer (B-2).** Wrap `ProductController::store` and `update` in `DB::transaction`;
   lock the target product row (`lockForUpdate`) before computing the delta; move
   `logInitialStock`/`logAdjustment` inside the transaction.
3. **Sale row lock (B-3).** At the top of the `update()`/`destroy()` transactions, fetch the sale
   with a row lock so all subsequent reads (items, deltas) share one consistent baseline.
4. **Archived guard (B-4).** Load candidate products with `withTrashed()` but reject any product
   that is trashed unless it already exists on the sale being edited; keep the `is_active` check.
5. Keep changes minimal and local: no new services, no new interfaces, no schema changes.

Rollback behaviour: because every mutation is inside a transaction, any movement/validation failure
must return the database to its pre-request state — verified by dedicated tests (§15).

---

## 13. Files/modules expected to change

- `app/Http/Controllers/SaleController.php` — `destroy()`, `update()`, and the shared product
  loading used for restoration.
- `app/Http/Controllers/ProductController.php` — `store()`, `update()`, `logInitialStock()`,
  `logAdjustment()`.
- `app/Http/Requests/StoreSaleRequest.php` — archived-product rejection.
- `app/Http/Requests/UpdateSaleRequest.php` — permitted-to-retain semantics for existing archived
  lines.
- `app/Models/SaleItem.php` — possibly document/centralise the archived-aware product resolution
  used by writers (no behavioural expansion beyond the fix).
- `tests/Feature/SaleTest.php`, `tests/Feature/ProductTest.php`,
  `tests/Feature/SaleHistoricalSnapshotTest.php`, `tests/Feature/MysqlConcurrencyTest.php` — new
  tests.

No new migrations, models, services or configuration.

---

## 14. Database/migration impact

**No migration is recommended.** Every fix is in PHP:

- B-1 — switch the destroy restoration to `withTrashed()`/`lockForUpdate`.
- B-2 — wrap existing writes in a transaction and add `lockForUpdate`.
- B-3 — add `lockForUpdate` on the `Sale` row.
- B-4 — validation only.

The existing indexes and constraints are sufficient. Optional DB-level hardening (CHECK constraints,
extra indexes) is explicitly out of scope and belongs to a separate operational step.

---

## 15. Test strategy

### Feature tests (SQLite)

- **B-1:** create sale → soft-delete the product → cancel → assert stock restored to its pre-sale
  value, an `in` movement exists with the invoice reference, the sale and its lines are gone, and the
  sale-level audit row remains.
- **B-1 rollback:** force the movement write to fail during cancel and assert neither the stock
  restoration nor the deletion persists.
- **B-2:** for both product create and update, force the movement write to fail and assert the stock
  change rolls back (stock and movement agree afterwards).
- **B-2 concurrency (SQLite: behavioural):** assert the product write path runs inside a single
  transaction by failing the movement and checking rollback.
- **B-3 (behavioural):** sequential stale-form resubmission reconciles correctly (locks the
  behaviour in).
- **B-4:** new sale with a soft-deleted product is rejected; an existing archived line is retained
  but a new archived line is rejected; `is_active=false` remains rejected.
- **Regression:** existing snapshot, seller-scoping and client-scoping tests stay green.

### MySQL concurrency tests (`mysql` group)

Add the four minimum tests from §6.4 (two admins on one sale; two cancellations; two concurrent
sales on one product; archival concurrent with sale creation).

### Regression suite

Full SQLite suite must remain green (currently `269/269`, `1,344` assertions), and the `mysql`
group must remain green (`3/3`).

---

## 16. Regression strategy

Verify no regression in Steps 1–8 specifically:

- Sales/inventory transactions, row locking and oversell rejection (Step 1).
- Historical monetary snapshots — `tax_rate`, `unit_price`, `cost` (Steps 2, 3).
- Client identity snapshots and safe client deletion (Steps 3).
- API authorization and commercial data secrecy (Step 4).
- Login throttling (Step 5).
- Seller attribution and seller sale scoping (Step 6).
- Sanctum token lifetime, abilities and pruning (Step 7).
- Seller isolation on client surfaces (Step 8).

Any test added by Step 9 must be additive; existing assertions must not be weakened to make the fix
pass.

---

## 17. Deployment considerations

- No migration means no special deploy ordering.
- No configuration or environment changes.
- The behavior change is user-visible only in two cases: cancelling a sale whose product was
  archived now restores stock, and creating a new sale with an archived product is now rejected.
  Release notes should call both out.
- Existing data remains valid; no backfill is needed because the ledger invariant is repaired going
  forward and historical cancelled sales are already irreversible.

---

## 18. Risks and trade-offs

| Risk | Assessment |
|---|---|
| Foreign-key/soft-delete confusion when restoring archived products | Low — `reconcileItems` already does this correctly and can be mirrored. |
| Adding a sale-row lock increases lock contention | Low — edits/cancels are rare and short; product locks are already taken. |
| Archived-line retention rules could be over- or under-permissive | Low — validate against the existing documented intent and test both retain and reject. |
| Duplicate movements if restoration is refactored incorrectly | Mitigated by explicit rollback and movement-count assertions. |
| B-5 left unaddressed means tax remains truncated until its own step | Accepted and documented explicitly. |

---

## 19. Recommended follow-up steps

1. **Step 10 — Tax precision:** replace the `(int)` IVA casts in `SaleController::create/store` with
   decimal-safe handling, align with `update`/`edit`, and test fractional rates end to end (B-5).
2. **Step 11 — Sale cancellation/audit model:** decide whether hard cancellation should preserve
   line-level detail (soft-delete or append-only cancellation record) (B-7).
3. **Operational hardening:** indexes, CHECK constraints, export bounds/deployment defaults — the
   remaining Step 8 deferred themes.

---

## 20. Approval checklist

- [ ] B-1 confirmed and accepted as HIGH.
- [ ] B-2 confirmed and accepted as HIGH.
- [ ] B-3 confirmed (narrowed to true concurrency) and accepted as HIGH.
- [ ] B-4 confirmed and accepted as HIGH.
- [ ] B-5 acknowledged as deferred, not part of Step 9.
- [ ] B-6 accepted as REJECTED.
- [ ] B-7 acknowledged as MODIFIED and out of scope.
- [ ] Scope limited to B-1, B-2, B-3 and B-4.
- [ ] No migration approved.
- [ ] Test strategy (feature + MySQL concurrency) approved.
- [ ] Regression strategy approved.
- [ ] Step 9 title approved: **Complete the stock-write hardening — cancel path, product form,
      sale-row locking and archived-product guards.**

---

## 21. Implementation record

Executed on `main`, starting from `f125dd2` (Step 8). Scope was limited to B-1, B-2, B-3 and B-4.
No migration was written or applied.

### 21.1 Commits

| Order | Hash | Subject |
|---|---|---|
| 1 | `546d0e173552306fa193d4eeb506e4ab1a08961f` | `fix: harden inventory and sale write integrity` |
| 2 | (this commit) | `docs: record step 9 implementation evidence` |

### 21.2 What changed

- **B-1 — cancellation (`SaleController::destroy`).** Locks the sale row, then loads the affecting
  products with `Product::withTrashed()->orderBy('id')->lockForUpdate()` keyed by id. Stock is
  restored on the same row and paired with an `in` movement before the sale is hard-deleted. If a
  required product row is genuinely absent, the transaction throws a
  `ValidationException` (`app.sales.cancel_product_missing`) so the sale is **preserved** rather
  than silently falsifying the ledger.
- **B-2 — product form (`ProductController::store`/`update`).** Both writes now run inside
  `DB::transaction`; `update()` re-reads the row with `lockForUpdate()` and computes the delta from
  the freshly locked stock. A movement failure rolls the whole stock change back.
- **B-3 — sale row lock (`SaleController::update`/`destroy`).** The sale is fetched with
  `lockForUpdate()->firstOrFail()` as the first statement of the transaction and items are read
  only after the lock; product locks are taken in deterministic `orderBy('id')` order in both
  `reconcileItems` and `applyItems`.
- **B-4 — archived products.** `applyItems` throws `app.sales.product_archived` for any trashed
  product, closing the archival race on new sales. `StoreSaleRequest` gained a `previousQuantity()`
  seam (default `0`); `UpdateSaleRequest` overrides it with the saved line quantity. A trashed
  product is rejected when its quantity would increase beyond the previous quantity, while
  retaining or reducing an existing archived line remains allowed. The `is_active` rejection is
  unchanged.
- New lang keys `app.sales.product_archived` and `app.sales.cancel_product_missing` in `lang/es`
  and `lang/en`.

No new service, interface, model, migration or configuration was introduced.

### 21.3 Verification

| Check | Command | Result |
|---|---|---|
| Full SQLite suite | `composer test` | **279 passed / 279**, 1,397 assertions (was 269 / 1,344) |
| MySQL concurrency group | `vendor\bin\phpunit -c phpunit.mysql.xml` | **7 passed / 7**, 61 assertions (was 3 / 3) |
| Formatting | `vendor\bin\pint --test` | passed |
| Syntax | `php -l` on the 9 changed PHP files | no syntax errors |
| Migrations | `php artisan migrate:status` | 16 migrations, all `Ran`, none pending |

New SQLite tests: B-1 restoration for a soft-deleted product, B-1 rollback on failed restoration,
B-2 rollback for both product store and update, B-4 reject-on-new / retain-or-reduce / reject-increase
/ reject-new-archived-line, inactive rejection, and sequential stale-form reconciliation.

New MySQL tests (group `mysql`): two admins editing one sale serialise on the sale-row lock; two
cancellations restore stock exactly once; competing sales on one unit serialise on the product lock
and reject oversell; archival racing with sale creation serialises and then blocks the sale.

### 21.4 Development database integrity

Read-only metrics were captured immediately before and after the work against the dev MySQL
database (`as_negocios`); they were **identical**, confirming the work touched no dev data:

```
sales 180 · sale_items 450 · products 37 · products_trashed 0 · clients 22
SUM(subtotal) 131404.99 · SUM(total) 147173.59 · SUM(tax_amount) 15768.60
SUM(inventory_movements.quantity) 3732 · inventory_movements 487
personal_access_tokens 0 · audit_logs 0
```

### 21.5 Deviations from the plan

- §13 mentioned possibly documenting/centralising the archived-aware product resolution in
  `SaleItem`; not required — the fix stays in the two controllers and `SaleItem` is unchanged.
- The §15 “B-1 rollback” case is implemented as
  `test_destroy_rolls_back_when_an_archived_product_restoration_fails`, which exercises the
  fail-safe missing-product branch.
- B-5 (tax), B-6 (rejected) and B-7 (cancellation/audit model) remain untouched, as scoped.

---

IMPLEMENTATION STATUS: IMPLEMENTED
APPROVAL REQUIRED: NO
