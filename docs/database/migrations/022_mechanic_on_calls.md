# 022 — mechanic_on_calls

**Migration file:** `database/migrations/20260608000007_create_mechanic_on_calls_table.php`
**Added:** 2026-06-08
**Phase:** 5 — Notifications & Preferences (staff side)

## Purpose

On-call rotation table. Used by `CheckJobTimeouts` when `Garage.timeout_reminder_policy = 'on_call'` to route timeout alerts to whichever mechanic is currently on duty.

A rotation is a time-bound assignment of one mechanic to the garage's on-call slot. Overlapping rotations are allowed at the DB level (no exclusive constraint) — the resolver picks the first match; admin UI can enforce non-overlap on top.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID FK → `garages.id` | no | — | `cascadeOnDelete` |
| `mechanic_id` | ULID FK → `mechanics.id` | no | — | `cascadeOnDelete` |
| `starts_at` | timestamp | no | — | Shift start (inclusive) |
| `ends_at` | timestamp | no | — | Shift end (inclusive) |
| `created_at`, `updated_at` | timestamp | yes | — | |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `mechanic_on_calls_garage_id_starts_at_index` | `(garage_id, starts_at)` | Range queries |
| `mechanic_on_calls_garage_id_ends_at_index` | `(garage_id, ends_at)` | Range queries |
| `mechanic_on_calls_garage_id_created_at_index` | `(garage_id, created_at)` | Playbook `idx_garage_created` (every tenant-scoped table) |

## Lookup

```php
MechanicOnCall::withoutGlobalScopes()
    ->where('garage_id', $garage->id)
    ->where('starts_at', '<=', now())
    ->where('ends_at', '>=', now())
    ->first();
```

Helper: `MechanicOnCall::coversNow(): bool`.

## Fallback when empty

When no row covers `now()` and the garage policy is `on_call`, `CheckJobTimeouts::resolveTimeoutRecipients()` broadcasts to all assigned mechanics on the job. Poka-Yoke contract: gaps in the on-call schedule must not drop alerts.

## Related decisions

- **Phase 5 — RESOLVED 2026-06-08.** See memory `garage-phase5-deferred.md` §"Timeout reminder policy".

## Related migrations

- `001_garages` — parent table
- `002_mechanics` — `mechanic_id` FK target
- `021_garages_staff_settings` — provides `timeout_reminder_policy` that drives use of this table
