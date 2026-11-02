#!/bin/sh
# Run explicitly in a controlled migration environment, using Neon's direct URL.
set -eu
cd /var/www/html
: "${DB_DIRECT_URL:?set the direct PostgreSQL connection URL for migrations}"
case "$DB_DIRECT_URL" in *-pooler.*) echo 'Migrations require the direct Neon connection.' >&2; exit 1;; esac
export DB_URL="$DB_DIRECT_URL"
export DB_CONNECTION=pgsql
APP_CONFIG_CACHE=/tmp/classy-readiness-config.php php /usr/local/bin/classy-database-ready.php
php artisan config:clear
php artisan migrate --force
