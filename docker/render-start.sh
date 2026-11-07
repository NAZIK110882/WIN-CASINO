#!/bin/sh
set -eu

php scripts/migrate.php
php scripts/seed.php
exec apache2-foreground
