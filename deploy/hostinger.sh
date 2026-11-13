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

# Hostinger desactiva proc_open: composer no puede lanzar los scripts, se corre el descubrimiento a mano
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
php artisan package:discover --ansi

php artisan migrate --force
# exec() también está desactivado, así que el enlace de storage se crea con ln (no con artisan storage:link)
[ -e public/storage ] || ln -s ../storage/app/public public/storage

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Listo: $(git log --oneline -1)"
