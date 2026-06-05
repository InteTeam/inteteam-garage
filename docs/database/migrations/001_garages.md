# 001 — garages

**Migration file:** `database/migrations/20260520200424_create_garages_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models

## Purpose

Tenant root. Every other tenant-scoped model resolves to one row here via `garage_id`. A garage represents a single workshop with its own mechanics, vehicles, repair jobs, branding, locale, and payment preferences.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `name` | string | no | — | Display name |
| `slug` | string | no | — | URL slug — `unique` |
| `online_payment_enabled` | boolean | no | `false` | Toggles the payment gate in `JobStateMachine::guardCollected()` |
| `default_notification_channel` | string | no | `email` | Default for new `NotificationPreference` rows; values from `Garage::CHANNELS` |
| `locale` | string | no | `en` | Garage-side locale; drives translation pair resolution in `TranslationService` |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `garages_slug_unique` | `(slug)` | Slug uniqueness |

## Foreign Keys

None — tenant root.

## Relationships

```
Garage ──hasMany──► Mechanic
Garage ──hasMany──► RepairJob
(implicitly parent of every other HasGarageScope model)
```

## Model Configuration

- Class: `App\Models\Garage` (final)
- Traits: `HasUlids`, `SoftDeletes`, `HasFactory`
- **No `HasGarageScope`** — this is the tenant root, scoping itself would be circular
- Policy: `#[UsePolicy(GaragePolicy::class)]`
- `$fillable`: `name, slug, online_payment_enabled, default_notification_channel, locale`
- `casts()`: `online_payment_enabled => boolean`
- Constants: `Garage::CHANNEL_EMAIL | CHANNEL_SMS | CHANNEL_IN_APP`, list in `Garage::CHANNELS`

## Constraints & Invariants

- `slug` must be globally unique (no per-tenant scoping — slugs are global identifiers).
- `default_notification_channel` should be validated against `Garage::CHANNELS` at the form-request layer (not enforced at DB level; values are free-form strings in the migration).
- `online_payment_enabled` toggle changes are expected to be appended to `ApprovalEvent` log per `docs/tasks.md` Phase 4 (not yet implemented as of 2026-06-05).

## Deviations from playbook

None.

## Related Migrations

- All other Phase 1 migrations have `garage_id` FK pointing here.
- Any future garage-level setting (branding, billing config) extends this table or attaches via FK.
