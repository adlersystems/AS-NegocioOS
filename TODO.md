# AS-NegocioOS — Build Tracker

## Verification gates (every module)
1. `vendor\bin\pint` — style clean (repo is not under Git, so no `--dirty`)
2. `composer test` — all tests green
3. `npm run build` — Vite compiles
4. Manual smoke: `php artisan serve` + browser check

> We STOP after every module for review. Nothing moves forward until green.

---

## Milestone A — Foundation (Phases 0–3) ✅ DONE
- [x] npm deps: `alpinejs`, `chart.js` installed
- [x] Composer deps: `maatwebsite/excel` 4.0.2, `barryvdh/laravel-dompdf` 3.1.2
- [x] `resources/css/app.css`: `@custom-variant dark`, semantic color tokens, `x-cloak`
- [x] `resources/js/app.js`: Alpine + Chart.js + toast/theme/language components
- [x] Migrations: users(+role/language/dark_mode), clients, products, sales,
      sale_items, inventory_movements, settings, audit_logs (`migrate:fresh --seed` verified)
- [x] Models: User*, Client, Product, Sale, SaleItem, InventoryMovement, Setting, AuditLog
- [x] Seeders: DatabaseSeeder, SettingsSeeder, UserSeeder, SampleDataSeeder (demo data seeded)
- [x] Factories for all core models (incl. User role states, lowStock/outOfStock)
- [x] Role enforcement via `RoleMiddleware` + `role:` alias (replaces separate CheckAdmin)
- [x] SetLocale middleware (web group) — locale from user/session/cookie
- [x] Manual auth: login / register / logout / password reset controllers + views (no Breeze)
- [x] `routes/web.php`: auth + role-based route groups; `language.switch` public
- [x] i18n files (`lang/es`, `lang/en`) — all views use `__()`; `es` default
- [x] Layouts in `components/layouts/`: `app.blade.php` + `auth.blade.php` (mobile drawer + desktop collapsible sidebar, collapsible via localStorage)
- [x] Blade components: card, button, input, select, textarea, modal, badge, empty-state, toast, icon, label, error
- [x] Toast system (Alpine) reading Laravel flash (`x-data="toast"` in app layout)
- [x] Dark mode: FOUC inline script, 3-way toggle (light/dark/system)
- [x] Language switcher (session + cookie) — every route responds to the chosen locale
- [x] Module skeleton controllers + placeholder pages (client/product/sale/inventory/reports/settings)
- [x] Tests: Auth, Dashboard, RoleAccess, Language (19 passing, 52 assertions)
- [x] **GATE: pint ▸ composer test (19✓) ▸ build ✓ ▸ smoke (/login 200, / 302) — STOP for review**

## Milestone B — Dashboard ✅ DONE
- [x] `DashboardController` with metric queries (counts, KPIs, ARPU, inventory value, alerts)
- [x] Metric cards: clients, products, sales, receivables + KPI row (today/month revenue, ARPU, inventory value)
- [x] Chart.js charts: sales/month (line), revenue trend 12m (bar), sales by seller (doughnut), top products (bar)
- [x] Inventory alerts strip (low stock / out of stock / expiring soon)
- [x] Top client + recent sales list + quick actions
- [x] `MetricCard` (`metric`) Blade component; charts wired via `dashboardCharts` Alpine component
- [x] Audit logging: `RecordsActivity` trait wired into Client, Product, Sale, SaleItem, InventoryMovement, Setting (logs created/updated/deleted when an authenticated user acts)
- [x] Tests: dashboard metrics + AuditLogTest — **26 passing (73 assertions)**
- [x] **GATE: pint ✓ ▸ composer test (26✓) ▸ build ✓ ▸ smoke (/login 200, /dashboard 302) — STOP for review**

## Milestone C — Clientes ✅ DONE
- [x] `ClientController` resource (list/create/show/edit/destroy) + `Store/UpdateClientRequest`
- [x] Purchase history + pending balance on show page (history table, KPIs, profile card)
- [x] Search + pagination (10/page, `latest('id')` for deterministic order, query string preserved)
- [x] Validation rules (unique email/NIT ignored on update) + `ClientTest` (15 tests) + PDF (DomPDF) + Excel (Maatwebsite) exports of the filtered list
- [x] Delete confirmation modal (Alpine) on the list; `x-textarea` fixed to render its slot for edit pre-fill
- [x] Tests: login-guard, role access, CRUD, validations, search, pagination, exports, HTTP audit — **41 passing (123 assertions)**
- [x] **GATE: pint ✓ ▸ composer test (41 ✓) ▸ build ✓ ▸ smoke (/login 200, /clients 200 as guest→login) — STOP for review**

## Milestone D — Productos ✅ DONE
- [x] `ProductController` resource + `Store/UpdateProductRequest` (unique SKU ignored on update)
- [x] Index filters: search (name/SKU) + status (active/inactive) + stock level (low / out / expiring), paginated
- [x] `stock-badge` component: in-stock / low / out / expired / expiring / inactive badges; new `Product::outOfStock()` scope
- [x] Show page: status-alert strip, metrics (price, cost, margin %, stock value, units sold), recent inventory movements
- [x] `ProductTest` (17 tests): gates, role access, filters, CRUD, validations, show, HTTP audit — **58 passing (183 assertions)**
- [x] **GATE: pint ✓ ▸ composer test (58 ✓) ▸ build ✓ ▸ smoke (/login 200, /products 200) — STOP for review**

## Milestone E — Ventas ✅ DONE
- [x] `SaleController` resource (index filters search/seller/from/to, create→store, edit→update, show, destroy)
- [x] Dynamic line-item form (Alpine `saleForm`), autoridad de precio desde `Product::sale_price`, subtotal/IVA/total automáticos, carga previa en edición (restaura stock previo en la validación)
- [x] Stock decrement + inventory movement on sale; update reconcilia por-producto (incrementos→out, reducciones/removidos→in con `in`); destroy restaura stock con reason `Venta anulada`
- [x] `StoreSaleRequest`/`UpdateSaleRequest` con duplicados, inactivos y stock insuficiente en `after()`; invoice-number search (`V-000001`→id) en `Sale::scopeSearch`
- [x] Invoice PDF (DomPDF A4) `sales.invoice.pdf` + list PDF `sales.export.pdf` + Excel `sales.export.excel` (SalesExport)
- [x] `SaleTest` (24 tests): gates, roles, filtros índice, store con IVA 12% y movimientos, validaciones, reconciliation, destroy, show, PDF/Excel, audit — **84 passing (285 assertions)**
- [x] **GATE: pint ✓ ▸ composer test (84 ✓) ▸ build ✓ ▸ smoke (/login, /sales, /sales/create, /products 200; excel 200) — STOP for review**
- [x] **Fix bug #30**: productos con historial ya no fallan al borrar (`FOREIGN KEY constraint failed` por `sale_items.product_id restrictOnDelete`). `Product` usa soft-deletes (`deleted_at` migration + trait); destroy archiva el producto, historial/ventas/movimientos intactos; índice, busca y formulario de ventas lo ocultan. edición de ventas antiguas incluye productos archivados (`withTrashed`) en `sellableProducts()`/requests/reconcile. Tests: 3 nuevos (destroy con historial, `assertSoftDeleted`, edición de venta con producto archivado) + destrucción sin historial ajustada — **86 passing (294 assertions); pint ✓; migrate aplicada a la DB dev**

## ✅ DONE — Rebuild TOAST system from scratch (vanilla JS)
- [x] **Diagnóstico previo**: el sistema OLD (Alpine `x-data="toast"` + `data-flash-*` + `x-transition`) funcionaba en Chrome headless pero el usuario reportaba "no aparecen toasts" — dependencia frágil del ciclo de vida de Alpine. **Decisión: reconstruir desde cero.**
- [x] **Reescrito `toast.js`**: módulo vanilla JS puro (SIN Alpine, SIN `Alpine.data`, SIN `x-data` ni `x-transition`). Lee el flash de un `<script type="application/json" data-toast-initial>` en `DOMContentLoaded`, inyecta toasts como nodos DOM reales con transiciones CSS, auto-dismiss ~4s, botón de cerrar. Expone `window.showToast(type, msg)` como **global de top-level** (siempre definido).
- [x] **Reescrito `components/toast.blade.php`**: contenedor `#toast-region` + `<script data-toast-initial>` con `@json($toastMessages)` (4 keys success/error/warning/info leídas de `session()`). Nota: `@json([...array...])` rompe por el split de comas del directive → se pasa por variable `@php $toastMessages = [...]` + `@json($toastMessages)`. La región se crea lazy por JS (solo cuando hay toast).
- [x] **`resources/js/app.js`**: quité `import toast` como componente Alpine y `Alpine.data('toast', ...)`; ahora `import './toast'` (módulo con side-effect). Alpine sigue para sidebar/theme/dashboard/sales.
- [x] **CSS**: estilos toast propios en `app.css` (`#toast-region`, `.as-toast`, `.as-toast-show`, `.as-toast-leave`, icono, cerrar; top-right con centro en mobile), sin dependencia de Alpine.
- [x] **Botones de exportación** (clients/sales index): `@click` (Alpine) → `onclick` nativo para que funcionen aunque Alpine no esté; `window.showToast` sin cambio.
- [x] **Tests reescritos** (3): `ClientTest`, `SaleTest`, `ProductTest` ya no aseveran `data-flash-success`; ahora aseveran que el JSON en `data-toast-initial` contiene el mensaje esperado (`json_encode` con flags `JSON_HEX_TAG|APOS|AMP|QUOT`) + presencia de `data-toast-initial`. **86 passing / 297 assertions.**
- [x] **GATE: pint ✓ ▸ composer test (86 ✓ / 297 ✓) ▸ build ✓ (app-Qgh3cLIW.js, app-DxLzFSn9.css) ▸ smoke Chrome headless ✓ — flujo real de borrado de cliente (caso reportado): flash `"Cliente eliminado con éxito."` en `data-toast-initial`, toast renderizado y VISIBLE (`as-toast-show`), sin errores de consola; login welcome toast ✓; `window.showToast` ✓; botones export con `onclick` ✓ — STOP for review**

## Milestone F — Inventario
- [ ] `InventoryController`: entries/exits, history, product movements
- [ ] Critical stock alerts UI
- [ ] Tests + export
- [ ] **GATE + STOP for review**

## Milestone G — Reportes
- [ ] `ReportController`: sales, clients, products, inventory, receivable
- [ ] Filters (date range, client, product, seller)
- [ ] PDF + Excel exports
- [ ] Tests
- [ ] **GATE + STOP for review**

## Milestone H — Configuración
- [ ] `SettingController` (edit + update), file upload for logo
- [ ] Settings reflected in invoices, currency formatting, IVA %, email
- [ ] Tests
- [ ] **GATE + STOP for review**

## Milestone I — API + Audit
- [ ] Sanctum install + `routes/api.php` auth endpoints
- [ ] API: dashboard, clients, products, sales, inventory, reports, settings
- [ ] `AuditLog` trait wired into critical models
- [ ] Audit log view + filters
- [ ] Tests for API endpoints + audit records
- [ ] **GATE + STOP for review**

## Final — Full sweep
- [ ] `composer test` full suite
- [ ] `npm run build`
- [ ] Final smoke test of every module + role switching
- [ ] Update README with setup/run instructions