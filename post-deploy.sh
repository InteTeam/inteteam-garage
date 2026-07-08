#!/usr/bin/env bash
# Run by inteteam-panel DeployAppJob after `docker compose up -d`.
set -euo pipefail

# Always run from the project root, regardless of caller's working directory.
cd "$(dirname "${BASH_SOURCE[0]}")"

# Fix .env permissions — panel writes .env as root (600); PHP-FPM (uid 1000) must read it.
# Container entrypoint (docker/php/entrypoint.sh) also enforces this on every start,
# but we set it here too so the host-visible file is correct (e.g. for /home/deploy SSH session).
# Fatal if it fails — panel-driven deploys must surface this signal, not swallow it.
if [[ -f .env ]]; then
    chown 1000:1000 .env || { echo "ERROR: chown .env failed — panel-side .env writer must run as root"; exit 1; }
    chmod 640 .env       || { echo "ERROR: chmod .env failed"; exit 1; }
fi

# Fix storage permissions — git pull (as root) leaves storage/ owned by root.
# PHP runs as UID 1000 (www) inside the container and must be able to write here.
mkdir -p storage/app/public storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache
chown -R 1000:1000 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Wait for MariaDB to be ready
for i in $(seq 1 30); do
  if docker compose exec -T mariadb sh -c \
      'mariadb-admin ping -h 127.0.0.1 --silent 2>/dev/null || mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null' 2>/dev/null; then
    echo "MariaDB ready"; break
  fi
  sleep 2
  [[ $i -eq 30 ]] && echo "ERROR: MariaDB not ready after 60s" && exit 1
done

# Fix /home/www/.config for Tinker/XDG cache — must run as root inside container
docker compose exec -T -u root php-fpm sh -c \
  "mkdir -p /home/www/.config && chown -R 1000:1000 /home/www/.config storage bootstrap/cache" \
  2>/dev/null || echo "warning: php-fpm home/.config fix skipped"

# Nginx upload limit
docker compose exec -T nginx sh -c \
  "printf 'client_max_body_size 100M;\n' > /etc/nginx/conf.d/z_uploads.conf && nginx -s reload" \
  2>/dev/null || echo "warning: nginx upload limit fix skipped"

docker compose exec -T php-fpm composer install --no-dev --optimize-autoloader --no-interaction

# Generate APP_KEY only if not already set, then restart services so all
# long-running processes (queue-worker) pick up the new key from .env.
APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2 || true)"
if [[ -z "$APP_KEY" ]]; then
  docker compose exec -T php-fpm php artisan key:generate --force
  echo "APP_KEY generated — restarting services to load new key..."
  # Restart nginx too: it caches php-fpm's container IP and returns 502 after
  # php-fpm gets a new IP. See playbook docker/README.md section
  # "Gotcha: nginx caches php-fpm IP after restart php-fpm".
  docker compose restart php-fpm queue-worker nginx
  sleep 3
fi

docker compose exec -T php-fpm php artisan migrate --force
docker compose exec -T php-fpm php artisan storage:link 2>/dev/null || true

# One-shot admin bootstrap for fresh envs. Read GARAGE_BOOTSTRAP_ADMIN_EMAIL
# from .env (Panel-managed) and pass as CLI arg so config:cache doesn't hide it.
# Command is idempotent (firstOrCreate) — safe to run on every deploy. Remove
# the env var in Panel once the admin exists.
BOOTSTRAP_EMAIL="$(grep '^GARAGE_BOOTSTRAP_ADMIN_EMAIL=' .env | cut -d= -f2 | tr -d '"' || true)"
if [[ -n "$BOOTSTRAP_EMAIL" ]]; then
  docker compose exec -T php-fpm php artisan garage:bootstrap-admin --email="$BOOTSTRAP_EMAIL"
fi

# Production cache warming — skip if APP_URL is not set (dev/local mode)
APP_URL="$(grep '^APP_URL=' .env | cut -d= -f2 || true)"
if [[ "$APP_URL" == https://* ]]; then
  docker compose exec -T php-fpm php artisan config:cache
  docker compose exec -T php-fpm php artisan route:cache
  docker compose exec -T php-fpm php artisan view:cache
else
  docker compose exec -T php-fpm php artisan optimize:clear
fi

# Restart php-fpm + queue-worker + nginx on every deploy so all long-running
# processes pick up the latest PHP code. Without php-fpm restart, OPcache
# keeps serving stale bytecode for controllers/config until the next fpm
# restart. Nginx follows the same pattern as the APP_KEY-generation branch
# above — it caches php-fpm's container IP and would return 502 otherwise.
docker compose restart php-fpm queue-worker nginx
sleep 3

# Build frontend assets
docker run --rm \
  --env-file .env \
  -v "$(pwd)":/var/www \
  -w /var/www \
  node:22-alpine \
  sh -c "npm ci --silent && npm run build"

rm -f public/hot

if [[ ! -f "public/build/manifest.json" ]]; then
  echo "ERROR: Frontend build failed — public/build/manifest.json not found"
  exit 1
fi

echo "post-deploy complete"
