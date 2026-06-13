#!/bin/sh
# Runs as root before the CMD starts.
# Guarantees /var/www/.env is readable by the www user regardless of
# how it was created on the host. Then either:
#   - execs php-fpm directly as root (FPM master drops to www via pool config)
#   - drops to www via su-exec for any other command (artisan, queue:work, etc.)
set -eu

WWW_UID="$(id -u www)"
WWW_GID="$(id -g www)"
ENV_FILE=/var/www/.env

if [ -f "$ENV_FILE" ]; then
    if [ "$(stat -c '%u' "$ENV_FILE")" != "$WWW_UID" ] || [ "$(stat -c '%g' "$ENV_FILE")" != "$WWW_GID" ]; then
        chown www:www "$ENV_FILE" || echo "[entrypoint] WARN: chown .env failed (bind mount on Windows host?)" >&2
    fi
    chmod 640 "$ENV_FILE" || echo "[entrypoint] WARN: chmod .env failed" >&2

    if ! su-exec www test -r "$ENV_FILE"; then
        echo "[entrypoint] ERROR: .env is NOT readable by www after fix attempt." >&2
        echo "[entrypoint] $(ls -la "$ENV_FILE")" >&2
    fi
fi

# Only chown storage/bootstrap-cache if top-level dir is not already owned by www.
# Avoids stat-storm on Windows OneDrive bind mounts on every container restart.
mkdir -p /var/www/storage/app/public \
         /var/www/storage/framework/cache \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/logs \
         /var/www/bootstrap/cache || echo "[entrypoint] WARN: mkdir storage tree failed" >&2

if [ "$(stat -c '%u' /var/www/storage 2>/dev/null)" != "$WWW_UID" ] \
   || [ "$(stat -c '%u' /var/www/bootstrap/cache 2>/dev/null)" != "$WWW_UID" ]; then
    chown -R www:www /var/www/storage /var/www/bootstrap/cache \
        || echo "[entrypoint] WARN: chown -R storage/bootstrap-cache failed" >&2
fi

# php-fpm: keep as root so master can open stderr; pool config drops workers to www.
# Everything else (artisan, queue:work): drop to www via su-exec.
if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec www "$@"
