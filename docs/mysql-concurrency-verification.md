# MySQL concurrency verification

The SQLite suite (`composer test`) validates functional behavior and
transactional rollback, but in-memory SQLite cannot exercise InnoDB row
locking. This note documents how concurrent inventory protection is
verified against the real production engine (MySQL 8, InnoDB,
`REPEATABLE-READ`).

## Automated coverage (group `mysql`)

`tests/Feature/MysqlConcurrencyTest.php` runs against the dedicated local
database `as_negocios_test` and covers:

1. `test_sale_lifecycle_works_on_mysql` — full create → edit → destroy sale
   cycle on MySQL: stock decrement/restore, sale items, inventory movements
   and totals.
2. `test_oversell_is_rejected_on_mysql` — the request-level stock validation
   rejects overselling and persists nothing.
3. `test_lock_for_update_blocks_a_competing_transaction` — proves that the
   controller's `lockForUpdate()` product fetch acquires an exclusive InnoDB
   row lock: a competing connection issuing `SELECT ... FOR UPDATE NOWAIT`
   on the same row fails while the first transaction holds the lock, and
   succeeds after it commits/rolls back.

Run it (requires the local MySQL server and test DB):

```powershell
vendor\bin\phpunit -c phpunit.mysql.xml
```

The `mysql` group is excluded from the default SQLite suite in `phpunit.xml`.

## Why this is sufficient for the sale/inventory paths

Both sale creation (`applyItems`) and editing (`reconcileItems`) execute
inside `DB::transaction` and fetch every affected product with
`lockForUpdate()` **before** any stock mutation. The sequence inside each
transaction is therefore:

1. Validate the request (snapshot of stock; not authoritative).
2. Begin transaction; `SELECT ... FOR UPDATE` every affected product row.
3. Re-check stock against the **locked** value (after any concurrent
   transaction has committed) and reject with a validation error if
   insufficient.
4. Mutate stock, write `sale_items` and `inventory_movements`, recompute
   totals.
5. Commit; any exception rolls the whole unit back.

Because step 3 re-reads after acquiring the lock, two concurrent requests
cannot both pass the stock check: the second one blocks at step 2 until the
first commits, then sees the decremented stock.

## Remaining manual verification (not automated)

A true test that drives two sale requests in parallel processes/threads is
not practical in the current single-process PHPUnit harness without
introducing substantial infrastructure (parallel PHP processes, channel
synchronization, a dedicated thread-safe connection pool). If desired, this
can be verified on production (or a staging MySQL instance):

1. Create a product with `stock = 1`.
2. Open two browser windows / two `curl` requests on the sale form for that
   product.
3. Submit both requests nearly simultaneously (both must pass the page-level
   stock display of `1`).
4. Expected: exactly **one** sale is created; the second request is rejected
   with the "Stock insuficiente" validation error. Final stock `0`, and a
   single `out` inventory movement exists for the winning sale.

Do **not** run this against real `as_negocios` data — use a throwaway
product and delete it afterwards, or run on a staging copy.