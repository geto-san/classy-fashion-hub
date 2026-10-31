#!/bin/sh
# Hosting entrypoint: configure, migrate/seed once when fresh, serve.
set -eu

cd /var/www/html

: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:?set DB_HOST}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?set DB_DATABASE}"
: "${DB_USERNAME:?set DB_USERNAME}"
: "${DB_PASSWORD:?set DB_PASSWORD}"

# Keep the same key across every deployment; never generate an ephemeral key.
: "${APP_KEY:?set a persistent APP_KEY (php artisan key:generate --show)}"
case "$APP_KEY" in
  base64:*) ;;
  *) export APP_KEY="base64:$APP_KEY" ;;
esac
php -r '$key = base64_decode(substr(getenv("APP_KEY"), 7), true); if ($key === false || strlen($key) !== 32) { fwrite(STDERR, "FATAL: APP_KEY must contain 32 base64-encoded bytes.\n"); exit(1); }'

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
export DB_CONNECTION DB_PORT

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
  echo "(3) app and database share the same Railway environment/private network."
  exit 1
fi

php artisan config:clear
php artisan storage:link || true
php artisan migrate --force

# Fresh database? Seed core data + Classy Fashion data (never wipes).
ADMINS=$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::table("admins")->count();')
if [ "${ADMINS:-0}" = "0" ]; then
  : "${SEED_ADMIN_PASSWORD:?set SEED_ADMIN_PASSWORD before first deployment}"
  php -r '$password = getenv("SEED_ADMIN_PASSWORD"); if (strlen($password) < 12 || in_array($password, ["admin123", "worker123", "customer123"], true)) { fwrite(STDERR, "FATAL: choose an administrator password with at least 12 characters.\n"); exit(1); }'
  EXISTING_CHANNELS=$(php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo Illuminate\Support\Facades\DB::table("channels")->count();')
  if [ "$EXISTING_CHANNELS" != "0" ]; then
    echo "FATAL: existing store has no admin; recover the account instead of reseeding business data."
    exit 1
  fi
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

# Restore any media missing from the (ephemeral) disk. Idempotent.
php artisan classy:repair-images || true
# Attach real product photos (database/seeders/product-images) when supplied.
php artisan classy:import-photos || true

# Single Classy brand on every boot (idempotent): footer, e-mail sender,
# invoice footer, admin logo, channel logo_alt and SEO.
php artisan db:seed --class='Database\Seeders\ClassyBrandSeeder' --force || true

# Replace any published default password (admin123/worker123/customer123),
# including on databases seeded before this check existed. Idempotent;
# prints a new random password once in this log unless SEED_*_PASSWORD is set.
php artisan classy:secure-accounts

# Lost-password recovery (no shell on the free tier): set SEED_ADMIN_PASSWORD
# and RESET_ADMIN_PASSWORD=true, redeploy once, then remove RESET_ADMIN_PASSWORD.
if [ "${RESET_ADMIN_PASSWORD:-}" = "true" ]; then
  php artisan classy:secure-accounts --reset-admin
fi

php artisan optimize
chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
