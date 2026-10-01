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

# Fresh database? Seed core data + Classy Fashion data (never wipes).
ADMINS=$(php artisan tinker --execute="echo DB::table('admins')->count();" 2>/dev/null | tr -cd '0-9')
if [ "${ADMINS:-0}" = "0" ]; then
  php artisan db:seed --force
  php artisan db:seed --class='Database\Seeders\ClassyFashionSeeder' --force
  php artisan db:seed --class='Database\Seeders\ClassyFashionCatalogSeeder' --force
  if [ "${SEED_DEMO:-false}" = "true" ]; then
    php artisan db:seed --class='Database\Seeders\DemoOrdersSeeder' --force
  fi
  # Restore brand logo/favicon into (ephemeral) storage on fresh installs.
  mkdir -p storage/app/public/channel
  cp public/images/brand-logo.png storage/app/public/channel/cfh-logo-brandkit.png
  cp public/images/brand-favicon.png storage/app/public/channel/cfh-favicon-brandkit.png
  php artisan tinker --execute="DB::table('channels')->where('id', 1)->update(['logo' => 'channel/cfh-logo-brandkit.png', 'favicon' => 'channel/cfh-favicon-brandkit.png']);" --no-interaction
  php artisan indexer:index --mode=full || true
fi

php artisan optimize
chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
