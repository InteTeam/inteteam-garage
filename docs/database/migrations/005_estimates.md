# 005 — estimates

**Migration file:** `database/migrations/20260520200433_create_estimates_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models (estimate flow logic itself lives in Phase 3)

## Purpose

An estimate is a versioned snapshot of work proposed to the customer. One repair job may have multiple estimates — each new revision is a fresh row with `revision_number` incremented. Once the customer has responded to an estimate (approve/decline on any line item), it is frozen; a new revision must be created to change scope. The "current" estimate for a job is the one with the highest `revision_number`.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete (note: column is `job_id`, not `repair_job_id`) |
| `revision_number` | unsignedSmallInteger | no | `1` | Cast to `integer`; new revision = previous + 1 |
| `sent_at` | dateTime | yes | — | Null until estimate is sent to customer |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `estimates_garage_id_job_id_index` | `(garage_id, job_id)` | Per-job estimate lookup, ordered by `revision_number` |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit table name in `constrained('repair_jobs')`

## Relationships

```
RepairJob ──hasMany──► Estimate ──hasMany──► LineItem
```

`RepairJob::currentEstimate()` uses `latestOfMany('revision_number')`.

## Model Configuration

- Class: `App\Models\Estimate` (final)
- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes`, `HasFactory`
- Policy: `#[UsePolicy(EstimatePolicy::class)]`
- `$fillable`: `garage_id, job_id, revision_number, sent_at`
- `casts()`: `revision_number => integer`, `sent_at => datetime`
- Helpers:
  - `hasCustomerResponse(): bool` — true if any line item is `approved` or `declined`
  - `allLineItemsResolved(): bool` — true if no line items remain in `pending`

## Constraints & Invariants

- An estimate with a customer response (`hasCustomerResponse() === true`) is **immutable** — a new revision must be created instead of editing existing line items. Enforced at service layer (`EstimateService`).
- `revision_number` is monotonically increasing per `job_id` — application-level invariant, not enforced at DB level.
- `sent_at` is a one-way write — once set, should not be cleared.

## Deviations from playbook

- **Column named `job_id` rather than `repair_job_id`.** Convention in the rest of the codebase uses `job_id` for the FK to `repair_jobs` (see `006_job_stages`, `007_line_items` indirectly, etc.). Consistent within this app, but worth flagging because the table is `repair_jobs` — the conventional Laravel name would be `repair_job_id`. The pivot `repair_job_mechanic` uses `repair_job_id`. Inconsistency: pivots use `repair_job_id`, owned children use `job_id`. Not a fix item — just note for newcomers.
- **Missing `idx_garage_created`.** Only `(garage_id, job_id)` is present. Action: add `(garage_id, created_at)` if estimate-history list views grow.

## Related Migrations

- `001_garages`, `004_repair_jobs` — parents
- `007_line_items` — children of this table
