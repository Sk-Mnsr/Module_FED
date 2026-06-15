#!/usr/bin/env bash
# Droits storage/bootstrap : PHP-FPM (www-data) + déploiement artisan (support, etc.)
#
# Usage :
#   sudo DEPLOY_USER=support bash scripts/fix-storage-permissions.sh /var/www/html/Module_FED
#
# Ajouter l'utilisateur de déploiement au groupe web (une fois) :
#   sudo usermod -aG www-data support

set -euo pipefail

APP_DIR="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
WEB_GROUP="${WEB_GROUP:-www-data}"

# Utilisateur qui lance artisan / déploie (support par défaut si sudo)
if [[ -n "${DEPLOY_USER:-}" ]]; then
  DEPLOY_USER="${DEPLOY_USER}"
elif [[ -n "${SUDO_USER:-}" && "${SUDO_USER}" != "root" ]]; then
  DEPLOY_USER="${SUDO_USER}"
else
  DEPLOY_USER="$(stat -c '%U' "$APP_DIR" 2>/dev/null || echo "www-data")"
fi

cd "$APP_DIR"

mkdir -p \
  storage/app/private/od \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

touch storage/logs/laravel.log

chown -R "${DEPLOY_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

# setgid sur les dossiers : nouveaux fichiers héritent du groupe www-data
find storage bootstrap/cache -type d -exec chmod g+s {} \;

echo "OK — ${APP_DIR}"
echo "    propriétaire : ${DEPLOY_USER}:${WEB_GROUP}"
echo "    PHP-FPM doit tourner sous un utilisateur du groupe ${WEB_GROUP} (ex. www-data)."
echo ""
echo "Si artisan échoue encore, ajoutez ${DEPLOY_USER} au groupe web :"
echo "    sudo usermod -aG ${WEB_GROUP} ${DEPLOY_USER}"
echo "    (puis reconnectez-vous en SSH)"
