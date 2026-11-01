# Phase 1 — Step 14 Plan

Status: IMPLEMENTATION COMPLETED — PENDING USER REVIEW
Author: read-only audit performed on `main` at `c1df84e`; implementation completed 2026-11-01
Date: 2026-11-01
Revision: plan revised and Option A implemented; no commit or push
Scope type: D-5 SVG upload stored XSS (settings logo only; no migration)
Approval: the user explicitly authorized implementation of the approved Option A plan. Commit, amend and
push remain unauthorized.

---

## 1. Objective, business context and scope

Assess Phase 1 finding **D-5: SVG upload stored XSS** in the editable company-logo feature and record the
bounded remediation that was subsequently authorized and implemented.

The business need is legitimate: an administrator may upload a company logo, which is displayed in the
application shell and on generated invoice PDFs. The security question is whether an uploaded file can
become active content in a browser, especially when it is served from the application's own origin.

### 1.1 In scope

- The settings logo upload form and its validation.
- The stored logo path, public-disk location and URL generation.
- Browser rendering in the settings page and application layout.
- DomPDF rendering through the logo data URI.
- Same-origin serving and relevant response/security-header context.
- Admin-only access to settings and the upload path.
- Existing logo tests and the implemented regression tests.
- Legacy uploaded SVG files and deployment/storage implications.
- Alternatives for rejecting, sanitizing, rasterizing or isolating SVG content.

The authorized implementation and tests are complete; evidence is in §17.

### 1.2 Out of scope

- General upload hardening outside the settings logo.
- Redesign of settings authorization or the admin role.
- API changes; the API settings endpoint is read-only.
- A general CSP overhaul, WAF/CDN deployment, or new infrastructure.
- Unrelated Phase 1 findings.
- Further implementation, test, configuration or migration changes beyond the five authorized files.
- Commits, amendments and pushes. These remain unauthorized.

### 1.3 Security objective

Prevent user-uploaded content from becoming executable same-origin browser content, while preserving the
existing PNG/JPEG/WebP logo workflow and the invoice/layout display behavior.

The user has confirmed that SVG logo upload is **not** a product requirement and selected **Option A:
explicit SVG rejection**. The supported logo formats for the implemented change are PNG, JPEG and WebP.

---

## 2. Verified baseline

Observed directly before this audit:

- Branch `main`; working tree clean; `git status -sb` showed `## main...origin/main`.
- `HEAD = c1df84ee06f8875ac941eaeba4bfffc7ad2e98ab` (`feat: implement API rate limiting`,
  2026-10-31 10:08:30 -0600); local and remote synchronized.
- Step 13 is committed and pushed; its SQLite suite was **340 passed / 1,909 assertions** and its MySQL
  group was **8 passed / 70 assertions**.
- Runtime verified: **PHP 8.5.9 (ZTS, Visual C++ 2022 x64)** and **Laravel Framework 13.29.0**.
  The repository constraints are `^13.17` and `^8.3`; the installed versions are the concrete baseline.
- `php artisan migrate:status`: **16 migrations**, all `Ran`, none pending.
- Application environment observed earlier: `CACHE_STORE=database`, `SESSION_DRIVER=database`,
  `DB_CONNECTION=mysql`, `DB_DATABASE=as_negocios`, `APP_URL=http://localhost:8000`,
  `FILESYSTEM_DISK=local`.
- Test environment: `CACHE_STORE=array`, SQLite in-memory (`phpunit.xml`).
- `docs/phase1-step14-plan.md` did not exist before this document.
- No current uploaded logo: `Setting::logoPath()` and `Setting::logoUrl()` returned `null`;
  `storage/app/public/logos` does not exist; `storage/app/public` contains only `.gitignore`.
- `public/storage` exists as a directory junction to `storage/app/public`.

No Step 14 source, test, configuration, migration, dependency, storage or database change was made.

---

## 3. Current implementation inventory and data flow

### 3.1 Upload path (pre-Step-14 baseline)

This table preserves the pre-Step-14 baseline. The current implemented form and validation values are
documented in §8 and §17.

| Layer | Location | Observed behavior |
|---|---|---|
| Route | `routes/web.php:84-87` | GET/POST settings routes are protected by `role:admin`. |
| Form | `resources/views/settings/edit.blade.php:31-59` | Pre-Step-14 file input `name="logo"`, `accept="image/png,image/jpeg,image/webp,image/svg+xml"`. |
| Request | `app/Http/Requests/UpdateSettingsRequest.php:26` | Pre-Step-14 rule: `['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048']`. |
| Authorization | `UpdateSettingsRequest::authorize()` | Returns `true`; route middleware is the actual admin gate. |
| Controller | `app/Http/Controllers/SettingController.php:20-36` | Deletes the old logo, stores the new file with `->store('logos', 'public')`, then saves settings. |
| Storage | `config/filesystems.php:41-48` | `public` disk root `storage/app/public`, URL `APP_URL/storage`, `visibility=public`. |
| Persistence | `settings` table | Key/value table; logo is stored as a text path. Migration `2026_01_01_000060_create_settings_table.php`. |

The controller does not inspect or sanitize file content. The stored filename is generated by Laravel,
not supplied by the user: `store()` uses `hashName()` with `Str::random(40)` plus the guessed extension
(`vendor/laravel/framework/src/Illuminate/Http/FileHelpers.php:42-54`). A collision is practically
negligible.

Replacement behavior: the old logo is deleted **before** the new file is stored
(`SettingController.php:24-26`). If the new store operation failed after validation, the old logo could
already be gone. That is a replacement/availability concern, not the D-5 XSS vector, and is not required
to remediate D-5.

### 3.2 Display path

| Consumer | Location | Behavior |
|---|---|---|
| Settings preview | `resources/views/settings/edit.blade.php:34-39` | `Setting::logoUrl()` in an `<img src="...">`. |
| Application layout | `resources/views/components/layouts/app.blade.php:18,68-69` | `Setting::logoUrl()` in an `<img src="...">`. |
| Invoice PDF | `resources/views/sales/invoice-pdf.blade.php:34-35` | `Setting::logoDataUri()` in an `<img src="data:...">` rendered by DomPDF. |
| URL helper | `app/Models/Setting.php:100-105` | `Storage::disk('public')->url($path)`. |
| Data URI helper | `app/Models/Setting.php:110-126` | Reads the file, gets its MIME type, and base64-encodes the bytes. |

No JavaScript logo rendering, `<object>`, `<iframe>` or `srcdoc` use of the uploaded logo was found.

### 3.3 Serving context

- The public disk URL is same-origin: `APP_URL/storage/...`.
- `public/storage` is linked to `storage/app/public`, so uploaded files are directly reachable under the
  application's public web root.
- `public/.htaccess` sets a CSP:
  `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ...`.
  The presence of `'unsafe-inline'` means that CSP would not reliably block inline script in a
  same-origin SVG document.
- No `X-Content-Type-Options: nosniff` or upload-specific `Content-Disposition` was found in the
  inspected application/public configuration.
- `config/brand.php:27` and `public/images/logo/*.svg` are **static product assets**, not user uploads.
  A basic search found no `<script`, `onload`, `javascript:` or `<foreignObject>` in those bundled SVGs.
  They are outside the D-5 upload path.

### 3.4 Other upload/serving paths

- The only settings upload path is the logo field above.
- `app/Http/Controllers/Api/SettingController.php` is GET-only; there is no API upload route.
- No other `svg` upload rule was found in `app/`.
- No other direct use of `Setting::logoUrl()` or `Setting::logoDataUri()` was found beyond the three
  consumers listed in §3.2.

---

## 4. Reproduction and verification

### 4.1 Historical finding

The Phase 1 audit recorded D-5 as “SVG accepted” and therefore a stored-XSS risk. That observation must be
reconciled with the current rule set, which contains both `image` and `mimes:...svg`.

### 4.2 Current validation behavior

Laravel 13.29's `image` rule rejects SVG by default. The relevant framework source is
`vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php`:
`validateImage()` (lines 1539-1548) excludes SVG unless `allow_svg` is supplied; `validateMimes()`
(lines 1754-1768) uses the guessed extension.

Read-only verification with an actual `UploadedFile` pointing at the existing bundled
`public/images/logo/logo.svg`:

| Rules | `getMimeType()` | `guessExtension()` | Result |
|---|---|---|---|
| `image` + `mimes:jpg,jpeg,png,webp,svg` + `max:2048` | `image/svg+xml` | `svg` | **fails**: `El campo logo debe ser una imagen.` |
| `mimes:jpg,jpeg,png,webp,svg` only | `image/svg+xml` | `svg` | **passes** |
| `image:allow_svg` + `mimes:jpg,jpeg,png,webp,svg` | `image/svg+xml` | `svg` | **passes** |

Read-only verification with a temporary file containing an SVG script payload but named `logo.png`:

- The real `UploadedFile` was detected as `image/svg+xml` with guessed extension `svg`.
- The current rule set **failed** validation with the same `logo` error.

This demonstrates that current content sniffing detects SVG by content, not merely by the client-supplied
filename. It is a limited validation check, not a full fuzzing or penetration test.

### 4.3 Important test-fake nuance

`UploadedFile::fake()->createWithContent('logo.png', '<svg ...>')` reports `image/png`/`png` because
Laravel's testing `File` derives the reported MIME from the **filename**
(`vendor/laravel/framework/src/Illuminate/Http/Testing/File.php:132-135`). Such a fake can therefore pass
`image` even though the bytes are SVG. Tests must not use that fake alone to prove content-based MIME
rejection. A real temporary file wrapped in an actual `Illuminate\Http\UploadedFile`, or an explicitly
reported MIME, is needed to exercise `finfo`/Symfony MIME detection.

### 4.4 Interpretation

- **New SVG uploads are currently rejected** by the `image` rule, including an SVG payload disguised with
  a `.png` filename in the limited verification above.
- The historical D-5 claim is **not reproducible as a current upload-form exploit** under the installed
  Laravel 13.29 rules.
- D-5 remains a **latent misconfiguration and legacy-file risk**: the rule still lists `svg`, the form
  advertises SVG, the translations advertise SVG, no regression test asserts SVG rejection, and any SVG
  uploaded before this validation behavior (or under a different deployment version) would remain
  publicly reachable.
- Direct navigation to a same-origin SVG document is the meaningful browser-XSS path. SVG loaded through
  an `<img>` element is normally treated as an image and does not execute its scripts in modern browsers;
  the application currently uses `<img>`, not `<object>` or `<iframe>`. The public direct URL and the
  CSP's `'unsafe-inline'` allowance keep the legacy-file risk relevant.

---

## 5. Threat model and exploitability assessment

### 5.1 Actors and access

- Uploading requires an authenticated **admin** account; guests, sellers and managers cannot reach the
  settings POST route.
- An anonymous attacker cannot upload a logo through the current application.
- Relevant actors are therefore a malicious admin, a compromised admin session/account, or an attacker
  who can convince an admin to upload a crafted file. An admin already has broad settings authority, but
  stored XSS could still affect other administrators or persist beyond the compromised session.

### 5.2 Attacker-controlled input

- File bytes, client filename and client-reported MIME type.
- The server determines the stored filename and extension from content sniffing.
- The server does not sanitize SVG content or reject it at the storage layer independently of validation.

### 5.3 Potential impact if an SVG is stored

- Directly visiting the same-origin `/storage/logos/...svg` URL could execute script in the application
  origin if the browser treats the response as an SVG document and CSP does not block it.
- The current CSP permits inline script (`'unsafe-inline'`), so CSP is not a reliable compensating control.
- The application currently renders the logo with `<img>`, which generally does not execute SVG script in
  modern browsers. This reduces the practical impact but does not make a publicly reachable SVG document
  safe.
- The invoice path embeds the file as a data URI for DomPDF. DomPDF does not execute browser JavaScript,
  but it parses SVG server-side; a legacy SVG is still undesirable there and should be covered by the
  legacy-file audit/replacement decision.
- Same-origin access could expose session-bound application actions or data if script executes in a
  privileged browser session.

### 5.4 Current exploitability conclusion

- **Current upload form: mitigated by validation** under Laravel 13.29, subject to the limited
  verification and normal residual parser/MIME risks.
- **Legacy stored SVG: not proven absent in every deployment.** The development checkout has no stored
  logo and no `logos` directory, but production/installations must be audited.
- **Latent risk: real.** Removing `image`, adding `allow_svg`, changing framework behavior, or relying on
  `mimes` alone would reopen SVG acceptance. The current UI and translations actively invite SVG uploads.

---

## 6. Findings

### 6.1 Confirmed by inspection/verification

1. The current logo rule includes `image` and `mimes:...svg`; `image` rejects SVG by default.
2. A real SVG is detected as `image/svg+xml` / `svg`; the current combined rule fails.
3. `mimes:...svg` alone and `image:allow_svg` both accept the same SVG.
4. The form `accept` attribute and both translation files advertise SVG.
5. No test asserts that SVG is rejected; the only logo test uploads a fake PNG.
6. The controller stores to the public disk without sanitization and serves the file under the
   application origin.
7. The layout and settings page use `<img>`; the invoice uses a data URI through DomPDF.
8. The current development database has no logo path and the upload directory is absent/empty.
9. Bundled `public/images/logo/*.svg` are static assets, not user uploads.
10. The current CSP allows inline script; no `nosniff`/upload-specific disposition was found.
11. The upload route is admin-only.
12. No other logo upload or API upload path exists.

### 6.2 Inferred, not proven

- Production installations may contain legacy uploaded SVGs that this checkout does not show.
- A future rule change or framework upgrade could change the effective validation behavior.
- A real attacker may find parser/polyglot edge cases not covered by this limited verification.
- Browser behavior for direct SVG navigation and CSP application can vary by browser/server configuration.

---

## 7. Alternatives and recommendation

### 7.1 Option A — Reject SVG explicitly (selected)

Remove SVG from the accepted formats and make the rejection intentional and tested.

Implemented changes were:

- Keep the existing `image` rule.
- Remove `svg` from `mimes:...` in `UpdateSettingsRequest`; the implemented rule is
  `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`.
- Do not add a separate `mimetypes` rule. `image` and `mimes` already use the content-sniffed guessed
  extension, and the narrowed `mimes` list restricts the result to JPEG/PNG/WebP; an additional
  `mimetypes` list would duplicate that check without a demonstrated security benefit.
- Remove `image/svg+xml` from the form `accept` attribute.
- Remove SVG from the English and Spanish logo descriptions.
- Add regression tests proving SVG (including malicious SVG and SVG content disguised as `.png`) is
  rejected while PNG/JPEG/WebP remain accepted.
- Document the legacy-file audit as a pending per-installation operational task; it was not executed in
  Step 14. Do not automatically delete or modify existing files.

Advantages: no new dependency; minimal code change; preserves current effective behavior; removes the
misleading UI; easy to test; no SVG parser or sanitizer to maintain.

Disadvantages: administrators can no longer upload SVG logos; existing SVG logos need conversion; slight
loss of vector quality. Current validation already rejects SVG, so this mainly makes the behavior explicit
and closes the latent/legacy gap.

### 7.2 Option B — Sanitize SVG and keep support

Add an SVG sanitizer (for example a maintained SVG-sanitizing library), sanitize before storage, and add
tests for scripts, event handlers, external references and malformed SVG. Serving hardening would also be
needed: correct `Content-Type`, `X-Content-Type-Options: nosniff`, and preferably a separate origin or
download disposition for direct access.

Advantages: preserves SVG logos. Disadvantages: new dependency and security-sensitive parser surface;
sanitizers can have bypasses; more tests and maintenance; the application would still serve active-content
formats from its own origin. **Not selected:** the user confirmed that SVG upload is not a product
requirement, so no sanitizer was implemented.

### 7.3 Option C — Rasterize SVG server-side

Convert uploaded SVG to PNG/WebP at upload time using GD/Imagick or a dedicated converter, then store only
the raster result.

Advantages: preserves a vector-input workflow while storing non-executable image formats. Disadvantages:
requires an image-processing dependency/extension, can fail on fonts/external references/complex SVG,
may change visual fidelity, and adds CPU cost. More complex than rejecting SVG. **Not selected:** no
documented business requirement justifies adding rasterization in Step 14.

### 7.4 Option D — Isolate or harden serving

Serve user uploads from a separate origin/domain, force `Content-Disposition: attachment`, or use a
restrictive CSP for the storage path.

Advantages: defense-in-depth if SVG must be accepted. Disadvantages: separate origin is infrastructure
work; `attachment` breaks `<img>` display; a path-specific CSP requires web-server changes and careful
testing. This is not a substitute for rejecting or sanitizing SVG in the current architecture.
**Excluded from Step 14:** no separate-origin serving, global CSP change or additional security
infrastructure was introduced.

### 7.5 Option E — No change

Rely on the current `image` rule and leave `mimes:...svg`, the form `accept`, and the translations as they
are.

Advantages: no work. Disadvantages: misleading UI; no regression test; latent re-enable risk; legacy files
remain unaddressed. **Rejected:** Option A was selected.

### 7.6 Selected approach and resolved decisions

The user has selected **Option A** and resolved the prior open questions:

1. SVG logo upload is **not** a confirmed product requirement. Supported logo formats will be PNG, JPEG
   and WebP. Do not implement SVG sanitization or rasterization unless a future, documented business
   requirement justifies reconsidering them.
2. Existing installations must be audited for stored SVG logos before deployment. Do not automatically
   delete or modify existing files. Document the audit procedure, backup requirements and replacement
   process. A legacy SVG must be replaced with a raster version or explicitly removed after confirming its
   database reference.
3. Keep Step 14 limited to explicit SVG rejection, UI/translation consistency, content-aware regression
   tests and the documented legacy-file audit procedure. Do not introduce separate-origin serving, global
   CSP changes or additional security infrastructure.
4. Do not include the logo replacement-order issue in Step 14. Defer it to a separate improvement.
5. Keep the existing `image` rule and remove SVG from the `mimes` allowlist. Do not add a redundant
   explicit raster `mimetypes` allowlist: the `image` and `mimes` checks already use the content-sniffed
   guessed extension, and the narrowed `mimes` list restricts the result to JPEG/PNG/WebP. A separate
   `mimetypes` rule has no demonstrated additional security benefit here.

The implemented rule is `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`.

---

## 8. Implemented scope and exact files

This section records the implemented scope. It was implemented as approved; validation evidence is in
§17. No controller, model, schema, configuration, dependency or other upload feature was changed.

| File | Implemented change |
|---|---|
| `app/Http/Requests/UpdateSettingsRequest.php` | Removed `svg` from the logo `mimes` list. Kept `image` and `max:2048`. No `mimetypes` rule was added: `image` and `mimes` already use the content-sniffed guessed extension, and the narrowed `mimes` list restricts the result to JPEG/PNG/WebP; the extra rule would be redundant. Implemented rule: `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`. |
| `resources/views/settings/edit.blade.php` | Changed `accept` to `image/png,image/jpeg,image/webp` (removed `image/svg+xml`). |
| `lang/en/app.php` | Changed the logo description to “PNG, JPG or WebP image up to 2 MB.” |
| `lang/es/app.php` | Changed the logo description to “Imagen PNG, JPG o WebP de hasta 2 MB.” |
| `tests/Feature/SettingTest.php` | Added SVG-rejection and valid-raster regression tests; kept the existing PNG upload test. |
| `docs/phase1-step14-plan.md` | Plan, audit record and implementation evidence. |

No change was required in:

- `SettingController` for the rejection approach (validation runs before the controller);
- the `settings` schema or a migration;
- `config/filesystems.php`;
- `app/Models/Setting.php`;
- routes, middleware, API controllers, or dependencies;
- the static `public/images/logo/*.svg` product assets;
- the public CSP or `.htaccess` for the minimal rejection approach.

Legacy-file handling is an operational/deployment step, not a schema migration. The procedure is
documented in §9.1 and remains pending for each installation; it was not executed as part of Step 14. Do
not automatically delete or modify existing files. Any legacy SVG must be replaced with a raster version or
explicitly removed only after its database reference is confirmed and the required backup exists.

SVG sanitization, rasterization and SVG support are not part of this plan. The user confirmed that SVG
upload is not a product requirement.

---

## 9. Database and storage implications

- The `settings` table stores the logo as a text path in the existing `value` column; no schema change is
  needed for rejection.
- Uploads live under `storage/app/public/logos` and are exposed through `public/storage`.
- No automatic legacy-file migration exists. A deployment audit must check the database path and the
  storage directory for `.svg` files.
- Removing a legacy SVG may leave a broken logo reference; the deployment step must either replace it with
  a raster file or clear/update the `logo` setting.
- Back up the settings row and the referenced file before replacement or removal.
- The current development checkout has no logo value and no `logos` directory, so no local legacy-file
  action is required for this checkout.
- No new storage disk, bucket, CDN or separate origin is introduced by the selected option.

### 9.1 Legacy SVG audit procedure (documented; pending per-installation operational task)

**Status: not executed.** This procedure is a pending operational task for each deployment or installation
before rollout. It is intentionally manual and non-destructive. Do not run automated deletion or
modification as part of Step 14.

1. **Prepare and record approval.** Schedule a maintenance/change window, identify the responsible
   operator, and record the installation, date and scope. Do not begin until the backup in step 5 is
   available.
2. **Inventory the database reference.** Run a read-only query:

   ```sql
   SELECT id, `key`, `value` FROM settings WHERE `key` = 'logo';
   ```

   Record the `logo` value. If it is empty or the setting is absent, record “no logo configured.”
3. **Inventory the upload directory.** List files without modifying them:

   ```powershell
   Get-ChildItem -LiteralPath "storage\app\public\logos" -Recurse -File -ErrorAction SilentlyContinue |
       Select-Object FullName, Length, LastWriteTime
   ```

   If the directory does not exist, record “no uploaded logo directory.”
4. **Identify SVG files and references.** Flag files whose extension is `.svg`, whose detected MIME type
   is `image/svg+xml`, or whose path is the value stored in `settings.logo`. Do not open a suspicious file
   in a browser or render it as an image. Record the setting value, resolved path, file size, hash, last
   modified time and whether the file is still referenced.
5. **Back up before any change.** Back up the `settings` row (or the full database) and copy each
   referenced file to a secure location outside the public web root. Record hashes and the restore
   procedure. Keep the backup until the replacement/removal is verified and accepted.
6. **Replace or remove deliberately.**
   - Preferred: replace the SVG with a PNG/WebP logo, update `settings.logo` to the new path, and verify
     the new file is the one displayed.
   - Alternative: clear/update the `settings.logo` reference first, then explicitly remove the legacy SVG
     only after confirming that no live reference remains.
   - Do not delete a file while the database still points to it. Do not bulk-delete files based only on
     extension.
7. **Validate the installation.** Confirm the settings preview, application layout and invoice PDF render
   correctly; confirm no `.svg` remains referenced by `settings.logo`; confirm the legacy file is absent
   from the public path if removal was chosen. Check `public/storage` still resolves to
   `storage/app/public`.
8. **Record the result and retain evidence.** Record “none found,” “replaced,” or “removed,” with the
   backup location, operator, date and validation result. No migration or automatic cleanup is added to
   the application for this procedure.

---

## 10. Compatibility and UX considerations

- PNG, JPEG and WebP uploads are unaffected and are the supported logo formats.
- SVG uploads currently fail validation despite being advertised; after the change they will be rejected
  intentionally and consistently. SVG is not a supported logo format.
- The form label/help text and file picker must match server validation, otherwise administrators will
  continue to choose files that cannot be accepted.
- Existing SVG logos in a live installation need conversion to PNG/WebP or deliberate removal under the
  §9.1 audit procedure; vector quality may be reduced.
- The layout and settings preview use `<img>` and continue to work for raster formats.
- Invoice PDF rendering continues to use the data URI; raster logos are the expected supported format.
- The API settings endpoint remains GET-only and unchanged.
- POS sales, inventory, clients, products and other business workflows are unaffected.
- No change to the admin-only access model was made.
- No separate-origin serving, global CSP change or additional security infrastructure is introduced.

---

## 11. Implementation sequence (code and tests completed; operational checks pending)

The code and test steps below were completed after authorization. Validation evidence is in §17. The
legacy audit and manual browser/PDF checks remain pending and were not executed as part of Step 14.

1. Added regression tests for SVG rejection and valid raster acceptance.
2. Updated the request rule to `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`; no
   redundant `mimetypes` rule was added.
3. Updated the form `accept` attribute and both translation descriptions.
4. Ran the focused settings tests, full SQLite suite, MySQL group, Pint and PHP syntax checks.
5. **Pending manual validation (not performed):** verify the settings form and logo preview with
   PNG/JPEG/WebP, and verify an SVG produces a validation error.
6. **Pending operational task (not executed):** perform the documented §9.1 legacy audit in each
   deployment; do not automatically delete or modify files.
7. Reviewed the diff; explicit authorization is still required before committing or pushing.

The implementation does not include unrelated upload hardening, separate-origin serving, CSP changes,
dependency additions, SVG sanitization/rasterization or schema changes. The logo replacement-order issue
is deferred to a separate improvement.

---

## 12. Test strategy and implemented cases

### 12.1 Existing tests to preserve

- `test_guests_are_redirected_from_settings`
- `test_only_admin_can_access_settings`
- `test_admin_can_update_company_settings_and_they_persist`
- `test_admin_can_upload_a_logo` (fake PNG)
- The remaining settings validation/cache/fractional-IVA tests

No existing assertion should be weakened or removed.

### 12.2 New tests (implemented)

Implemented rule: `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`. No `mimetypes` rule was
added because it would duplicate the content-sniffed allowlist without a demonstrated security benefit.

| Case | Setup | Expected |
|---|---|---|
| SVG rejected | Real `UploadedFile` with a minimal SVG and `image/svg+xml` | `logo` validation error; no logo path stored; existing logo untouched |
| Malicious SVG rejected | Real file containing `<script>`/event-handler SVG | `logo` validation error; no file stored |
| SVG disguised as PNG rejected | Real temporary file with SVG bytes, client name `logo.png`, actual content sniffing | `logo` validation error |
| Valid PNG accepted | Existing fake/real PNG | Upload succeeds; path exists; URL contains `storage/` |
| Valid JPEG accepted | Real/fake JPEG | Upload succeeds |
| Valid WebP accepted | Real/fake WebP, if supported by the test environment | Upload succeeds |
| Invalid non-image rejected | Text file | `logo` validation error |
| Oversized file rejected | Image over 2 MB | `logo` validation error |
| Guest/seller/manager blocked | Existing role tests | Redirect/403; no upload |
| Rejected replacement preserves old logo | Existing logo, then invalid upload | Old path/file remains |

### 12.3 Test implementation note

Do not use `UploadedFile::fake()->createWithContent('logo.png', $svg)` as the only SVG-disguise test.
Laravel's testing fake reports MIME from the filename, so it may pass the `image` rule even though the
bytes are SVG. Use a real temporary file wrapped in `Illuminate\Http\UploadedFile` (test mode) so Symfony
`finfo` MIME detection is exercised, or explicitly report the MIME type.

Because the current `image` rule already rejects SVG, a rejection test may pass before the rule change.
During implementation, sensitivity was proven by a temporary local check that re-enabled SVG
(`image:allow_svg` plus `svg` in `mimes`), running the SVG tests, and restoring the approved rule. The
temporary mutation was not committed. The regression tests fail if SVG acceptance is reintroduced; see
§17.3.

### 12.4 Executed commands

```powershell
php artisan test --filter=SettingTest
php artisan test
php artisan test --configuration=phpunit.mysql.xml
vendor\bin\pint --test
php -l app\Http\Requests\UpdateSettingsRequest.php
```

Result: focused settings tests green; full SQLite suite green on the second run; MySQL group green; Pint
clean; PHP syntax clean. Exact test/assertion counts are recorded in §17.4.

---

## 13. Validation commands and acceptance criteria

### 13.1 Automated validation commands executed

- Focused settings test run.
- Full SQLite test suite.
- MySQL group.
- Pint test mode.
- `php -l` on changed PHP files.

Exact results are recorded in §17.4.

### 13.2 Implementation acceptance criteria (completed during Step 14)

1. The server rejects SVG uploads through the settings form, including SVG content with a raster filename.
2. PNG, JPEG and WebP logos continue to upload and store successfully in automated tests.
3. The form `accept` list and both translations no longer advertise SVG.
4. A regression test proves SVG rejection with content-aware MIME detection.
5. Rejected uploads do not delete or replace an existing valid logo.
6. No new dependency, migration or schema change is introduced by the rejection approach.
7. The admin-only authorization behavior is unchanged.
8. Existing tests remain green and no unrelated behavior changes were introduced.
9. The rule remains `image` + `mimes:jpg,jpeg,png,webp`; no redundant `mimetypes` rule was added without
   a demonstrated security benefit.
10. No commit or push occurs without explicit user authorization.

The former criterion 8 (the §9.1 legacy audit) is an operational requirement, not an implementation
criterion. It was **not** completed during Step 14 and is listed in §13.3.

### 13.3 Operational and manual requirements pending before deployment

- **Per-installation legacy SVG audit:** execute §9.1 for each deployment; no audit has been run as part
  of Step 14, and no file has been automatically deleted or modified.
- **Manual settings preview validation:** not performed. Verify PNG/JPEG/WebP uploads and the logo preview
  in a browser before deployment.
- **Manual invoice PDF validation:** not performed. Verify the logo renders correctly in an invoice PDF
  before deployment.

---

## 14. Risks and mitigations

| Risk | Severity | Mitigation |
|---|---|---|
| Current `image` rule already rejects SVG, creating a false sense that no action is needed | Medium | Make rejection explicit; remove SVG from UI/translations; add regression tests; document legacy audit. |
| Legacy SVG files remain publicly reachable | High if present | Audit settings and storage in each deployment using §9.1; replace or remove deliberately after backup and reference confirmation. |
| Future change removes `image` or adds `allow_svg` | High | Keep SVG out of the allowlist; tests assert rejection; code review checklist. |
| MIME/polyglot parser bypass | Medium | Keep `image` plus the narrowed `mimes:jpg,jpeg,png,webp` allowlist; test disguised SVG; do not claim exhaustive security. |
| UI/server mismatch continues | Medium | Update `accept` and translations with the rule. |
| Admin is the uploader, so a malicious admin can already change settings | Medium | Admin-only exposure limits anonymous attack; stored XSS can still affect other admins/sessions; SVG support is not implemented. |
| Same-origin public serving and CSP with `unsafe-inline` | Medium | Reject SVG; separate-origin serving, global CSP changes and additional security infrastructure are explicitly out of scope for Step 14. |
| Invoice data URI with legacy SVG | Low/Medium | Audit and remove/replace legacy SVG; DomPDF does not execute browser JS, but it still parses the file. |
| Replacing old logo before successful store can lose the old file | Low/Medium | Deferred to a separate improvement; not part of Step 14. |
| Existing SVG users lose upload capability | Low | Communicate the format change; convert existing assets; update help text. |
| Legacy-file removal deletes a referenced file | Medium | Back up; inspect `settings.logo`; replace or clear the setting before deleting. |

---

## 15. Explicit exclusions

Not part of the implemented Step 14 scope:

- Further source, test, configuration or migration changes beyond the five authorized files.
- Adding an SVG sanitizer, rasterizing SVG on upload, or preserving SVG upload support.
- Any new dependency.
- A separate upload origin, CDN, WAF or reverse-proxy rule.
- A general CSP rewrite or global security-header change.
- General upload validation for other features.
- Changes to settings authorization, roles, API, POS, inventory, clients, products or sales.
- Database schema changes or data migrations.
- Automated deletion or modification of legacy files; only the documented, pending §9.1 audit and
  deliberate replacement/removal procedure is approved.
- Fixing the logo replacement-order issue; it is deferred to a separate improvement.
- Committing, amending or pushing anything.
- Reopening Steps 1–13 or unrelated Phase 1 findings.

---

## 16. Observed facts vs assumptions vs resolved decisions

### 16.1 Observed facts at baseline

These facts describe the pre-implementation baseline. The current working-tree state and implemented
changes are recorded in §17.

- `HEAD` is `c1df84e`; the working tree was clean at baseline; local and remote are synchronized.
- Runtime is PHP 8.5.9 and Laravel 13.29.0; 16 migrations are applied.
- The logo rule is `image` + `mimes:...svg` + `max:2048`.
- Laravel 13.29's `image` rule excludes SVG unless `allow_svg`.
- A real SVG is detected as `image/svg+xml`/`svg` and fails the current combined rule.
- A real SVG payload named `logo.png` is detected as SVG and fails the current combined rule in the
  limited verification.
- `mimes:...svg` alone and `image:allow_svg` accept the same SVG.
- The form and translations advertise SVG.
- The controller stores to the public disk without sanitization.
- The layout and settings page use `<img>`; the invoice uses a data URI.
- The development database has no logo and the upload directory is absent.
- The bundled static SVG product assets are not user uploads.
- The upload route is admin-only.
- The public CSP allows inline script; no `nosniff` was found.
- No other logo upload/API upload path was found.

### 16.2 Assumptions and inferences

- Production installations may have legacy SVG files not visible in this checkout.
- Direct navigation to a same-origin SVG document is the main browser-XSS path; `<img>` usage is safer
  but does not make the public file safe.
- Current browser/server behavior for SVG direct navigation and CSP is assumed to follow standard
  behavior; it was not exhaustively tested across browsers.
- The development environment is representative of the application code, not necessarily of every
  deployment's stored data.
- A malicious admin is already privileged; the stored-XSS impact is mainly against other admins,
  sessions and the application origin.

### 16.3 Resolved decisions and remaining review items

The user resolved the prior open questions on 2026-11-01:

1. **SVG is not a confirmed product requirement.** Option A (explicit SVG rejection) is selected.
   Supported formats are PNG, JPEG and WebP. SVG sanitization and rasterization were not implemented.
2. **Legacy installations must be audited, not automatically cleaned.** The procedure, backup
   requirements and replacement process are documented in §9.1. Any legacy SVG must be replaced with a
   raster version or explicitly removed after confirming its database reference.
3. **Scope remains limited** to explicit SVG rejection, UI/translation consistency, content-aware
   regression tests and the documented legacy-file audit procedure. No separate-origin serving, global
   CSP change or additional security infrastructure is introduced.
4. **The logo replacement-order issue is deferred** to a separate improvement and is not part of Step 14.
5. **Keep the existing `image` rule and remove SVG from `mimes`.** The implemented rule is
   `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`. No redundant raster `mimetypes`
   allowlist was added: `image` and `mimes` already use the content-sniffed guessed extension, and the
   narrowed `mimes` list restricts the result to JPEG/PNG/WebP without a demonstrated additional benefit.

No implementation questions remain for the approved rejection approach. Implementation and tests are
complete. The remaining review item is user review of the working-tree changes. Commits, amendments and
pushes remain unauthorized.

---

## 17. Implementation evidence (2026-11-01)

Status: IMPLEMENTATION COMPLETED — PENDING USER REVIEW

### 17.1 Implemented changes

| File | Implemented change |
|---|---|
| `app/Http/Requests/UpdateSettingsRequest.php` | Logo rule changed to `['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']`; `svg` removed; no `mimetypes` rule added. |
| `resources/views/settings/edit.blade.php` | File input `accept` changed to `image/png,image/jpeg,image/webp`. |
| `lang/en/app.php` | Logo description now says PNG, JPG or WebP, up to 2 MB. |
| `lang/es/app.php` | Logo description now says PNG, JPG or WebP, up to 2 MB. |
| `tests/Feature/SettingTest.php` | Added JPEG, WebP, SVG-rejection, malicious-SVG, disguised-SVG and rejected-replacement regression tests; added real-file and payload helpers. |
| `docs/phase1-step14-plan.md` | Implementation evidence and final status updated. |

No controller, model, schema, migration, configuration, dependency or other upload feature was changed.

### 17.2 Tests added

- `test_admin_can_upload_a_jpeg_logo`
- `test_admin_can_upload_a_webp_logo`
- `test_svg_logo_upload_is_rejected`
- `test_svg_logo_upload_with_script_is_rejected`
- `test_svg_content_disguised_as_png_is_rejected`
- `test_rejected_logo_replacement_preserves_the_existing_logo`

The existing PNG upload test and all pre-existing settings tests were preserved.

### 17.3 Content-based MIME detection and sensitivity proof

The SVG tests use a real temporary file (`tempnam`) wrapped in
`new Illuminate\Http\UploadedFile($path, $name, null, null, true)`. They assert actual content detection:
`getMimeType()` is `image/svg+xml`, and the disguised `.png` test also asserts `guessExtension()` is `svg`.
The tests do not rely exclusively on `UploadedFile::fake()->createWithContent()`.

Sensitivity was demonstrated by temporarily changing the rule to `image:allow_svg` with `svg` restored to
`mimes`, then running `php artisan test --filter=test_svg`:

- **3 tests, 0 passed, 3 failed, 7 assertions.**
- Failures were the expected missing `logo` validation errors at `SettingTest.php:123`, `:143` and `:166`.
- The approved rule was immediately restored.
- After restoration, `php artisan test --filter=SettingTest` passed **17 tests / 89 assertions**.
- No temporary mutation remains in the working tree.

### 17.4 Validation results

| Check | Result |
|---|---|
| Focused settings tests | **17 passed / 89 assertions** |
| Full SQLite suite, first run | **346 tests: 345 passed, 1 failed, 1,938 assertions**; the failure was the unrelated `ClientSellerScopingTest::test_vendedor_api_client_detail_returns_only_own_sales` random-data flake (generated NIT contained `900`); targeted rerun passed **1 / 10 assertions** |
| Full SQLite suite, second run | **346 passed / 1,938 assertions** |
| MySQL group | **8 passed / 70 assertions** |
| Pint (`vendor\bin\pint --test`) | **passed** |
| PHP syntax checks | **clean** for `UpdateSettingsRequest.php`, `SettingTest.php`, `lang/en/app.php` and `lang/es/app.php` |
| WebP environment | GD loaded; `imagewebp()` available; `test_admin_can_upload_a_webp_logo` passed |

### 17.5 Legacy files and storage

- No legacy audit was executed against production or any external installation.
- `storage/app/public/logos` remains absent; no uploaded files were deleted or modified.
- The tests use `Storage::fake('public')` and temporary OS files; temporary test files were removed.
- The §9.1 procedure remains a documented, pending per-installation operational task; it was not executed.

### 17.6 Git state

- Branch `main`; `HEAD = c1df84e`; local and remote synchronized.
- Modified: `app/Http/Requests/UpdateSettingsRequest.php`, `resources/views/settings/edit.blade.php`,
  `lang/en/app.php`, `lang/es/app.php`, `tests/Feature/SettingTest.php`.
- Untracked: `docs/phase1-step14-plan.md`.
- `git diff --stat`: 5 files changed, 158 insertions, 10 deletions (the untracked plan is not included).
- Nothing staged; no commit, amend, push or force-push.

### 17.7 Limitations and unresolved issues

- The first full-suite run had one unrelated random-data failure; the targeted test and a second full run
  passed.
- No manual browser validation of the settings logo preview was performed. This check remains pending
  before deployment.
- No manual invoice PDF rendering validation was performed. This check remains pending before deployment.
- Automated feature tests and static inspection covered the changed validation, UI `accept` list and
  translations, but they do not replace the pending manual checks.
- WebP support was confirmed in the actual PHP test environment.
- Commit, amend and push remain unauthorized. Nothing was staged, no commit was created or modified, and
  no push was performed.

---

PLAN DECISION: Option A (explicit SVG rejection) implemented; publication not authorized.
IMPLEMENTATION COMPLETED — PENDING USER REVIEW
No files were staged. No commit was created or modified. No push was performed. Final user review is pending.
