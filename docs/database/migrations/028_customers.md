# Migration 028: `customers` table

Backs the new SSO-authenticated customer portal (`/account/*`). Distinct from mechanic-side `users` because:
- Customers have no `garage_id` — they're cross-tenant entities tied to CRM-owned data.
- A single email → single customer row; SSO identity is the join key.

## Schema

| Column | Type | Notes |
|---|---|---|
| `id` | ULID (PK) | `HasUlids` on the model. |
| `crm_customer_id` | string, nullable, indexed | Bridge to CRM-owned vehicles/jobs/payments. Populated on first SSO login when `CrmApiService::findCustomerByEmail()` returns a match; remains null when CRM doesn't know the email yet (handled with a dashboard banner). |
| `email` | string, unique | Always stored lowercase (callback folds it). Unique because SSO identity is the join key. |
| `name` | string | Display name. Prefers CRM-provided name once `crm_customer_id` is set; falls back to SSO `userinfo.name` then the email. |
| `last_login_at` | timestamp, nullable | Stamped on every successful `customer.callback`. |
| `created_at`, `updated_at` | timestamps | Standard Eloquent timestamps. |

## Indexes

- PK on `id`.
- UNIQUE on `email`.
- INDEX on `crm_customer_id` — every dashboard / vehicle / job query joins via this column.

## Multi-tenancy

**No `garage_id`** by design. A customer record represents the same person regardless of how many garages they've used. Queries that pull this customer's data (`vehicles`, `repair_jobs`, etc.) do so with `withoutGlobalScopes()` to bypass `HasGarageScope`.

## Future migrations

- If CRM ever supports multiple customer IDs per email (e.g. household vs. business), this table grows a pivot. Until then, the 1:1 `crm_customer_id` is correct.
- Email change handling is deliberately out of scope for migration 028 — when a customer's SSO email changes, the simplest path is a fresh row + manual merge by CRM. We'll know we need automation when the operator complains.
