# 002 — mechanics

**Migration file:** `database/migrations/20260520200430_create_mechanics_table.php`
**Added:** 2026-05-20
**Phase:** 1 — Multi-Tenancy & Core Models

## Purpose

A `Mechanic` is the per-garage profile of a staff member. It links a global `User` (SSO-authenticated) to a specific `Garage` with a role (`garage_admin` or `mechanic`). One `User` may have multiple `Mechanic` rows — one per garage they belong to.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `user_id` | unsignedBigInteger | no | — | Reference to `users.id` — **no FK constraint** (see Deviations) |
| `role` | string | no | `mechanic` | One of `Mechanic::ROLES` |
| `locale` | string(5) | yes | `null` | ISO 639-1 / `xx-XX`. Added via `016_mechanics_locale` (2026-06-08). `null` = inherit from `Garage.locale`. Resolution via `Mechanic::resolvedLocale()`. |
| `is_active` | boolean | no | `true` | Suspend without deleting |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `mechanics_garage_id_user_id_unique` | `(garage_id, user_id)` | One profile per user per garage |
| `mechanics_garage_id_is_active_index` | `(garage_id, is_active)` | Active-staff list queries |
| `mechanics_garage_id_created_at_index` | `(garage_id, created_at)` | Playbook-required `idx_garage_created` (added via `20260606000001_add_garage_created_indexes`) |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `user_id` → `users.id` (bigint, **not constrained** — see Deviations)

## Relationships

```
Garage ──hasMany──► Mechanic ──belongsTo──► User
                       │
                       └─belongsToMany──► RepairJob (via repair_job_mechanic pivot)
```

## Model Configuration

- Class: `App\Models\Mechanic` (final)
- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes`, `HasFactory`
- Policy: `#[UsePolicy(MechanicPolicy::class)]`
- `$fillable`: `garage_id, user_id, role, locale, is_active`
- `casts()`: `is_active => boolean`
- Constants: `Mechanic::ROLE_GARAGE_ADMIN`, `Mechanic::ROLE_MECHANIC`, list in `Mechanic::ROLES`
- Helpers: `isAdmin(): bool`, `resolvedLocale(): string`

## Constraints & Invariants

- `(garage_id, user_id)` unique — a `User` cannot have two profiles in the same garage.
- `role` should be validated against `Mechanic::ROLES` at the form-request layer.
- `HasGarageScope` filters by session `current_garage_id` automatically — Tinker queries must use `Mechanic::withoutGlobalScopes()`.

## Deviations from playbook

- **Missing FK constraint on `user_id`.** Migration uses `unsignedBigInteger('user_id')` instead of `foreignId('user_id')->constrained('users')`. Per `docs/DATABASE_CONVENTIONS.md` and `inte-playbook/laravel/DATABASE_CONVENTIONS.md`, FKs to `users` should be `foreignId(...)->constrained()`. Action: backfill the constraint in a follow-up migration, or document why the constraint was intentionally omitted (e.g. cross-DB user table from SSO).
- **Has `idx_garage_created` equivalent.** Added via `20260606000001_add_garage_created_indexes` after playbook audit. Playbook required `(garage_id, created_at)` on every tenant-scoped table.

## Related Migrations

- `001_garages` — parent
- `009_repair_job_mechanic` — pivot for many-to-many assignment to repair jobs
- `0001_01_01_000000_create_users_table` (framework default) — referenced `users.id`
