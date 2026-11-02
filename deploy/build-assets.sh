#!/bin/sh
# Compile both interfaces with the Classy package included in Tailwind 4 scanning.
set -eu
APP_DIR="$(cd "${1:-/var/www/html}" && pwd)"
for package in Shop Admin; do
  cd "$APP_DIR/packages/Webkul/$package"
  IMPORT='@import "../../../../../../ClassyFashion/src/Resources/assets/theme.css";'
  if ! grep -Fxq "$IMPORT" src/Resources/assets/css/app.css; then
    printf '\n%s\n' "$IMPORT" >> src/Resources/assets/css/app.css
  fi
  npm install --no-audit --no-fund
  npm run build
done
