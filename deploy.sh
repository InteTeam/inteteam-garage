#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
#  INTETEAM-GARAGE — One-shot Ubuntu server deployment script
#
#  Usage (from inside cloned repo):
#    sudo bash deploy.sh --domain=garage.example.com --ssl=caddy
#    sudo bash deploy.sh --domain=garage.example.com --ssl=npm
#
#  Usage (single-line from remote, repo not yet cloned):
#    curl -fsSL https://raw.githubusercontent.com/InteTeam/inteteam-garage/main/deploy.sh \
#      | sudo bash -s -- --domain=garage.example.com --repo=git@github.com:InteTeam/inteteam-garage.git
#
#  Options:
#    --domain=example.com     Your domain name (required for production)
#    --ssl=caddy|npm          caddy = built-in auto-SSL (default)
#                             npm   = use external Nginx Proxy Manager
#    --repo=<git-url>         Git URL to clone (if not already in the repo dir)
#    --branch=main            Branch to deploy (default: main)
#    --port=8085              Host port nginx binds to (default: 8085)
#    --dir=/home/deploy/...   Where to clone the repo
#    --fresh                  Run migrate:fresh --seed  ⚠ DESTROYS existing data
#    --help                   Show this help
#
#  Tested on: Ubuntu 22.04 LTS / Ubuntu 24.04 LTS
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail
IFS=$'\n\t'

# ── Colours ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

step() { echo -e "\n${BOLD}${BLUE}── Step ${1}/12 ──${RESET}  ${BOLD}${2}${RESET}"; }
ok()   { echo -e "  ${GREEN}✓${RESET}  ${1}"; }
warn() { echo -e "  ${YELLOW}⚠${RESET}  ${1}"; }
err()  { echo -e "  ${RED}✗${RESET}  ${1}" >&2; }
info() { echo -e "  ${CYAN}→${RESET}  ${1}"; }
die()  { err "$1"; exit 1; }

# ── Defaults ─────────────────────────────────────────────────────────────────
DOMAIN=""
SSL_METHOD="caddy"
REPO_URL=""
BRANCH="main"
DEPLOY_DIR="/home/deploy/inteteam-garage"
APP_PORT="${PORT:-8085}"
FRESH=false

# ── Parse arguments ──────────────────────────────────────────────────────────
for arg in "$@"; do
  case $arg in
    --domain=*)  DOMAIN="${arg#*=}" ;;
    --ssl=*)     SSL_METHOD="${arg#*=}" ;;
    --repo=*)    REPO_URL="${arg#*=}" ;;
    --branch=*)  BRANCH="${arg#*=}" ;;
    --port=*)    APP_PORT="${arg#*=}" ;;
    --dir=*)     DEPLOY_DIR="${arg#*=}" ;;
    --fresh)     FRESH=true ;;
    --help|-h)
      grep '^#  ' "$0" | sed 's/^#  //'
      exit 0
      ;;
    *) warn "Unknown argument ignored: $arg" ;;
  esac
done

# ── Banner ───────────────────────────────────────────────────────────────────
echo -e "${BOLD}${CYAN}"
echo "╔══════════════════════════════════════════════╗"
echo "║    INTETEAM-GARAGE  —  Deployment Script     ║"
echo "╚══════════════════════════════════════════════╝"
echo -e "${RESET}"
[[ -n "$DOMAIN"   ]] && info "Domain     : $DOMAIN"     || warn "No domain set — local/dev mode only"
[[ -n "$REPO_URL" ]] && info "Repo       : $REPO_URL"
info "SSL method : $SSL_METHOD"
info "App port   : $APP_PORT"
[[ "$FRESH" == "true" ]] && warn "FRESH mode : migrate:fresh --seed will run (data will be reset)"

# ── Must run as root ─────────────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || die "Run as root: sudo bash deploy.sh [options]"

# ─────────────────────────────────────────────────────────────────────────────
step "1" "System packages & Docker"
# ─────────────────────────────────────────────────────────────────────────────
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git curl wget vim ufw fail2ban

if ! command -v docker &>/dev/null; then
  info "Docker not found — installing via official script..."
  curl -fsSL https://get.docker.com | sh
  systemctl enable --now docker
  ok "Docker installed: $(docker --version)"
else
  ok "Docker already present: $(docker --version)"
fi

if ! docker compose version &>/dev/null; then
  apt-get install -y -qq docker-compose-plugin
fi
ok "Docker Compose: $(docker compose version --short 2>/dev/null || docker compose version)"

mkdir -p /etc/docker
cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" },
  "storage-driver": "overlay2"
}
JSON
systemctl restart docker
ok "Docker daemon log rotation configured"

# ─────────────────────────────────────────────────────────────────────────────
step "2" "Firewall (UFW + fail2ban)"
# ─────────────────────────────────────────────────────────────────────────────
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow "${APP_PORT}/tcp"
ufw --force enable
systemctl enable --now fail2ban
ok "UFW active, fail2ban running"

# ─────────────────────────────────────────────────────────────────────────────
step "3" "Repository"
# ─────────────────────────────────────────────────────────────────────────────
if [[ -f "./docker-compose.yml" && -f "./artisan" ]]; then
  APP_DIR="$(pwd)"
  ok "Running from inside repo: $APP_DIR"
elif [[ -d "$DEPLOY_DIR" && -f "$DEPLOY_DIR/artisan" ]]; then
  APP_DIR="$DEPLOY_DIR"
  info "Found existing repo at $APP_DIR — pulling latest..."
  cd "$APP_DIR"
  git pull --ff-only origin "$BRANCH" 2>/dev/null || warn "git pull skipped (manual changes present)"
  ok "Repo up-to-date"
else
  [[ -n "$REPO_URL" ]] || die "No repo found in current dir and no --repo=<url> provided. See --help."
  info "Cloning $REPO_URL → $DEPLOY_DIR ..."
  mkdir -p "$(dirname "$DEPLOY_DIR")"
  git clone --branch "$BRANCH" "$REPO_URL" "$DEPLOY_DIR"
  APP_DIR="$DEPLOY_DIR"
fi

cd "$APP_DIR"
ok "Working directory: $APP_DIR"

# ─────────────────────────────────────────────────────────────────────────────
step "4" "Pre-flight: host permissions"
# ─────────────────────────────────────────────────────────────────────────────
# UID 33 = www-data inside Docker Alpine. Must set BEFORE containers start.
info "Preparing Laravel storage & bootstrap/cache ..."
mkdir -p storage/app/public
mkdir -p storage/framework/{cache,sessions,testing,views}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chown -R 33:33 storage bootstrap/cache
chmod -R 775  storage bootstrap/cache
ok "storage/ and bootstrap/cache/ permissions set"

# ─────────────────────────────────────────────────────────────────────────────
step "5" "Environment (.env)"
# ─────────────────────────────────────────────────────────────────────────────
[[ ! -f .env ]] && cp .env.example .env && info "Created .env from .env.example"

set_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

randpass() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c 32; }

# ── Port (Panel injects PORT before running deploy.sh) ───────────────────────
set_env "PORT" "$APP_PORT"

# ── Application ──────────────────────────────────────────────────────────────
if [[ -n "$DOMAIN" ]]; then
  set_env "APP_ENV"   "production"
  set_env "APP_DEBUG" "false"
  set_env "APP_URL"   "https://${DOMAIN}"
  set_env "LOG_LEVEL" "warning"
else
  set_env "APP_ENV"   "local"
  set_env "APP_DEBUG" "true"
  set_env "LOG_LEVEL" "debug"
fi

# ── Database ─────────────────────────────────────────────────────────────────
set_env "DB_CONNECTION" "mysql"
set_env "DB_HOST"       "mariadb"
set_env "DB_PORT"       "3306"
set_env "DB_DATABASE"   "inteteam_garage"
set_env "DB_USERNAME"   "garage"

_DB_PASS="$(grep "^DB_PASSWORD=" .env | cut -d= -f2 || true)"
if [[ -z "$_DB_PASS" || "$_DB_PASS" == "GaragePass2026!" ]]; then
  set_env "DB_PASSWORD"      "$(randpass)"
  set_env "DB_ROOT_PASSWORD" "root_$(randpass)"
  warn "New DB passwords generated — keep .env safe!"
fi

# ── Redis ────────────────────────────────────────────────────────────────────
set_env "REDIS_CLIENT" "phpredis"
set_env "REDIS_HOST"   "redis"
set_env "REDIS_PORT"   "6379"

_REDIS_PASS="$(grep "^REDIS_PASSWORD=" .env | cut -d= -f2 || true)"
if [[ -z "$_REDIS_PASS" || "$_REDIS_PASS" == "GarageRedis2026!" ]]; then
  set_env "REDIS_PASSWORD" "$(randpass)"
fi

# ── Drivers ──────────────────────────────────────────────────────────────────
set_env "SESSION_DRIVER"   "redis"
set_env "CACHE_STORE"      "redis"
set_env "QUEUE_CONNECTION" "redis"

# ── SSO (auth.inte.team — OAuth client must be registered in SSO admin) ──────
if [[ -n "$DOMAIN" ]]; then
  set_env "SSO_URL" "https://auth.inte.team"
  _SSO_CLIENT="$(grep "^SSO_CLIENT_ID=" .env | cut -d= -f2 || true)"
  [[ -z "$_SSO_CLIENT" ]] && warn "SSO_CLIENT_ID is empty — register this app in SSO admin (/admin/clients) and set it in .env"
  _SSO_SECRET="$(grep "^SSO_CLIENT_SECRET=" .env | cut -d= -f2 || true)"
  [[ -z "$_SSO_SECRET" ]] && warn "SSO_CLIENT_SECRET is empty — copy it from SSO admin and set it in .env"
fi

ok ".env configured"

chown 1000:1000 .env
chmod 664 .env
ok ".env ownership set to UID 1000 (www)"

# ─────────────────────────────────────────────────────────────────────────────
step "6" "Production Docker Compose override (MariaDB tmp fix)"
# ─────────────────────────────────────────────────────────────────────────────
cat > docker-compose.prod-fix.yml <<'YAML'
# AUTO-GENERATED by deploy.sh — do not edit manually.
services:
  mariadb:
    command: ["sh", "-c", "chmod 1777 /tmp && exec docker-entrypoint.sh mariadbd"]
YAML
ok "docker-compose.prod-fix.yml written"

# ─────────────────────────────────────────────────────────────────────────────
step "7" "Docker proxy-tier network"
# ─────────────────────────────────────────────────────────────────────────────
if ! docker network inspect proxy-tier &>/dev/null; then
  docker network create proxy-tier
  ok "Created external Docker network: proxy-tier"
else
  ok "Docker network 'proxy-tier' already exists"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "8" "Build images & start containers"
# ─────────────────────────────────────────────────────────────────────────────
DC_CMD=(docker compose -f docker-compose.yml -f docker-compose.prod-fix.yml)

if [[ "$SSL_METHOD" == "caddy" && -n "$DOMAIN" ]]; then
  info "Starting with Caddy (--profile prod, auto Let's Encrypt SSL)..."
  "${DC_CMD[@]}" --profile prod up -d --build
else
  info "Starting without Caddy (nginx exposed on :${APP_PORT}, suitable for NPM)..."
  "${DC_CMD[@]}" up -d --build
fi
ok "All containers started"

info "Waiting for MariaDB to be ready..."
for i in $(seq 1 30); do
  if docker compose exec -T mariadb sh -c \
      'mariadb-admin ping -h 127.0.0.1 --silent 2>/dev/null || mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null'; then
    ok "MariaDB is ready"; break
  fi
  sleep 2
  [[ $i -eq 30 ]] && die "MariaDB did not become ready in 60 s — check: docker compose logs mariadb"
done

# ─────────────────────────────────────────────────────────────────────────────
step "9" "Container post-start fixes"
# ─────────────────────────────────────────────────────────────────────────────
info "Fixing php-fpm home/.config permissions..."
docker compose exec -T -u root php-fpm sh -c \
  "mkdir -p /home/www/.config && chown -R 1000:1000 /home/www/.config storage bootstrap/cache" \
  2>/dev/null || warn "php-fpm permission fix skipped"
ok "php-fpm permissions set"

info "Setting Nginx upload limit to 100M..."
docker compose exec -T nginx sh -c \
  "printf 'client_max_body_size 100M;\n' > /etc/nginx/conf.d/z_uploads.conf && nginx -s reload"
ok "Nginx upload limit set (100M)"

# ─────────────────────────────────────────────────────────────────────────────
step "10" "Laravel bootstrap"
# ─────────────────────────────────────────────────────────────────────────────
info "Installing Composer dependencies..."
docker compose exec -T php-fpm composer install --no-dev --optimize-autoloader
ok "Composer dependencies installed"

_APP_KEY="$(grep "^APP_KEY=" .env | cut -d= -f2 || true)"
if [[ -z "$_APP_KEY" ]]; then
  info "Generating APP_KEY..."
  docker compose exec -T php-fpm php artisan key:generate --force
  ok "APP_KEY generated"
else
  ok "APP_KEY already set"
fi

if [[ "$FRESH" == "true" ]]; then
  warn "Running migrate:fresh --seed (all data will be wiped)..."
  docker compose exec -T php-fpm php artisan migrate:fresh --seed --force
  ok "Fresh migration + seed complete"
else
  info "Running migrations..."
  docker compose exec -T php-fpm php artisan migrate --force
  ok "Migrations complete"
fi

docker compose exec -T php-fpm php artisan storage:link 2>/dev/null || true
ok "Storage symlink created"

if [[ -n "$DOMAIN" ]]; then
  info "Warming Laravel caches..."
  docker compose exec -T php-fpm php artisan config:cache
  docker compose exec -T php-fpm php artisan route:cache
  docker compose exec -T php-fpm php artisan view:cache
  ok "Laravel caches warmed"
fi

# ─────────────────────────────────────────────────────────────────────────────
step "11" "Frontend asset build"
# ─────────────────────────────────────────────────────────────────────────────
info "Building frontend assets via Vite (this may take 1-2 min)..."
docker run --rm \
  --env-file .env \
  -v "$(pwd)":/var/www \
  -w /var/www \
  node:22-alpine \
  sh -c "npm ci --silent && npm run build"

rm -f public/hot

if [[ ! -f "public/build/manifest.json" ]]; then
  die "Frontend build FAILED — public/build/manifest.json not found"
fi
ok "Frontend built (public/build/manifest.json present)"

# ─────────────────────────────────────────────────────────────────────────────
step "12" "Health check"
# ─────────────────────────────────────────────────────────────────────────────
sleep 3
_CHECK_PORT="$APP_PORT"
[[ "$SSL_METHOD" == "caddy" && -n "$DOMAIN" ]] && _CHECK_PORT=80

_HTTP="$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${_CHECK_PORT}/" 2>/dev/null || echo "000")"
if [[ "$_HTTP" == "200" || "$_HTTP" == "302" || "$_HTTP" == "301" ]]; then
  ok "App is responding (HTTP $_HTTP on port $_CHECK_PORT)"
else
  warn "App returned HTTP $_HTTP on port $_CHECK_PORT"
  warn "Check logs: docker compose logs nginx && docker compose logs php-fpm"
fi

# ── Summary ──────────────────────────────────────────────────────────────────
_SERVER_IP="$(curl -s --connect-timeout 3 ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')"

echo ""
echo -e "${BOLD}${GREEN}════════════════════════════════════════════════════${RESET}"
echo -e "${BOLD}${GREEN}   inteteam-garage deployed!${RESET}"
echo -e "${BOLD}${GREEN}════════════════════════════════════════════════════${RESET}"
echo ""

if [[ "$SSL_METHOD" == "caddy" && -n "$DOMAIN" ]]; then
  echo -e "  ${BOLD}App URL :${RESET}  https://$DOMAIN"
  echo -e "  ${BOLD}SSL     :${RESET}  Caddy auto (Let's Encrypt)"
  echo ""
  echo -e "  ${CYAN}DNS must point  $DOMAIN → $_SERVER_IP${RESET}"

elif [[ "$SSL_METHOD" == "npm" ]]; then
  echo -e "  ${BOLD}App (behind NPM) :${RESET}  http://${_SERVER_IP}:${APP_PORT}"
  echo ""
  echo -e "${BOLD}${YELLOW}  ── Nginx Proxy Manager setup ──────────────────────────────${RESET}"
  echo -e "  Add a Proxy Host in NPM:"
  echo ""
  echo -e "  ${BOLD}1. Main app${RESET}"
  echo -e "     Domain names    : ${DOMAIN:-yourdomain.com}"
  echo -e "     Scheme          : http"
  echo -e "     Forward Hostname: ${_SERVER_IP}"
  echo -e "     Forward Port    : ${APP_PORT}"
  echo -e "     ✓ Block Common Exploits"
  echo -e "     SSL Tab → Request Let's Encrypt cert"
  echo -e "     SSL Tab → ✓ Force SSL  ✓ HTTP/2"
  echo ""
  echo -e "  ${BOLD}2. After deploy, configure SSO in .env:${RESET}"
  echo -e "     SSO_URL=https://auth.inte.team"
  echo -e "     SSO_CLIENT_ID=<from SSO admin /admin/clients>"
  echo -e "     SSO_CLIENT_SECRET=<from SSO admin>"
  echo -e "     Then: docker compose exec php-fpm php artisan config:cache"
fi

echo ""
echo -e "${BOLD}  Useful commands:${RESET}"
echo -e "    docker compose logs -f                  # tail all services"
echo -e "    docker compose logs -f php-fpm          # Laravel app log"
echo -e "    docker compose logs -f queue-worker     # Queue job log"
echo -e "    docker compose exec php-fpm php artisan tinker"
echo -e "    docker compose ps                       # container status"
echo ""
echo -e "${BOLD}  Production update (after git pull):${RESET}"
echo -e "    git pull origin main"
echo -e "    docker compose exec -u www php-fpm composer install --no-dev --optimize-autoloader"
echo -e "    docker compose exec -u www php-fpm php artisan migrate --force"
echo -e "    docker compose exec -u www php-fpm php artisan optimize:clear"
echo -e "    docker run --rm --env-file .env -v \$(pwd):/var/www -w /var/www node:22-alpine sh -c 'npm ci --silent && npm run build'"
echo -e "    rm -f public/hot"
echo -e "    docker compose restart queue-worker"
echo ""
echo -e "  App dir : $APP_DIR"
echo ""
