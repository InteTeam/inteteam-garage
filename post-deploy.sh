#!/usr/bin/env bash
# Run by inteteam-panel DeployAppJob after `docker compose up -d`.
set -euo pipefail

# Wait for MariaDB to be ready
for i in $(seq 1 30); do
  if docker compose exec -T mariadb sh -c \
      'mariadb-admin ping -h 127.0.0.1 --silent 2>/dev/null || mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null' 2>/dev/null; then
    echo "MariaDB ready"; break
  fi
  sleep 2
  [[ $i -eq 30 ]] && echo "ERROR: MariaDB not ready after 60s" && exit 1
done

docker compose exec -T php-fpm composer install --no-dev --optimize-autoloader --no-interaction

# Generate APP_KEY only if not already set
APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2 || true)"
[[ -z "$APP_KEY" ]] && docker compose exec -T php-fpm php artisan key:generate --force

docker compose exec -T php-fpm php artisan migrate --force
docker compose exec -T php-fpm php artisan storage:link 2>/dev/null || true
docker compose exec -T php-fpm php artisan optimize:clear

# Build frontend assets
docker run --rm \
  -v "$(pwd)":/var/www \
  -w /var/www \
  node:22-alpine \
  sh -c "npm ci --silent && npm run build"

rm -f public/hot

echo "post-deploy complete"
