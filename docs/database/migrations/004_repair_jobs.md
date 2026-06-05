# 004 — repair_jobs

**Migration file:** `database/migrations/20260520200432_create_repair_jobs_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models

## Purpose

The central aggregate of the garage domain. Holds the lifecycle state of one repair, its vehicle, its assigned mechanics (via pivot), its estimates, stages, audit events, portal token, handover inspection, payment status, and notification preferences. Every customer-visible action is rooted on a `RepairJob.id`.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `vehicle_id` | ULID | no | — | FK → `vehicles.id` cascadeOnDelete |
| `state` | string | no | `RepairJob::STATE_CREATED` (`created`) | One of `RepairJob::STATES` |
| `payment_reference` | string | yes | — | Returned by `CrmPaymentService::requestPayment()` |
| `payment_confirmed_at` | dateTime | yes | — | Set by `PaymentWebhookController` |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `repair_jobs_garage_id_state_index` | `(garage_id, state)` | Per-tenant state-filtered dashboards |
| `repair_jobs_garage_id_created_at_index` | `(garage_id, created_at)` | Per-tenant recency lists |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `vehicle_id` → `vehicles.id` (ULID, `cascadeOnDelete`)

## Relationships

```
Garage ──hasMany──► RepairJob ──belongsTo──► Vehicle
                          │
                          ├─hasMany──► JobStage
                          ├─hasMany──► Estimate (current = highest revision_number)
                          ├─hasMany──► ApprovalEvent
                          ├─hasMany──► JobStateTransition
                          ├─hasOne───► SignedPortalToken (latest)
                          ├─hasOne───► NotificationPreference
                          ├─hasOne───► HandoverInspection
                          └─belongsToMany──► Mechanic (via repair_job_mechanic)
```

## State Machine

10-state lifecycle, transitions enforced by `App\Services\JobStateMachine`:

```
created → booked → in_progress → awaiting_approval → approved → completed → awaiting_collection → collected
                                       ↓        ↑
                                customer_query  │
                                       ↓        │
                                  scope_change ─┘
```

All constants on `RepairJob`:
`STATE_CREATED, STATE_BOOKED, STATE_IN_PROGRESS, STATE_AWAITING_APPROVAL, STATE_CUSTOMER_QUERY, STATE_SCOPE_CHANGE, STATE_APPROVED, STATE_COMPLETED, STATE_AWAITING_COLLECTION, STATE_COLLECTED`.

Key guards (see `JobStateMachine`):
- `in_progress → awaiting_approval` requires at least one line item
- `approved → completed` requires all line items resolved
- `awaiting_collection → collected` requires `HandoverInspection` submitted AND (payment confirmed OR `garage.online_payment_enabled = false`)

Every transition appends a row to `job_state_transitions` (see `013_job_state_transitions`).

## Model Configuration

- Class: `App\Models\RepairJob` (final)
- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes`, `HasFactory`
- Policy: `#[UsePolicy(RepairJobPolicy::class)]`
- `$fillable`: `garage_id, vehicle_id, state, payment_reference, payment_confirmed_at` ⚠ see Deviations
- `casts()`: `payment_confirmed_at => datetime`

## Constraints & Invariants

- `state` is the single source of truth for lifecycle; all writes go through `JobStateMachine::transition()`.
- `payment_confirmed_at` is written only by `PaymentWebhookController` after CRM payment confirmation.
- All mutations cascade-on-delete: deleting a `Vehicle` or `Garage` removes its jobs (and onwards via cascades through estimates, line items, stages, etc.).

## Deviations from playbook

- **`state` IS in `$fillable`.** `docs/features/garage-core/architecture.md` explicitly states:
  > `Job.state` is NOT in `$fillable` — it can only be changed via `JobStateMachine::transition()`.
  Current `App\Models\RepairJob::$fillable` includes `state`. This means a mass-assignment via `RepairJob::update($request->validated())` could bypass the state machine. **Action:** remove `state` from `$fillable` and route all state writes through `JobStateMachine::transition()`. Either fix in code, or update `architecture.md` to reflect the actual policy.

## Related Migrations

- `001_garages`, `003_vehicles` — parents
- `005_estimates`, `006_job_stages`, `007_line_items`, `008_notification_preferences`, `009_repair_job_mechanic` — direct children
- `010_media`, `011_signed_portal_tokens`, `012_approval_events`, `013_job_state_transitions`, `014_handover_inspections` — transitively dependent
