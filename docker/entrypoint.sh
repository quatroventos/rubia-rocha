#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
  storage/app/public \
  storage/app/private \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  php artisan key:generate --force
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

ln -sfn ../storage/app/public public/storage

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
  php artisan cms:ensure-admin || true
fi

if [ "${RUN_SEED:-false}" = "true" ]; then
  php artisan db:seed --class=InitialContentSeeder --force
fi

if [ "${RUN_PRERENDER:-true}" = "true" ] && command -v node >/dev/null 2>&1; then
  php artisan site:prerender || echo "site:prerender falhou; o HTML publico pode estar desatualizado."
  chown -R www-data:www-data storage/app/prerender storage/app/prerender-payloads.json 2>/dev/null || true
fi

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" = "false" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

php-fpm -D
nginx -g "daemon off;"
