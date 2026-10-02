#!/bin/sh
# Render entrypoint: configure, migrate/seed once when fresh, serve.
set -eu

cd /var/www/html

: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:?set DB_HOST}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?set DB_DATABASE}"
: "${DB_USERNAME:?set DB_USERNAME}"
: "${DB_PASSWORD:?set DB_PASSWORD}"

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

# Wait for the database (max ~2 minutes).
for i in $(seq 1 24); do
  if php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; then
    break
  fi
  sleep 5
done

php artisan storage:link || true
php artisan migrate --force

# Restore any media missing from the (ephemeral) disk. Idempotent.
php artisan classy:repair-images || true

# Fresh database? Seed core data + Classy Fashion data (never wipes).
ADMINS=$(php artisan tinker --execute="echo DB::table('admins')->count();" 2>/dev/null | tr -cd '0-9')
if [ "${ADMINS:-0}" = "0" ]; then
  php artisan db:seed --force
  php artisan db:seed --class='Database\Seeders\ClassyFashionSeeder' --force
  php artisan db:seed --class='Database\Seeders\ClassyFashionCatalogSeeder' --force
  if [ "${SEED_DEMO:-false}" = "true" ]; then
    php artisan db:seed --class='Database\Seeders\DemoOrdersSeeder' --force
  fi
  # Brand logo/favicon and seeded images are restored by
  # classy:repair-images (runs on every boot, below).
  php artisan indexer:index --mode=full || true
fi

php artisan optimize
chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
