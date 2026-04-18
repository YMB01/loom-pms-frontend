# Production deployment notes

## Environment

- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env` (see `.env.production.example`).
- Never commit real `.env` files.

## CORS / frontend

- Set `CORS_ALLOWED_ORIGINS` to your Next.js origins (comma-separated, with `https://`).
- Optionally set `FRONTEND_URL` to the primary SPA URL; it is merged into allowed origins.

## Caches (after each deploy)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Or run `./scripts/production-optimize.sh`.

To clear when debugging:

```bash
php artisan optimize:clear
```

## Permissions

Ensure the web user can write:

- `storage/` (and subdirs: `framework/cache`, `framework/sessions`, `framework/views`, `logs`)
- `bootstrap/cache/`

Example (adjust user/group for your server):

```bash
chmod -R ug+rwx storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Health check

`GET /api/health` returns `{"status":"ok","version":"1.0.0"}` (no authentication).

## Heroku

`Procfile` is included: `web: vendor/bin/heroku-php-apache2 public/`

Set config vars in the Heroku dashboard to match `.env.production.example`.

## Railway

See `RAILWAY.md` for `railway.json`, `nixpacks.toml`, env vars, and MySQL reference wiring.
