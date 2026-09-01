# AS-NegocioOS (Laravel)

Fresh Laravel 13.29 skeleton (`laravel/laravel`) on Windows/Laragon. No business logic yet:
single `/` route returning the `welcome` view, default `User` model, stock auth migrations.

## Environment

- PHP 8.5, Composer, Node 24. Windows PowerShell (win32) with Laragon.
- Laravel **Boost is NOT installed** — only the generated bootstrap block is present in
  `AGENTS.md`/`CLAUDE.md`. Install it before making app changes:
  ```powershell
  composer require laravel/boost --dev
  php artisan boost:install
  ```
  (Then read this file again; Boost replaces these instructions.)
- Use `MSYS`-style shell quirks: PowerShell, not bash. Prefer `vendor\bin\...` paths.

## Commands

- Develop: `composer run dev` (or `php artisan serve` + `npm run dev`).
- Tests: `composer test` (runs `config:clear` then `php artisan test`). Test DB is
  in-memory sqlite (`phpunit.xml`). Files under `tests/Unit` and `tests/Feature`.
- Lint/format: `vendor\bin\pint` (Pint 1.x). No `pint.json` — default Laravel preset.
- Frontend: Tailwind 4 via `@tailwindcss/vite` + Vite 8. `npm run build` / `npm run dev`.

## Conventions / gotchas

- DB is SQLite at `database/database.sqlite` (config from `.env`/`.env.example`). Migrations
  under `database/migrations`.
- No custom models/controllers/migrations beyond the skeleton — anything domain-specific
  still needs to be scaffolded.
- Follow standard Laravel conventions (controllers in `app/Http/Controllers`, routes in
  `routes/`); don't reinvent structure.
