#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

# Nothing here uses the database yet - sessions and cache are on the filesystem.
# The file exists so artisan does not fail the moment a migration is added.
mkdir -p database
touch database/database.sqlite

rm -f bootstrap/cache/*.php

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

exec "$@"
