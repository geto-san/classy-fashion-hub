#!/bin/sh
# Restore a backup made by scripts/backup.sh into a database.
# Usage:
#   DB_HOST=... DB_PORT=... DB_DATABASE=<target> DB_USERNAME=... DB_PASSWORD='...' \
#   APP_DIR=store ./scripts/restore.sh backups/<stamp>
# The target database must exist and be EMPTY (we never wipe implicitly).
set -eu

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?set DB_DATABASE}"
: "${DB_USERNAME:?set DB_USERNAME}"
: "${DB_PASSWORD:?set DB_PASSWORD}"
: "${APP_DIR:=store}"

SRC="${1:?usage: restore.sh backups/<stamp>}"
test -f "$SRC/db.sql" || { echo "No db.sql in $SRC"; exit 1; }

TABLES=$(mariadb -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE';")
test "$TABLES" = "0" || { echo "Target $DB_DATABASE is not empty ($TABLES tables). Refusing."; exit 1; }

mariadb -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$SRC/db.sql"

mkdir -p "$APP_DIR/storage/app"
tar -xzf "$SRC/storage-public.tar.gz" -C "$APP_DIR/storage/app"

echo "Restored $SRC into $DB_DATABASE"
