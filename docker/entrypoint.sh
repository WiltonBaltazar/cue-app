#!/bin/sh
# Prepares the app on every container start, then hands over to Supervisor.
set -e

cd /var/www/html

# Run artisan as the web user, so caches and logs stay writable by PHP-FPM and the queue.
artisan() {
    runuser -u www-data -- php artisan "$@"
}

# A mounted storage volume starts empty: recreate Laravel's folders.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Wait for the database (it may still be starting with the stack).
tries=0
until artisan db:show >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "Database not reachable after 60 s." >&2
        exit 1
    fi
    echo "Waiting for the database..."
    sleep 2
done

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    artisan migrate --force
    # Reference data only (team roles, declaration); safe to repeat.
    artisan db:seed --force
fi

# public/ belongs to root, so the symlink is made as root.
php artisan storage:link --force >/dev/null 2>&1 || true
artisan optimize

exec "$@"
