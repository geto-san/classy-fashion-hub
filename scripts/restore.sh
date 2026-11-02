#!/bin/sh
# Restore into a disposable, empty database; never overwrite business data.
set -eu
umask 077
. "$(dirname "$0")/database-env.sh"
: "${APP_DIR:=store}"
: "${MEDIA_DIR:=$APP_DIR/storage/app}"
SRC="${1:?usage: restore.sh backups/<stamp>}"
test -f "$SRC/COMPLETE" || { echo 'Backup is incomplete or uses the legacy format.' >&2; exit 1; }
test "$(cat "$SRC/database-driver")" = "$DB_CONNECTION" || { echo 'Database driver differs from the backup.' >&2; exit 1; }
(cd "$SRC" && sha256sum -c SHA256SUMS)
python3 - "$SRC/media.tar.gz" "$MEDIA_DIR" <<'PY'
import sys,tarfile
from pathlib import Path
with tarfile.open(sys.argv[1]) as archive:
    for member in archive.getmembers():
        path=Path(member.name)
        if path.is_absolute() or '..' in path.parts or not path.parts or path.parts[0] not in ('public','private') or member.issym() or member.islnk() or not (member.isfile() or member.isdir()):
            raise SystemExit('Unsafe media archive entry.')
        target=Path(sys.argv[2])/path
        if target.exists() and member.isfile():
            raise SystemExit('Media destination contains files; choose an empty directory.')
PY
case "$DB_CONNECTION" in
  pgsql)
    TABLES=$(psql -X -At -v ON_ERROR_STOP=1 -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';")
    test "$TABLES" = 0 || { echo 'Target database is not empty. Refusing.' >&2; exit 1; }
    pg_restore --exit-on-error --single-transaction --no-owner --no-acl --dbname="$DB_DATABASE" "$SRC/db.dump"
    ;;
  *)
    TABLES=$(mariadb -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -N "$DB_DATABASE" -e 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();')
    test "$TABLES" = 0 || { echo 'Target database is not empty. Refusing.' >&2; exit 1; }
    mariadb -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" < "$SRC/db.sql"
    ;;
esac
mkdir -p "$MEDIA_DIR"
python3 - "$SRC/media.tar.gz" "$MEDIA_DIR" <<'PY'
import sys,tarfile
with tarfile.open(sys.argv[1]) as archive:
    archive.extractall(sys.argv[2],filter='data')
PY
echo "Restored $SRC into $DB_DATABASE."
