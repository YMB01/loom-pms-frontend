# Loom PMS — Laravel API

Property management API built with Laravel 11, Sanctum, and MySQL.

## Requirements

- PHP 8.2+
- Composer 2
- MySQL 8 (or compatible)

## Setup

1. **Install dependencies**

   ```bash
   composer install
   ```

2. **Environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Edit `.env` and set at least:

   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `APP_URL` (e.g. `http://localhost:8000`)

   Optional: Sanctum SPA hosts (`SANCTUM_STATEFUL_DOMAINS`), SMS (`AT_*`, `TWILIO_*`), mail, etc.

3. **Database and demo data**

   Create an empty MySQL database (e.g. `CREATE DATABASE loom_pms CHARACTER SET utf8mb4;`) matching `DB_*` in `.env`.

   ```bash
   php artisan migrate:fresh --seed
   ```

   Demo logins (from `LoomPmsDemoSeeder`): `admin@admin.com` / `admin`, `manager@admin.com` / `admin`.

   **Login fails with “Invalid email or password”?** The DB has no demo user yet, or credentials don’t match. Re-run: `php artisan migrate:fresh --seed` (destructive). Ensure the API is on `http://localhost:8000` and the Next.js app uses `NEXT_PUBLIC_API_URL=http://localhost:8000/api` (including `/api`).

   **Smoke-test the API:** with `php artisan serve` running, run `./scripts/test-api-curl.sh`, or run `php artisan test --filter=LocalApiSmokeTest` (uses SQLite in-memory; no MySQL required for the test).

4. **Queue worker** (required for background SMS)

   ```bash
   php artisan queue:work
   ```

5. **Run the application**

   ```bash
   php artisan serve
   ```

   API base URL: `http://localhost:8000/api`

6. **Optimize config (production)**

   ```bash
   php artisan optimize
   ```

## API responses

All `/api/*` routes use JSON. Validation, auth, HTTP, and server errors return a consistent envelope:

`{ "success": false, "data": {}, "message": "..." }`

Successful responses use `{ "success": true, "data": {}, "message": "..." }`.

## CORS

In local development (`APP_ENV=local` or `APP_DEBUG=true`), all origins are allowed. In production, set `CORS_ALLOWED_ORIGINS` in `.env` (comma-separated URLs) and keep `APP_DEBUG=false`.
