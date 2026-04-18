# Deploying Loom PMS API on Railway

Set the **service root** to this directory (`loom-pms-api`) when connecting the repo, or use a monorepo **Root Directory** / watch path so builds run where `composer.json`, `railway.json`, and `nixpacks.toml` live.

## Config files

| File | Purpose |
|------|--------|
| `railway.json` | Nixpacks builder, start command, health check on `/api/health` |
| `nixpacks.toml` | PHP 8.2 (`php82`), `NIXPACKS_PHP_ROOT_DIR` for Laravel `public/` |

## Start command

Defined in `railway.json` (`deploy.startCommand`):

`php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT`

**Notes**

- **`db:seed --force` runs on every container start.** If your seeders are not safe to re-run, remove seeding from the start command and run it once manually (Railway shell) or use a release phase / one-off job.
- If **`$PORT` is not expanded** (rare), set the start command to:  
  `/bin/sh -c "php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=$PORT"`
- `php artisan serve` is fine for small APIs; for higher traffic, use Nginx + `php-fpm` or Octane (separate setup).

## Environment variables to set in Railway

Set these on the **Laravel API service** (Variables tab). Use **Reference variables** for MySQL (see below).

### Required (app boots)

| Variable | Example / notes |
|----------|-----------------|
| `APP_KEY` | Output of `php artisan key:generate --show` (base64 key) |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<your-railway-app>.up.railway.app` or your custom domain |

### Database (MySQL plugin — use references)

| Variable | Value |
|----------|--------|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |

Alternatively, set **`DB_URL`** to **`${{MySQL.MYSQL_URL}}`** (Laravel reads `url` on the `mysql` connection). Use either discrete `DB_*` variables **or** `DB_URL`, not conflicting duplicates.

### CORS / SPA (Next.js)

| Variable | Notes |
|----------|--------|
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins with scheme, e.g. `https://app.example.com` |
| `FRONTEND_URL` | Primary SPA URL (optional; merged into CORS) |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated hostnames (no scheme), e.g. `app.vercel.app` |

### Recommended for production

| Variable | Notes |
|----------|--------|
| `LOG_LEVEL` | `error` or `warning` |
| `SESSION_DRIVER` | `database` (run migrations so session table exists) or `cookie` |
| `QUEUE_CONNECTION` | `database` (and run a worker service if you process queues) |
| `CACHE_STORE` | `database` or `redis` if you add Redis |

### Optional integrations (from `.env.production.example`)

`MAIL_*`, `AWS_*`, `AT_*` / `TWILIO_*` for SMS, `REDIS_*`, `TRUSTED_PROXIES` (`*` behind Railway’s proxy if needed).

---

## Connect Railway MySQL to Laravel (step by step)

1. **Create the database**  
   In your Railway project: **New** → **Database** → **MySQL**. Wait until the database is provisioned.

2. **Create or select the API service**  
   Deploy this repo with **root directory** `loom-pms-api` (or only deploy that folder). Builder should pick up `railway.json` → **NIXPACKS**.

3. **Wire variables with references**  
   Open the **Laravel service** → **Variables** → **Add variable** → **Add reference**.  
   Select your **MySQL** service and add references, for example:
   - `DB_HOST` → reference `MYSQLHOST`
   - `DB_PORT` → reference `MYSQLPORT`
   - `DB_DATABASE` → reference `MYSQLDATABASE`
   - `DB_USERNAME` → reference `MYSQLUSER`
   - `DB_PASSWORD` → reference `MYSQLPASSWORD`  

   Or add a single variable **`DB_URL`** referencing **`MYSQL_URL`**.

4. **Set `DB_CONNECTION`**  
   Add `DB_CONNECTION` = `mysql` (plain value, not a reference).

5. **Set app secrets**  
   Generate `APP_KEY` locally: `php artisan key:generate --show` and paste into Railway as `APP_KEY`. Set `APP_URL` to your Railway-generated HTTPS URL (or custom domain).

6. **Deploy**  
   Push to the connected branch or trigger **Redeploy**. The start command runs migrations and seeds, then `php artisan serve` on `$PORT`.

7. **Verify**  
   Open `https://<your-service-url>/api/health` — expect `{"status":"ok","version":"1.0.0"}`.

**Networking:** Services in the same Railway project reach MySQL on the **internal** host/port from `MYSQLHOST` / `MYSQLPORT`. You do not need to expose the DB publicly for the API container.

---

## After deploy (optional)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run these in a **Railway shell** on the running service if you want cached config in production (ensure `APP_ENV=production` first).
