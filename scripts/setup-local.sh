#!/usr/bin/env bash
# Set up Classy Fashion Hub from a fresh clone.
#
# Why this exists: the repo tracks only OUR files under store/ (the ClassyFashion
# package, seeders, tests, six small core edits). Bagisto core itself is not
# committed, so a plain clone has no artisan, no vendor/ and no app.
# This script downloads Bagisto 2.4.12 and merges it UNDER the tracked files:
# anything already in store/ (including our core edits) is never overwritten.
#
# Usage:   scripts/setup-local.sh
# Options (environment):
#   PHP_BIN=php8.3        PHP binary to use (default: php)
#   BAGISTO_SOURCE=/dir   use an existing Bagisto 2.4.12 tree instead of downloading
#   SKIP_COMPOSER=1       merge files only (no composer install / key:generate)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STORE="$ROOT/store"
PHP_BIN="${PHP_BIN:-php}"
BAGISTO_VERSION="2.4.12"

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP not found. Install PHP 8.3 (Bagisto 2.4 does not support 8.5) or set PHP_BIN."
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80500 ? 0 : 1);' \
  || die "PHP 8.3 or 8.4 required, found $("$PHP_BIN" -r 'echo PHP_VERSION;')."

if [ -z "${SKIP_COMPOSER:-}" ]; then
  command -v composer >/dev/null 2>&1 || die "Composer 2 not found (https://getcomposer.org)."
fi

[ -f "$STORE/packages/ClassyFashion/src/Providers/ClassyFashionServiceProvider.php" ] \
  || die "store/packages/ClassyFashion is missing - run this from a full clone of the repo."

if [ -f "$STORE/artisan" ]; then
  say "store/ already contains Bagisto (artisan found) - skipping download."
else
  SRC="${BAGISTO_SOURCE:-}"

  if [ -z "$SRC" ]; then
    TMP="$(mktemp -d)"
    trap 'rm -rf "$TMP"' EXIT
    say "Downloading Bagisto $BAGISTO_VERSION (files only, no install yet)"
    composer create-project bagisto/bagisto "$TMP/app" "$BAGISTO_VERSION" \
      --no-install --no-scripts --no-interaction --prefer-dist
    SRC="$TMP/app"
  fi

  [ -f "$SRC/artisan" ] || die "$SRC does not look like a Bagisto tree (no artisan)."

  say "Merging Bagisto core into store/ (existing files are kept)"
  added=0
  skipped=0
  while IFS= read -r -d '' rel; do
    rel="${rel#./}"
    if [ -e "$STORE/$rel" ]; then
      skipped=$((skipped + 1))
    else
      mkdir -p "$STORE/$(dirname "$rel")"
      cp -p "$SRC/$rel" "$STORE/$rel"
      added=$((added + 1))
    fi
  done < <(cd "$SRC" && find . -type f -not -path './.git/*' -not -path './vendor/*' -not -path './node_modules/*' -print0)
  printf '    %s files added, %s project files kept as they are\n' "$added" "$skipped"
fi

say "Removing Bagisto modules this shop does not use (extra gateways, social login)"
for m in Stripe Razorpay PayU PayGlocal PhonePe Paypal SocialLogin; do
  rm -rf "$STORE/packages/Webkul/$m"
done

if [ -n "${SKIP_COMPOSER:-}" ]; then
  say "SKIP_COMPOSER set - stopping after the merge."
  exit 0
fi

cd "$STORE"

[ -f .env ] || { cp .env.example .env; say "Created store/.env from .env.example"; }

say "Installing PHP dependencies"
composer install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=.\+' .env; then
  "$PHP_BIN" artisan key:generate
fi

cat <<NEXT

Done. Next steps:

  1. Edit store/.env: DB_DATABASE / DB_USERNAME / DB_PASSWORD (empty MySQL 8 or MariaDB database),
     APP_URL, and the MTN_* sandbox keys if you want mobile money.
  2. cd store
     $PHP_BIN artisan bagisto:install --no-interaction --demo-samples
     $PHP_BIN artisan db:seed --class='Database\\Seeders\\ClassyFashionSeeder'
     $PHP_BIN artisan db:seed --class='Database\\Seeders\\ClassyFashionCatalogSeeder'
     $PHP_BIN artisan serve --port=8000
  3. Verify:  $PHP_BIN vendor/bin/pest tests/Feature

NEXT
