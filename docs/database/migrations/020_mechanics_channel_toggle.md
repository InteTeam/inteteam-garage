# 020 — mechanics.channel_toggle_allowed

**Migration file:** `database/migrations/20260608000005_add_channel_toggle_allowed_to_mechanics_table.php`
**Added:** 2026-06-08
**Phase:** 5 — Notifications & Preferences (staff side)

## Purpose

Meta-permission per mechanic: can this mechanic toggle their own staff notification channels (email/SMS), or are they locked to all channels?

Resolves the Phase 5 Poka-Yoke ↔ Future-Proof tension: garage admin can lock critical roles to mandatory all-channels for handover/payment alerts, while other mechanics opt into the toggle UI.

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `channel_toggle_allowed` | `boolean` | yes | `null` | `null` = inherit from `Garage.staff_channel_toggle_default`. Explicit override per mechanic when admin sets it. |

## Resolution logic

```
mechanic.channel_toggle_allowed  ??  garage.staff_channel_toggle_default  ??  true
```

Helper: `Mechanic::canToggleChannels(): bool`.

Same C-hybrid pattern as `Mechanic.locale` (migration 016) — nullable column + runtime fallback, ready for future `Company.staff_channel_toggle_default` parent layer.

## Audit

Change to this column appends `ApprovalEvent::EVENT_STAFF_TOGGLE_LOCK_CHANGED` per non-collected job in the garage. Pattern mirrors the existing `online_payment_enabled` audit in `GarageSettingsService`.

## Related decisions

- **Phase 5 — RESOLVED 2026-06-08.** See memory `garage-phase5-deferred.md` §"Channel preferences for staff".

## Related migrations

- `002_mechanics` — parent table
- `021_garages_staff_settings` — provides the fallback `Garage.staff_channel_toggle_default` + companion staff settings
