#!/usr/bin/env bash
# Corrige les droits storage/bootstrap pour PHP-FPM / Apache (www-data).
# Usage : sudo ./scripts/fix-storage-permissions.sh [/chemin/vers/Module_FED]

set -euo pipefail

APP_DIR="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

cd "$APP_DIR"

php artisan app:ensure-storage 2>/dev/null || true

mkdir -p \
  storage/app/private/od \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

touch storage/logs/laravel.log

chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "OK — ${APP_DIR} (propriétaire ${WEB_USER}:${WEB_GROUP})"
