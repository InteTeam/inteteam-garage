# 025 — compliance_records

**Migration file:** `database/migrations/20260619000002_create_compliance_records_table.php`
**Added:** 2026-06-19
**Phase:** Compliance — MOT / Road Tax / Insurance lifecycle

## Purpose

Append-only history of compliance expiry dates per vehicle, covering MOT, Road Tax, and Insurance. Backs both the `Vehicles/Show` Compliance tab (latest-per-type "what's in force now") and the daily reminder dispatcher.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | `ulid` PK | no | — | `HasUlids` like sibling tenant tables. |
| `garage_id` | `ulid` FK → `garages.id` | no | — | `cascadeOnDelete` — drop the garage, drop the history. |
| `vehicle_id` | `ulid` FK → `vehicles.id` | no | — | `cascadeOnDelete`. |
| `recorded_by_user_id` | `unsigned bigint` FK → `users.id` | yes | `null` | `nullOnDelete`. Null when the row is written by the scheduled DVLA refresh or a system command (no acting user). |
| `type` | `string(16)` | no | — | `ComplianceType` enum (`mot` / `tax` / `insurance`). Stored as string, validated via Eloquent cast. |
| `source` | `string(16)` | no | `'manual'` | `ComplianceSource` enum (`manual` / `dvla` / `dvsa`). `dvsa` is reserved for a future MOT history integration; today only `manual` and `dvla` are produced. |
| `expires_on` | `date` | no | — | Date only — no timezone semantics for MOT/Tax/Insurance expiry. |
| `note` | `text` | yes | `null` | Free-form mechanic note (manual) or `"DVLA status: Valid"` style (DVLA path). |
| `created_at` / `updated_at` | `timestamp` | no | now | Standard. |

### Indexes

- `(vehicle_id, type, created_at)` — supports the "latest record per type" query that powers the Compliance tab and the reminder dispatcher. Composite, not unique: history is append-only, multiple rows of the same (vehicle, type) are expected.
- `(garage_id, created_at)` (named `idx_compliance_records_garage_created`) — tenant-scoped reads. `HasGarageScope` adds `WHERE garage_id = ?` to every query, so without this composite the engine would land on `vehicle_id` first and filter `garage_id` on the rows. Per playbook `DATABASE_CONVENTIONS.md`.

## Append-only semantics

We never `UPDATE` an existing row. To change an expiry date, the controller writes a new row; reads always take the latest `created_at`. This gives a clean audit trail ("Mechanic X said MOT expires Y on 2026-04-12, then DVLA corrected to 2026-04-14 on 2026-04-13") and removes any chance of losing history when a mechanic mis-types a date.

`VehicleComplianceService::applyDvlaResult()` adds a dedup layer on top: if the latest record's `expires_on` already equals the incoming DVLA value, the insert is skipped. So repeated DVLA refresh clicks do not pollute history with no-op rows.

## Insurance is manual

DVLA VES does not expose insurance policy data — that lives in MIB / askMID behind a paid contract. The `insurance` enum value is therefore only ever written with `source='manual'` today. If/when we bolt on an MIB integration, add the new `ComplianceSource` case and a sibling apply method on the service — the table needs no migration.

## Related migrations

- `024_vehicles_vin.md` — sibling.
- `026_garages_compliance_reminder_settings.md` — per-garage opt-in + dispatch tuning.
- `027_compliance_reminders_sent.md` — the dedup-by-unique-constraint audit table.
