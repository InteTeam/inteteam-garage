# Migration 029 — Add `remember_token` to `customers`

**File:** `database/migrations/20260624000001_add_remember_token_to_customers_table.php`
**Date:** 2026-06-24
**Type:** Patch / follow-up to migration 028

## Why this exists

Migration 028 (`create_customers_table`) was committed with `$table->rememberToken()` in the source, but on at least one dev environment the table was created from an earlier revision of the file that did not include the column. Because the migration row was already present in `migrations`, `php artisan migrate` did not re-apply 028 and the missing column silently propagated.

The omission was discovered when `Auth::guard('customer')->login($customer, remember: true)` issued an `UPDATE customers SET remember_token = ...` and crashed with `Unknown column 'remember_token'`.

## What it does

Idempotent — only adds the column if it doesn't already exist:

```php
if (! Schema::hasColumn('customers', 'remember_token')) {
    Schema::table('customers', fn (Blueprint $t) => $t->rememberToken());
}
```

On a fresh `migrate:fresh`, 028 creates the column, 029 is a no-op. On an already-migrated dev DB, 029 patches the hole.

## Rollback

Drops the column. Existing recaller cookies become unrecoverable (no harm — login flow falls through to SSO re-auth).
