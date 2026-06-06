# 013 — job_state_transitions

**Migration file:** `database/migrations/20260520200510_create_job_state_transitions_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models (companion to `JobStateMachine`)

## Purpose

Append-only log of every state change on a `RepairJob`. One row per `JobStateMachine::transition()` call, written from inside the service after the guarded state mutation succeeds. The `CheckJobTimeouts` command uses this table to find jobs that have been in a "waiting" state for too long.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete |
| `from_state` | string | no | — | Previous `RepairJob::state` value |
| `to_state` | string | no | — | New `RepairJob::state` value |
| `transitioned_by` | string | yes | — | `User::id` of the mechanic, `'system'` for auto-transitions, NULL if unauthenticated context |
| `occurred_at` | timestamp | no | — | Server-side wall clock |

**No `created_at`/`updated_at`** and **no `SoftDeletes`** — append-only audit pattern, same as `approval_events`.

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `job_state_transitions_garage_id_job_id_occurred_at_index` | `(garage_id, job_id, occurred_at)` | Per-job history; used by `CheckJobTimeouts` to find the latest transition |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit `constrained('repair_jobs')`

## Relationships

```
RepairJob ──hasMany──► JobStateTransition
```

`RepairJob::stateTransitions()` orders by `occurred_at` ascending — chronological state history.

## Model Configuration

- Class: `App\Models\JobStateTransition` (final)
- Traits: `HasUlids`, `HasGarageScope` (no `HasFactory`, no `SoftDeletes`)
- Policy: **none** — service-only writes, no controller-level operations
- `$fillable`: `garage_id, job_id, from_state, to_state, transitioned_by, occurred_at`
- `casts()`: `occurred_at => datetime`

## Constraints & Invariants

- Only `JobStateMachine::transition()` writes to this table. Controllers never call `JobStateTransition::create()` directly.
- `from_state` and `to_state` are not enum-constrained at the DB layer — validated by `JobStateMachine::TRANSITIONS` map at write time.
- `transitioned_by` accepts NULL because some triggers (system, webhooks, scheduled commands) lack a `User`.

## Deviations from playbook

- **No `RESTRICT` on FK delete.** Same intentional cascade as `approval_events` — deleting a garage clears its history.

## Related Migrations

- `001_garages`, `004_repair_jobs` — parents
- `012_approval_events` — companion audit table, similar shape
