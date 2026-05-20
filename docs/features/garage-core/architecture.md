# Architecture — Garage Core

## System Boundary

inteteam-garage is staff-facing only. Customers interact via the customer portal service (store_front).
inteteam-garage exposes a token-scoped read API for the portal; the portal handles all customer-facing UI.

## Multi-Tenancy

- Tenant root: `Garage` model. No `HasGarageScope` on itself.
- All other tenant-owned models: `HasGarageScope` (global scope on `garage_id`)
- Session key: `current_garage_id`
- `GarageMiddleware` resolves the current garage from the authenticated mechanic

## State Machine

`JobStateMachine` service holds the transition map and guard map.
`Job.state` is NOT in `$fillable` — it can only be changed via `JobStateMachine::transition()`.
Every transition appends to `JobStateTransition` (append-only, server timestamp).

## Audit Log

`ApprovalEventService::record(Job $job, string $eventType, array $payload, string $actorType, string $actorId)`
All writes go through this method. The table is append-only.

## GCS Object Naming

`{job_id}/{stage_slug}/{unix_timestamp}_{original_filename}`
Stage slug: pre-inspection, disassembly, fault-found, repair, complete

## Portal Token

`SignedPortalToken` model: `job_id`, `token` (ULID), `expires_at`, `revoked_at`
`PortalTokenMiddleware` resolves token from route param, checks expiry + revocation, binds `$job` to route.

## CRM Integration

All CRM calls go through `CrmApiService`. Auth via shared secret (`CRM_API_SECRET` header).
- `GET /api/v1/internal/customers/{crm_customer_id}` — fetch customer for portal display
- `POST /api/v1/internal/notifications` — trigger notification via CRM channels
- `POST /api/v1/internal/payments/requests` — create payment request (online payment flow)

## Translation

`TranslationService::translate(string $text, string $from, string $to, string $context = 'general')`
Context is `'estimate'` for line items with price (triggers preview flow) or `'general'` for auto-translate.
Glossary loaded from `database/seeders/AutomotiveGlossarySeeder.php`.

## Frontend Layout Strategy

- `GarageLayout` — mechanic/admin dashboard (authenticated, full nav)
- `PortalLayout` — customer portal (no auth, minimal branding shell, garage logo future)
