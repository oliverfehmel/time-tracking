#!/bin/sh
# Entrypoint of the Docker image: provides APP_SECRET, waits for the database,
# runs the migrations (only when RUN_MIGRATIONS=1) and warms up the cache.
# Every console command runs as www-data, so var/cache stays writable for Apache.
set -eu

cd /var/www/html

as_www_data() {
    su -s /bin/sh www-data -c "$*"
}

chown -R www-data:www-data var public/uploads public/favicon

# Without an explicit APP_SECRET a random one is generated once and kept in the data volume,
# so sessions and remember-me cookies survive container restarts.
if [ -z "${APP_SECRET:-}" ]; then
    secret_file=var/data/app_secret
    if [ ! -s "$secret_file" ]; then
        as_www_data "php -r 'echo bin2hex(random_bytes(32));' > $secret_file"
    fi
    APP_SECRET=$(cat "$secret_file")
    export APP_SECRET
fi

if [ "${1:-}" = "apache2-foreground" ] || [ "${1:-}" = "php" ]; then
    echo "Waiting for the database ..."
    attempts=0
    until as_www_data "php bin/console dbal:run-sql -q 'SELECT 1'" > /dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 60 ]; then
            echo "Database not reachable, giving up." >&2
            as_www_data "php bin/console dbal:run-sql 'SELECT 1'"
            exit 1
        fi
        sleep 2
    done

    if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
        as_www_data "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration"
    fi

    as_www_data "php bin/console cache:warmup"
fi

# The worker runs as www-data, Apache drops privileges by itself.
if [ "${1:-}" = "php" ]; then
    exec su -s /bin/sh www-data -c "exec $*"
fi

exec "$@"
