# 008 — notification_preferences

**Migration file:** `database/migrations/20260520200436_create_notification_preferences_table.php`
**Added:** 2026-05-20
**Phase:** 1 (table) / 5 (full notification flow)

## Purpose

Per-job override of how the customer wants to be notified for that specific repair (email / sms / in_app). Defaulted from `Garage.default_notification_channel` at job creation, and adjustable by the customer via the portal or by an admin from the dashboard. Every change is expected to append to `ApprovalEvent` (Phase 5).

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete — `unique` (one prefs row per job) |
| `channel` | string | no | `email` | Values from `Garage::CHANNELS` |
| `set_by` | string | no | `admin` | `admin` or `customer` — drives audit context |
| `created_at`, `updated_at` | timestamp | yes | — | |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `notification_preferences_job_id_unique` | `(job_id)` | At most one preferences row per repair job |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit `constrained('repair_jobs')`

## Relationships

```
RepairJob ──hasOne──► NotificationPreference
```

## Model Configuration

- Class: `App\Models\NotificationPreference` (final)
- Traits: `HasUlids`, `HasGarageScope`, `HasFactory` — **no `SoftDeletes`** (preference is overwritten in place, not soft-deleted)
- Policy: **none** (no `#[UsePolicy]`)
- `$fillable`: `garage_id, job_id, channel, set_by`
- No timestamps cast — defaults are sufficient
- **No constants on this model.** Channel values reuse `Garage::CHANNELS`; `set_by` values are bare strings (`'admin'`, `'customer'`).

## Constraints & Invariants

- `(job_id)` unique — enforced at DB level via the unique index.
- `channel` must be a member of `Garage::CHANNELS` — should be validated at the form-request layer.
- `set_by` must be `admin` or `customer` — should be validated at the form-request layer; consider promoting to constants on this model.
- Every change (initial creation, customer override, admin override) is expected to be appended to `ApprovalEvent`. Phase 5 task: wire `PortalPreferenceController` and admin override endpoint via `ApprovalEventService`.
- Seeded from `Garage.default_notification_channel` on job creation — **not implemented yet** (Phase 5 task).

## Deviations from playbook

- **Missing `idx_garage_created` and `idx_garage_status` equivalents.** Only `(job_id)` is indexed. This is acceptable for a 1-1 table — every read happens by `job_id`. Skip the playbook's `(garage_id, ...)` indexes here.
- **No constants for `set_by`.** Risk of typos. Suggestion: add `NotificationPreference::SET_BY_ADMIN = 'admin'` and `SET_BY_CUSTOMER = 'customer'`.

## Related Migrations

- `001_garages`, `004_repair_jobs` — parents
- `012_approval_events` — every change to this row should append an event there
