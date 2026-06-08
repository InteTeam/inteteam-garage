# 021 — garages staff settings

**Migration file:** `database/migrations/20260608000006_add_staff_settings_to_garages_table.php`
**Added:** 2026-06-08
**Phase:** 5 — Notifications & Preferences (staff side)

## Purpose

Garage-level configuration for the staff notification model:
1. Default for the per-mechanic channel-toggle meta-permission.
2. How aggressively to send timeout reminders to staff (24/7 vs working hours vs on-call rotation).
3. Working hours schedule when policy = `working_hours`.

These settings live on `Garage` for now; the C-hybrid plan adds a future `Company.*` parent layer if multi-garage chains become a first-class customer segment.

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `staff_channel_toggle_default` | `boolean` | no | `true` | Default for `Mechanic.channel_toggle_allowed`. Garage admin can flip to `false` to lock all mechanics to mandatory all-channels. |
| `timeout_reminder_policy` | `string(32)` | no | `'24_7'` | One of `Garage::TIMEOUT_POLICIES`: `24_7`, `working_hours`, `on_call`. |
| `working_hours` | `json` | yes | `null` | Per-weekday schedule. Required iff `timeout_reminder_policy = 'working_hours'`. Format: `{"mon": {"open": "08:00", "close": "17:00"}, ...}`. Missing day = closed. |

## Timeout policy semantics

- **`24_7`** — Always dispatch staff timeout alerts immediately. Default.
- **`working_hours`** — Dispatch only if `Garage::isWithinWorkingHoursNow()`. Outside the window the alert is **queued** (the next hourly run picks it up once back in hours), not dropped. Poka-Yoke contract: alerts are never lost, just deferred.
- **`on_call`** — Route to the mechanic returned by `MechanicOnCall` (migration 022) covering the current timestamp. If nobody is on call, fall back to broadcasting to all assigned mechanics (Poka-Yoke: never drop alerts when on-call rotation has a gap).

## Audit

Changes to any of these settings append events to **every non-collected job** in the garage:

- `staff_channel_toggle_default` → `EVENT_STAFF_TOGGLE_LOCK_CHANGED`
- `timeout_reminder_policy` → `EVENT_TIMEOUT_POLICY_CHANGED`

Payload: `{setting, from, to}`. Implemented in refactored `GarageSettingsService::update()` (single diff loop, generic across all audited settings).

## Related decisions

- **Phase 5 — RESOLVED 2026-06-08.** See memory `garage-phase5-deferred.md` §"Timeout reminder policy" + §"Channel preferences for staff".

## Related migrations

- `001_garages` — parent table
- `020_mechanics_channel_toggle` — companion meta-permission column
- `022_mechanic_on_calls` — provides on-call rotation data
