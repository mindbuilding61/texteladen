#!/usr/bin/env bash
set -euo pipefail

cd /var/www/app

mkdir -p var/invoice-storage var/cache var/log public/assets

if [ -z "${APP_SECRET:-}" ]; then
    echo "WARNING: APP_SECRET env var is empty. Generating ephemeral secret."
    export APP_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
fi

export APP_ENV="${APP_ENV:-prod}"
export APP_DEBUG="${APP_DEBUG:-0}"

if [ "${AUTO_MIGRATE:-1}" = "1" ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

# chown *after* migrations and cache warm so the files that root just created
# (DB, cache, logs) are handed over to the apache www-data worker.
chown -R www-data:www-data var public/assets

exec "$@"
