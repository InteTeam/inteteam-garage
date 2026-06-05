# 006 — job_stages

**Migration file:** `database/migrations/20260520200434_create_job_stages_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models (auto-lock on transition is Phase 2)

## Purpose

A repair job is broken into ordered visual stages — `pre-inspection`, `disassembly`, `fault-found`, `repair`, `complete` (`JobStage::STAGES`). Each stage owns the media uploaded during that part of the work. Once a stage is locked, no more media can be attached to it; the lock is intended to fire automatically when the job transitions past that stage (Phase 2 task — not yet implemented).

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete |
| `name` | string | no | — | Slug from `JobStage::STAGES` |
| `sort_order` | unsignedSmallInteger | no | — | Display order; cast to `integer` |
| `locked_at` | dateTime | yes | — | Set once → stage rejects further media |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `job_stages_garage_id_job_id_index` | `(garage_id, job_id)` | Per-job stage list, sorted by `sort_order` |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit `constrained('repair_jobs')`

## Relationships

```
RepairJob ──hasMany──► JobStage ──hasMany──► Media
```

`RepairJob::stages()` orders by `sort_order`.

## Model Configuration

- Class: `App\Models\JobStage` (final)
- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes`, `HasFactory`
- Policy: **none** (no `#[UsePolicy]` attribute on the model — authorization is via parent `RepairJob`)
- `$fillable`: `garage_id, job_id, name, sort_order, locked_at`
- `casts()`: `sort_order => integer`, `locked_at => datetime`
- Constants: `JobStage::STAGE_PRE_INSPECTION | STAGE_DISASSEMBLY | STAGE_FAULT_FOUND | STAGE_REPAIR | STAGE_COMPLETE`, list in `JobStage::STAGES`
- Helper: `isLocked(): bool`

## Constraints & Invariants

- `name` should be validated against `JobStage::STAGES` at the form-request layer.
- `(job_id, sort_order)` should be unique in practice but not enforced at DB level — add a unique constraint if duplicate orderings cause UI bugs.
- `(job_id, name)` should be unique in practice (one `repair` stage per job) — not enforced; relies on application-layer guards.
- `locked_at` is a one-way write — once set, never cleared (immutability). Enforced at service layer (`GcsService::upload()` checks `isLocked()`).
- **Auto-lock not implemented yet** — `JobStage` only locks via direct write; Phase 2 task is to wire `JobStateMachine` transitions to lock the corresponding stage.

## Deviations from playbook

- **No policy attached.** Other Phase 1 models all have `#[UsePolicy(...)]`. If admins/mechanics should have distinct rights to lock/unlock stages, attach `JobStagePolicy`. If authorization through the parent `RepairJob` is intentional, document that in `RepairJobPolicy`.
- **Missing `idx_garage_created`.** Only `(garage_id, job_id)` is present.

## Related Migrations

- `001_garages`, `004_repair_jobs` — parents
- `010_media` — children (stage-bound media)
