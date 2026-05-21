# Database Conventions

## Primary Keys

All tables use ULIDs as primary keys via `HasUlids` trait and `$table->ulid('id')->primary()`.

## Foreign Keys

| FK references | Column type | Migration method |
|---|---|---|
| Another ULID model | `ulid` | `$table->foreignUlid('garage_id')->constrained('garages')->cascadeOnDelete()` |
| External CRM ID (string) | `string` | `$table->string('crm_customer_id')` — no DB constraint |

## Tenant Scoping

All tenant-owned tables have `garage_id` as a foreign key, not `company_id`.
The `HasGarageScope` trait applies automatically when a model uses it.

## Timestamps

All tables use `$table->timestamps()`. Soft-delete tables add `$table->softDeletes()`.

## Naming

- Tables: plural snake_case (`repair_jobs`, `line_items`, `approval_events`)
- FK columns: `{singular_table}_id` (`garage_id`, `job_id`, `estimate_id`)
- Boolean columns: `is_` prefix (`is_active`, `is_locked`)
- Nullable datetime: `sent_at`, `locked_at`, `confirmed_at`
- Enum-style strings: `status`, `state`, `channel`, `role`

## Append-Only Tables

`approval_events` and `job_state_transitions` are append-only. No `updated_at`, no soft deletes.
Use `$table->timestamp('occurred_at')` instead of `$table->timestamps()`.
