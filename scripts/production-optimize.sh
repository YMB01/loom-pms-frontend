#!/usr/bin/env bash
# Run on the server after deploy (PHP + Composer available).
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Laravel caches warmed (config, route, view)."
