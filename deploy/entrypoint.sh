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

# Render (and most PaaS) injects $PORT; Apache must listen on it,
# otherwise the platform reports "no open ports".
PORT="${PORT:-80}"
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

# Wait for the database (max ~2 minutes), then fail fast with a clear
# message instead of cascading into migrate/seed errors.
DB_OK=0
for i in $(seq 1 24); do
  if php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; then
    DB_OK=1
    break
  fi
  sleep 5
done

if [ "$DB_OK" != "1" ]; then
  echo "FATAL: cannot reach MySQL at ${DB_HOST}:${DB_PORT} as ${DB_USERNAME}."
  echo "Check: (1) Railway MySQL service is Running (not paused; trial credit left),"
  echo "(2) DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD env values,"
  echo "(3) Railway public networking is enabled for the database."
  exit 1
fi

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
