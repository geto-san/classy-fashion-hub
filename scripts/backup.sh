#!/bin/sh
# Back up the database and a complete, independently exported media directory.
# For Cloudinary, export public and private disks with classy:copy-media first.
set -eu
umask 077
. "$(dirname "$0")/database-env.sh"
: "${APP_DIR:=store}"
: "${BACKUP_DIR:=backups}"
: "${MEDIA_DIR:=$APP_DIR/storage/app}"
: "${MEDIA_EXPORT_COMPLETE:?set MEDIA_EXPORT_COMPLETE=true after verifying the media export}"
test "$MEDIA_EXPORT_COMPLETE" = true || { echo 'A verified media export is required.' >&2; exit 1; }
test -d "$MEDIA_DIR/public" || { echo 'Missing public media directory.' >&2; exit 1; }
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
DEST="$BACKUP_DIR/$STAMP"
mkdir -p "$BACKUP_DIR"
mkdir "$DEST"
case "$DB_CONNECTION" in
  pgsql)
    pg_dump --format=custom --no-owner --no-acl --file="$DEST/db.dump.partial"
    pg_restore --list "$DEST/db.dump.partial" > /dev/null
    mv "$DEST/db.dump.partial" "$DEST/db.dump"
    ;;
  *)
    mariadb-dump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
      --single-transaction --quick --hex-blob --routines --events \
      "$DB_DATABASE" > "$DEST/db.sql.partial"
    test -s "$DEST/db.sql.partial"
    mv "$DEST/db.sql.partial" "$DEST/db.sql"
    ;;
esac
if [ -d "$MEDIA_DIR/private" ]; then
  tar -czf "$DEST/media.tar.gz.partial" -C "$MEDIA_DIR" public private
else
  tar -czf "$DEST/media.tar.gz.partial" -C "$MEDIA_DIR" public
fi
mv "$DEST/media.tar.gz.partial" "$DEST/media.tar.gz"
printf '%s\n' "$DB_CONNECTION" > "$DEST/database-driver"
(cd "$DEST" && sha256sum db.* media.tar.gz database-driver > SHA256SUMS)
printf '%s\n' 'complete' > "$DEST/COMPLETE"
echo "Backup written to $DEST. Copy this directory off this machine."
