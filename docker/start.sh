#!/bin/sh
# Container start-up: prepare the app, then serve it on $PORT (Railway sets PORT).
set -e
cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Uploaded question images/audio live in storage/app/public (mount a volume there).
mkdir -p storage/app/public
php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# First deploy only: creates roles, the admin from ADMIN_* variables and the default study.
# Safe to leave on — it never overwrites edited questions — but not needed after the first run.
if [ "${SEED_ON_DEPLOY:-false}" = "true" ]; then
    php artisan db:seed --force
fi

php artisan optimize

exec frankenphp php-server --listen ":${PORT:-8080}" --root /app/public
