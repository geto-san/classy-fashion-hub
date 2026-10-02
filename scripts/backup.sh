#!/bin/sh
# Classy Fashion Hub backup: database dump + media files.
# Usage (from repo root):
#   DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=blair_fashion \
#   DB_USERNAME=blair DB_PASSWORD='...' APP_DIR=store ./scripts/backup.sh
# Keeps the last 7 backups in ./backups (gitignored).
set -eu

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?set DB_DATABASE}"
: "${DB_USERNAME:?set DB_USERNAME}"
: "${DB_PASSWORD:?set DB_PASSWORD}"
: "${APP_DIR:=store}"
: "${BACKUP_DIR:=backups}"
: "${KEEP:=7}"

STAMP=$(date +%Y%m%d-%H%M%S)
DEST="$BACKUP_DIR/$STAMP"
mkdir -p "$DEST"

mariadb-dump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
  --single-transaction --routines --events \
  "$DB_DATABASE" > "$DEST/db.sql"

tar -czf "$DEST/storage-public.tar.gz" -C "$APP_DIR/storage/app" public

ls -t "$BACKUP_DIR" | tail -n +$((KEEP + 1)) | while IFS= read -r old; do
  rm -rf "$BACKUP_DIR/$old"
done

echo "Backup written to $DEST ($(du -sh "$DEST" | cut -f1))"
