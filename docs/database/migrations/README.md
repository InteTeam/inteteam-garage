# Migration Documentation

Sequential, append-only documentation of every garage-domain migration in `database/migrations/`. One markdown file per migration.

## Convention

- **Filename:** `NNN_{table_or_change}.md` — zero-padded 3-digit sequence number, snake_case suffix matching the migration.
- **Numbering is sequential by migration timestamp**, not by phase. New migrations get the next free number — never renumber existing files.
- **Find the next number:**
  ```bash
  ls docs/database/migrations/ | grep -E '^[0-9]{3}_' | sort -n | tail -1
  # If last is 016, next is 017
  ```
- Framework default migrations (`users`, `cache`, framework `jobs` queue table) are **not** documented here — only garage-domain tables.

## Template

```markdown
# NNN — {table or change}

**Migration file:** `database/migrations/{timestamp}_{name}.php`
**Added:** YYYY-MM-DD
**Phase:** {phase from docs/tasks.md}

## Purpose

One paragraph: what this table represents in the domain and why it exists.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| ... | | | | |
| `created_at` | timestamp | yes | — | |
| `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | SoftDeletes (omit if not used) |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `idx_{table}_garage_created` | `(garage_id, created_at)` | Tenant-scoped list queries |
| ... | | |

## Foreign Keys

Per `docs/DATABASE_CONVENTIONS.md`:
- `foreignId` for `users.id` (bigint)
- `foreignUlid` for everything else
- `string` for `roles.id`

## Relationships

```
ParentModel ──hasMany──► ThisModel
ThisModel ──belongsTo──► ParentModel
```

## Model Configuration

- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes` (where applicable)
- `casts()` method (NOT `$casts` property) — list non-trivial casts
- `$fillable` — list, or note if state column is excluded (e.g. `RepairJob.state` only mutable via `JobStateMachine`)
- Policy: `#[UsePolicy(XPolicy::class)]` attribute if applicable

## Constraints & Invariants

Any unique constraints, check constraints, or invariants enforced by the application layer that future readers should know about.

## Related Migrations

- Migrations that depend on this table
- Subsequent migrations that altered it (e.g. `016_add_customer_notes_to_line_items`)
```

## Why this layout

Source: `inte-playbook/laravel/README.md` Step 3 + `laravel/DATABASE_CONVENTIONS.md`. Keeps schema decisions discoverable without grepping migration files, surfaces FK and index choices, and enforces the tenant/ULID conventions per the playbook.
