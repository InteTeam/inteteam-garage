# 012 — approval_events

**Migration file:** `database/migrations/20260520200505_create_approval_events_table.php`
**Added:** 2026-05-20
**Phase:** 3 — Estimates & Customer Approval

## Purpose

Append-only audit log of everything that happens to a job that needs to be traceable for the customer, mechanic, or compliance team — line-item approvals/declines, customer questions, mechanic responses, estimate sends, scope changes, preference changes, handover submissions, payment events, timeout alerts. Single write path: `ApprovalEventService::record()` / `recordBySystem()`. Rows are never updated or deleted.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete |
| `actor_type` | string | no | — | `mechanic` / `customer` / `system` (see `ApprovalEvent::ACTOR_*`) |
| `actor_id` | string | no | — | `Mechanic::id` (ULID), `SignedPortalToken::id`, or `'system'` for scheduled events |
| `event_type` | string | no | — | One of `ApprovalEvent::EVENT_*` constants |
| `payload` | JSON | yes | — | Event-specific data (line item id, notes, scope diff, etc.) |
| `occurred_at` | timestamp | no | — | Server-side wall clock |

**No `created_at`/`updated_at`** — `$timestamps = false`. **No `SoftDeletes`** — append-only, never deleted. **No `id` autoincrement** — ULID. Matches playbook "Append-Only Audit Logs" rule.

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `approval_events_garage_id_job_id_occurred_at_index` | `(garage_id, job_id, occurred_at)` | Timeline view per job, chronological |
| `approval_events_garage_id_event_type_index` | `(garage_id, event_type)` | Audit queries — "all `_PAYMENT_CONFIRMED` events in garage X" |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit `constrained('repair_jobs')`

Both FK use `cascadeOnDelete` rather than playbook's recommended `RESTRICT` for audit logs — accepted deviation because deleting a garage or job in this product is a hard admin action that should clear its history. Document this in the admin SOP if/when delete UX ships.

## Relationships

```
RepairJob ──hasMany──► ApprovalEvent
```

`RepairJob::approvalEvents()` orders by `occurred_at` ascending — natural timeline order.

## Model Configuration

- Class: `App\Models\ApprovalEvent` (final)
- Traits: `HasUlids`, `HasGarageScope` (no `HasFactory`, no `SoftDeletes`)
- `$timestamps = false`
- `$fillable`: `garage_id, job_id, actor_type, actor_id, event_type, payload, occurred_at`
- `casts()`: `payload => array`, `occurred_at => datetime`
- Constants: `ACTOR_*` (3), `EVENT_*` (12)

## Constraints & Invariants

- All writes through `ApprovalEventService` — controllers and other services never call `ApprovalEvent::create()` directly.
- `payload` schema is event-type-specific and not validated at the DB layer. Add typed value objects in the service layer if downstream consumers grow brittle.
- `actor_id` is `string` (not FK-constrained) because actors are heterogeneous (mechanic ULID, portal token ULID, the literal `'system'`).

## Deviations from playbook

- **No `RESTRICT` on FK delete.** See Foreign Keys note above — `cascadeOnDelete` is intentional but worth flagging.

## Related Migrations

- `001_garages`, `004_repair_jobs` — parents
- `011_signed_portal_tokens` — common `actor_id` source for customer events
