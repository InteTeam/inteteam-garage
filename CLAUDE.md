# inteteam-garage

## Session Setup

```bash
git config user.name "piotrfx" && git config user.email "shopscot@gmail.com"
```

## Project Overview

Garage management module for independent mechanics. Manages the full vehicle repair lifecycle — diagnostics, media evidence, estimates, customer approvals, handover, and optional online payment. Integrates with `inteteam_crm` for customer identity, bookings, and notifications. Customers access a signed portal link (no SSO required).

**Stack:** Laravel 13 + PHP 8.3 + React 19 + TypeScript + Inertia.js + MariaDB + Redis + GCS

## Docker Commands

```bash
# Start dev environment
docker compose --profile dev up -d

# PHP artisan
docker compose exec php-fpm php artisan <command>

# Tests (always clear config first)
docker compose exec php-fpm php artisan config:clear && php artisan test

# Code quality
docker compose exec php-fpm ./vendor/bin/pint --dirty
docker compose exec php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M

# Migrations
docker compose exec php-fpm php artisan migrate

# Frontend
docker compose run --rm npm sh -c "npm run build"
docker compose run --rm npm sh -c "npm run dev -- --host"
```

## Dev URLs
- Application: http://localhost
- Vite HMR: http://localhost:5174
- phpMyAdmin: http://localhost:8082
- Mailpit: http://localhost:8026

## Architecture

### Multi-Tenancy
All tenant models use `HasGarageScope` (mirrors CRM `HasCompanyScope`, scoped to `garage_id`).
Resolved from `session('current_garage_id')` or the authenticated mechanic's garage.

### Backend Layers
- **Controllers** — thin, delegate to services, authorize via policies
- **Services** — business logic per domain
- **Policies** — authorization via `#[UsePolicy]` attribute on models
- **Form Requests** — Store + Update pairs

### Key Custom Services
| Service | Purpose |
|---|---|
| `GcsService` | GCS upload, signed URL generation, object naming |
| `JobStateMachine` | State transition enforcement + transition log |
| `ApprovalEventService` | Append-only audit log writes |
| `SignedPortalTokenService` | Token generation, validation, regeneration |
| `CrmApiService` | CRM API wrapper (customer lookup, notification trigger) |
| `CrmPaymentService` | CRM payment request initiation + webhook handling |
| `TranslationService` | LLM translation with glossary context |

### Frontend
React 19 + Inertia.js + Tailwind v4 + shadcn/ui. Pages in `resources/js/Pages/`.
Layouts: `GarageLayout` (mechanic dashboard), `PortalLayout` (customer-facing, no auth).

### Route Files
- `routes/web.php` — mechanic dashboard (auth + garage middleware)
- `routes/portal.php` — customer portal (signed token middleware, no SSO)
- `routes/api.php` — internal API endpoints
- `routes/console.php` — scheduled commands

## Code Conventions
- Always `declare(strict_types=1);`
- PHP 8 constructor property promotion
- Explicit return types on all methods
- ULIDs for primary keys (`HasUlids`)
- Casts in `casts()` method, not `$casts` property
- Job constants defined on the `Job` model (e.g. `Job::STATE_IN_PROGRESS`)
- PHPDoc for array shapes and complex types

## Before Any Code Change
Follow the routing table in `inte-playbook/workflow/README.md`.
Feature docs live in `docs/features/`.
Migration docs in `docs/database/migrations/` (sequential NNN_ numbering).

## Pre-Commit Quality Gate
```bash
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan test
docker compose exec php-fpm ./vendor/bin/pint --dirty
docker compose exec php-fpm ./vendor/bin/phpstan analyse --memory-limit=512M
docker compose run --rm npm sh -c "npm run build"
```

## Key Docs
- `docs/planning.md` — domain model, state machine, i18n strategy, Poka Yoke design
- `docs/tasks.md` — phased implementation checklist
- `docs/features/garage-core/` — SOP feature docs (created in Phase 0)
- `docs/database/migrations/` — migration documentation
