#!/usr/bin/env bash
# Diagnostic rapide serveur COFI-COMPTA (permissions, DB, sessions, cache).
# Usage : cd /var/www/html/Module_FED && bash scripts/server-diagnose.sh

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

echo "=== COFI-COMPTA — diagnostic serveur ==="
echo "Répertoire : $APP_DIR"
echo "Utilisateur : $(whoami) ($(id))"
echo ""

fail=0

check() {
  if eval "$2" >/dev/null 2>&1; then
    echo "  OK   $1"
  else
    echo "  FAIL $1"
    fail=1
  fi
}

echo "— Permissions storage / bootstrap/cache —"
check "storage/logs inscriptible" "test -w storage/logs"
check "storage/framework inscriptible" "test -w storage/framework"
check "storage/app/private inscriptible" "test -w storage/app/private"
check "bootstrap/cache inscriptible" "test -w bootstrap/cache"
ls -la storage/logs/laravel.log 2>/dev/null | sed 's/^/       /' || echo "       (pas de laravel.log)"
echo ""

echo "— PHP-FPM (www-data) peut écrire les logs ? —"
if id www-data >/dev/null 2>&1; then
  if sudo -u www-data test -w storage/logs 2>/dev/null; then
    echo "  OK   www-data → storage/logs"
  else
    echo "  FAIL www-data ne peut pas écrire dans storage/logs"
    echo "       → sudo chown -R support:www-data storage bootstrap/cache"
    echo "       → sudo chmod -R ug+rwx storage bootstrap/cache"
    echo "       → sudo usermod -aG www-data support  # puis reconnexion SSH"
    fail=1
  fi
else
  echo "  SKIP utilisateur www-data introuvable"
fi
echo ""

echo "— Base de données —"
if php artisan db:show 2>/dev/null | head -5; then
  :
else
  php -r "
    require 'vendor/autoload.php';
    \$app = require 'bootstrap/app.php';
    \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo '  Connexion : OK ('.config('database.default').')\n';
    echo '  Users     : '.App\Models\User::count().\"\n\";
  " 2>/dev/null || { echo "  FAIL connexion DB"; fail=1; }
fi
echo ""

echo "— Tables sessions / cache (requis pour la connexion) —"
php artisan tinker --execute="
echo Schema::hasTable('sessions') ? '  OK   table sessions' : '  FAIL table sessions manquante';
echo PHP_EOL;
echo Schema::hasTable('cache') ? '  OK   table cache' : '  FAIL table cache manquante';
echo PHP_EOL;
" 2>/dev/null || echo "  (tinker indisponible — lancez php artisan migrate --force)"
echo ""

echo "— Cache config Laravel —"
if [[ -f bootstrap/cache/config.php ]]; then
  echo "  INFO bootstrap/cache/config.php présent"
  if [[ ! -r bootstrap/cache/config.php ]]; then
    echo "  FAIL config.php illisible — php artisan config:clear"
    fail=1
  fi
else
  echo "  INFO pas de config cache (normal après config:clear)"
fi
echo ""

echo "— Test session (comme le navigateur) —"
if sudo -u www-data php artisan tinker --execute="
session()->put('_diag', 'ok');
session()->save();
echo session()->get('_diag') === 'ok' ? '  OK   session enregistrée' : '  FAIL session';
echo PHP_EOL;
" 2>/dev/null; then
  :
else
  echo "  WARN test session www-data échoué (droits ou driver session)"
fi
echo ""

if [[ $fail -eq 0 ]]; then
  echo "=== Résultat : OK — retestez la connexion ==="
  echo "Si le login échoue encore :"
  echo "  1. APP_DEBUG=true temporairement dans .env"
  echo "  2. tail -50 storage/logs/laravel.log pendant la connexion"
  echo "  3. php artisan migrate --force"
else
  echo "=== Résultat : problèmes détectés — corrigez puis relancez ce script ==="
  echo "  sudo DEPLOY_USER=support bash scripts/fix-storage-permissions.sh $APP_DIR"
  echo "  php artisan config:clear"
  echo "  php artisan migrate --force"
  exit 1
fi
