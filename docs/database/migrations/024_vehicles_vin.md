# 024 — vehicles.vin

**Migration file:** `database/migrations/20260619000001_add_vin_to_vehicles_table.php`
**Added:** 2026-06-19
**Phase:** Compliance — MOT / Road Tax / Insurance lifecycle

## Purpose

The DVLA Vehicle Enquiry Service (VES) returns MOT + Road Tax expiry keyed off the registration number, but operationally mechanics want VIN on file too — VIN is the only globally unique identifier that survives a private-plate transfer, and several CRM use cases (warranty, recall lookups) consume it. Adding it now means the Compliance feature ships with a complete vehicle identity card; backfilling later is rote.

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `vin` | `string(17)` | yes | `null` | ISO 3779 VIN length; nullable because existing vehicles were created before this column existed and there is no automated source we trust to backfill them. |

No index — we do not look vehicles up by VIN today. Add one only when a feature actually queries on it.

## UI exposure

- `Vehicles/Form.tsx` — optional text input (uppercase hint, 17 char limit).
- `Vehicles/Show.tsx` Details tab — rendered with `font-mono` for legibility; falls back to `—` when null.

## Related migrations

- `20260520_create_vehicles_table.php` (initial) — parent table.
- `025_compliance_records.md` — sibling migration in this phase.
