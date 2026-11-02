#!/bin/sh
set -eu

test -f /var/www/html/storage/installed
exec apache2-foreground
