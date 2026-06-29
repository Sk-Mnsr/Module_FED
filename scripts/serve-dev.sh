#!/usr/bin/env bash
# Serveur Laravel avec limites upload adaptées (php-dev.ini : 32 Mo / post 64 Mo)
set -e
cd "$(dirname "$0")/.."
exec php -c php-dev.ini artisan serve "$@"
