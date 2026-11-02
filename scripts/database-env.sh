#!/bin/sh
# Shared client configuration; source from backup.sh and restore.sh.
: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_DATABASE:?set DB_DATABASE}"
: "${DB_USERNAME:?set DB_USERNAME}"
: "${DB_PASSWORD:?set DB_PASSWORD}"
case "$DB_CONNECTION" in
  pgsql)
    : "${DB_PORT:=5432}"
    : "${DB_SSLMODE:=require}"
    case "$DB_HOST" in *-pooler.*) echo 'Use the direct PostgreSQL host for backup/restore.' >&2; exit 1;; esac
    case "$DB_SSLMODE" in require|verify-ca|verify-full) ;; *) echo 'PostgreSQL backup/restore requires TLS.' >&2; exit 1;; esac
    export PGHOST="$DB_HOST" PGPORT="$DB_PORT" PGDATABASE="$DB_DATABASE" PGUSER="$DB_USERNAME" PGPASSWORD="$DB_PASSWORD" PGSSLMODE="$DB_SSLMODE" PGCONNECT_TIMEOUT=15
    ;;
  mysql|mariadb)
    : "${DB_PORT:=3306}"
    export MYSQL_PWD="$DB_PASSWORD"
    ;;
  *) echo "Unsupported DB_CONNECTION: $DB_CONNECTION" >&2; exit 1;;
esac
