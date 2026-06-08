# 023 — users.crm_user_id

**Migration file:** `database/migrations/20260608000008_add_crm_user_id_to_users_table.php`
**Added:** 2026-06-08
**Phase:** 5 — Notifications & Preferences (staff side)

## Purpose

Maps a garage `User` (SSO-authenticated) to their corresponding CRM record. Required by `CrmStaffNotificationService` to route staff alerts (handover-flagged, payment-confirmed, timeout-reminder) through the upcoming polymorphic CRM endpoint (`recipient_type=staff`).

Closes the audit blocker raised 2026-06-08: prior implementation referenced `$user->crm_user_id` but the column did not exist, so the staff CRM call was always skipped while the audit row still claimed "dispatched". This migration provides the real backing column; `CrmStaffNotificationService::dispatch()` now early-returns + logs a warning when the column is null, instead of writing a misleading audit row.

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `crm_user_id` | `string` | yes | `null` | Foreign reference to the CRM `users.id` (ULID in CRM). Nullable so existing local users (test fixtures, dev-seeded accounts) keep working; SSO callback (`Auth\SsoLoginController@callback`) is the future writer for production. |

Plus a single-column index for `WHERE crm_user_id = ?` reverse lookups (CRM webhooks resolving inbound staff IDs back to a garage user).

## Dispatch semantics

- `crm_user_id IS NULL` → `CrmStaffNotificationService` logs a warning and does **not** record an audit row. The system is honest that no dispatch attempt happened.
- `crm_user_id IS NOT NULL` → `CrmStaffNotificationService` calls `CrmApiService::sendStaffNotification()` and records `EVENT_STAFF_NOTIFICATION_DISPATCHED` per channel. The `crm_dispatched` payload field reflects whether the feature flag actually let the HTTP call go out.

## Production wiring (deferred)

`Auth\SsoLoginController@callback` will populate `crm_user_id` from an SSO userinfo claim once the CRM/SSO contract is finalised. Until then, the column is null for SSO-provisioned users and the staff dispatch chain is a no-op (logged) — which is the intended behaviour while the CRM staff-recipient endpoint is still pending.

## Related decisions

- **Phase 5 — RESOLVED 2026-06-08.** See memory `garage-phase5-deferred.md`.
- **Audit 2026-06-08 blocker (Cat 4) — closed.** Honest audit log, no false dispatch claims.

## Related migrations

- `0001_01_01_000000_create_users_table` (framework default) — parent table
