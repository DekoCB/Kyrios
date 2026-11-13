#!/usr/bin/env bash
# Actualiza Kyrios en Hostinger: git pull + dependencias + migraciones + cachés.
# Uso (por SSH, dentro de la carpeta de la app):  bash deploy/hostinger.sh
set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
    echo "Falta el archivo .env (copie deploy/env.produccion.example y complete los datos)." >&2
    exit 1
fi

php artisan down --retry=60 >/dev/null 2>&1 || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

git pull --ff-only

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Listo: $(git log --oneline -1)"
