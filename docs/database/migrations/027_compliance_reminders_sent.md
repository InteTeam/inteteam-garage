# 027 — compliance_reminders_sent

**Migration file:** `database/migrations/20260619000004_create_compliance_reminders_sent_table.php`
**Added:** 2026-06-19
**Phase:** Compliance — MOT / Road Tax / Insurance lifecycle

## Purpose

Per-dispatch audit row for the compliance reminder scheduler, AND the dedup mechanism. The unique index is the contract: the same `(compliance_record, window_days, recipient_type, recipient_id)` tuple can never appear twice, so a hammered scheduler or a retried job cannot double-notify a customer.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | `ulid` PK | no | — | |
| `garage_id` | `ulid` FK → `garages.id` | no | — | `cascadeOnDelete`. |
| `vehicle_id` | `ulid` FK → `vehicles.id` | no | — | `cascadeOnDelete`. |
| `compliance_record_id` | `ulid` FK → `compliance_records.id` | no | — | `cascadeOnDelete`. The dedup is anchored to the **specific compliance record**, not the (vehicle, type) pair. A new MOT booking writes a new `compliance_records` row, which is a new dedup key, which is its own reminder cycle. |
| `type` | `string(16)` | no | — | Denormalised `ComplianceType` enum value — saves a join on read, harmless on append-only writes. |
| `channel` | `string(16)` | no | — | The channel actually used at dispatch time (resolved from garage settings). |
| `recipient_type` | `string(16)` | no | — | `'customer'` or `'mechanic'`. |
| `recipient_id` | `string(64)` | no | — | CRM customer ID (when `recipient_type='customer'`) or CRM user ID (when `'mechanic'`). String, not FK — recipients live in the CRM, not this DB. |
| `window_days` | `unsigned smallint` | no | — | Which configured window matched (e.g. 30, 7). Lets us answer "did we send the 30-day notice?" cheaply. |
| `sent_at` | `timestamp` | no | — | The canonical timestamp — set once on insert. Mutated only via the error-path UPDATE in `ComplianceReminderService` and even then `sent_at` itself stays put (only `error` changes). |
| `error` | `text` | yes | `null` | If `CrmApiService` threw, the row is still written first (the unique constraint reserves the dedup slot) and then `error` is updated with the truncated exception message. Treats failed dispatches as a real attempt — operators looking at this table see the full picture. |

No `created_at` / `updated_at` pair — `sent_at` IS the row's timestamp. Adding `timestamps()` would duplicate it and pretend the row is generic-mutable; model carries `public $timestamps = false;`. Same pattern as `job_state_transitions`.

### Indexes

- `unique(['compliance_record_id', 'window_days', 'recipient_type', 'recipient_id'])` named `compliance_reminders_dedup_idx` — the dedup contract.
- `(garage_id, sent_at)` named `idx_compliance_reminders_sent_garage_sent` — tenant-scoped reads (`HasGarageScope` adds `WHERE garage_id = ?` to every query). Per playbook `DATABASE_CONVENTIONS.md`.

## Dispatch sequence (`ComplianceReminderService::sendIfNotDeduped`)

1. `INSERT` the dedup row first. If the unique index fires (`QueryException`), return `'skipped'` and abandon. This is the dedup gate.
2. Build the subject + body in the garage locale.
3. Call `CrmApiService::sendNotification` (customer) or `sendStaffNotification` (mechanic).
4. On success, return `'sent'`. On exception, update the row's `error` column and return `'error'`.

The insert-first ordering matters: it means a CRM 500 cannot cause two notifications (the row is already in the table holding the dedup slot), and a duplicate scheduled run hits the unique constraint before the CRM call.

## Why store `recipient_id` as a string

CRM customer IDs and CRM user IDs are different identifier spaces, both living outside our DB. A real FK isn't possible without joining across services. The `recipient_type + recipient_id` pair is sufficient to identify the human and feeds the unique key.

## Related migrations

- `025_compliance_records.md` — parent record.
- `026_garages_compliance_reminder_settings.md` — what drives dispatch.
