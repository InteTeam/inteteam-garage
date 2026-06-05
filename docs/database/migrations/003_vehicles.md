# 003 — vehicles

**Migration file:** `database/migrations/20260520200430_create_vehicles_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models

## Purpose

A vehicle on which a repair job can be raised. The customer-identity tail (name, phone, email) lives in the CRM — vehicles here carry only `crm_customer_id` and the physical descriptors a mechanic needs (registration, make, model, year, colour). No PII duplication.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `crm_customer_id` | string | no | — | Opaque ID from `inteteam_crm`; not an FK (cross-system) |
| `registration` | string | no | — | UK plate; case sensitivity not enforced at DB level |
| `make` | string | no | — | e.g. `Ford` |
| `model` | string | no | — | e.g. `Focus` |
| `year` | unsignedSmallInteger | yes | — | Cast to `integer` |
| `colour` | string | yes | — | Optional |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `vehicles_garage_id_registration_index` | `(garage_id, registration)` | Plate lookup within a garage |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `crm_customer_id` → **no DB FK** (cross-system reference to inteteam_crm). Resolution happens at runtime via `CrmApiService` (see `docs/features/garage-core/architecture.md`).

## Relationships

```
Garage ──hasMany──► Vehicle ──hasMany──► RepairJob
```

## Model Configuration

- Class: `App\Models\Vehicle` (final)
- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes`, `HasFactory`
- Policy: `#[UsePolicy(VehiclePolicy::class)]`
- `$fillable`: `garage_id, crm_customer_id, registration, make, model, year, colour`
- `casts()`: `year => integer`

## Constraints & Invariants

- Same plate may exist in multiple garages (no global unique constraint on `registration`).
- No uniqueness on `(garage_id, registration)` either — historically the same registration may be reissued; if business needs it, add a unique partial constraint excluding soft-deleted rows.
- Customer-side data (name, phone, email) must always be fetched live from CRM via `crm_customer_id` — never cached on this table.

## Deviations from playbook

- **Missing `idx_garage_created`.** Playbook requires `(garage_id, created_at)` on every tenant-scoped table. Only `(garage_id, registration)` is present. Action: add `(garage_id, created_at)` if the vehicles index page lists by creation time.

## Related Migrations

- `001_garages` — parent
- `004_repair_jobs` — references `vehicles.id`
