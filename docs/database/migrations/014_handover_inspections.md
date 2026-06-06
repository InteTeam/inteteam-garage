# 014 — handover_inspections

**Migration file:** `database/migrations/20260520200515_create_handover_inspections_table.php`
**Added:** 2026-05-20
**Phase:** 4 — Customer Handover & Online Payment

## Purpose

One row per job, written when the customer submits the handover checklist via the portal at `POST portal/{token}/handover`. The presence of a row is one of the two gates `JobStateMachine::guardCollected()` checks before allowing `awaiting_collection → collected` (the other gate is `payment_confirmed_at` when `Garage.online_payment_enabled`). Effectively immutable after write — the table's `unique(job_id)` enforces single-write semantics.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete · **unique** |
| `submitted_by_token` | string | no | — | The `SignedPortalToken::id` (ULID) that the customer used. Identifies which token-bearing customer submitted. |
| `submitted_at` | dateTime | no | — | Server-side wall clock at submission |
| `created_at`, `updated_at` | timestamp | yes | — | |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `handover_inspections_job_id_unique` | `(job_id)` | Enforces one handover per job — second `POST` returns 422 from controller, hard guard at DB |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit `constrained('repair_jobs')`

## Relationships

```
RepairJob ──hasOne──► HandoverInspection ──hasMany──► HandoverItem (one per LineItem)
```

## Model Configuration

- Class: `App\Models\HandoverInspection` (final)
- Traits: `HasUlids`, `HasGarageScope`, `HasFactory`
- Policy: **none** — token-bound write through `PortalHandoverController@submit`; reads through portal endpoint scoped by token
- `$fillable`: `garage_id, job_id, submitted_by_token, submitted_at`
- `casts()`: `submitted_at => datetime`

## Constraints & Invariants

- `(job_id)` is **unique** — a second submission for the same job fails at the DB layer (and is rejected earlier with 422 by `PortalHandoverController@submit`).
- The matching `HandoverItem` rows must be created atomically with this row — see `015_handover_items.md`. The portal controller wraps both in a `DB::transaction()`.
- `submitted_by_token` is a snapshot value, not an FK — if the token row is rotated/deleted later we still want to know who submitted.

## Deviations from playbook

- **No `idx_garage_created`.** Tenant-time listings of handovers are not a current use case (we list jobs, not handovers). Add if a "recent handovers" admin view ships.

## Related Migrations

- `001_garages`, `004_repair_jobs` — parents
- `015_handover_items` — child rows
