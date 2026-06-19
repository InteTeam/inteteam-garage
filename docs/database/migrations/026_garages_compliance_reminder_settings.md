# 026 — garages.compliance_reminder_settings

**Migration file:** `database/migrations/20260619000003_add_compliance_reminder_settings_to_garages_table.php`
**Added:** 2026-06-19
**Phase:** Compliance — MOT / Road Tax / Insurance lifecycle

## Purpose

Per-garage opt-in + tuning for the daily compliance reminder dispatcher. Each garage admin decides whether to send reminders at all, which channel to use, how many days before expiry to fire each notice, what compliance types to include, and who to notify.

Poka-Yoke design — we don't know the end user's preference, so every dimension is configurable in `Settings/Index.tsx` and validated server-side in `UpdateGarageSettingsRequest`.

## Schema changes (add to `garages`)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `compliance_reminders_enabled` | `boolean` | no | `false` | Master switch. While off, `ComplianceReminderService::dispatchDue()` skips the garage entirely. |
| `compliance_reminders_channel` | `string(16)` | yes | `null` | Override of `default_notification_channel`. Null = inherit. Validated against `Garage::CHANNELS`. |
| `compliance_reminders_windows` | `json` | yes | `null` | Array of int days-before-expiry to fire at (e.g. `[30, 7]`). Null falls back to `Garage::DEFAULT_REMINDER_WINDOWS = [30, 7]`. Each entry validated `between:1,90`. |
| `compliance_reminders_recipient` | `string(32)` | no | `'customer'` | `ComplianceRecipient` enum (`customer` / `customer_and_mechanic` / `mechanic`). |
| `compliance_reminders_types` | `json` | yes | `null` | Array of `ComplianceType` enum values to remind about. Null = all three (`['mot', 'tax', 'insurance']`). |

All columns ordered `after('timeout_reminder_policy')` to stay grouped with sibling reminder/notification settings.

## Validation Poka-Yoke

`UpdateGarageSettingsRequest` enforces that **enabling** reminders requires both `compliance_reminders_windows` and `compliance_reminders_types` to be present and non-empty:

```php
'compliance_reminders_windows' => [
    Rule::requiredIf(fn () => (bool) $this->boolean('compliance_reminders_enabled')),
    'array',
    'min:1',
],
```

`sometimes` is deliberately omitted — it short-circuits the entire rule chain when the field is absent, which would silently skip the `requiredIf` callable. Same shape applies to `compliance_reminders_types`.

The UI mirrors the rule: the Save button on `Settings/Index.tsx` is disabled while reminders are enabled but either array is empty.

## Audit hook

`GarageSettingsService::AUDITED_SETTINGS` now also tracks:

- `compliance_reminders_enabled` → `EVENT_PREFERENCE_CHANGED`
- `compliance_reminders_channel` → `EVENT_PREFERENCE_CHANGED`
- `compliance_reminders_recipient` → `EVENT_PREFERENCE_CHANGED`

Per non-collected job, the diff loop appends an audit row when any of these change. `compliance_reminders_windows` and `_types` are deliberately **not** audited — they're tuning knobs, not policy switches.

`GarageSettingsService::update()` was changed to `refresh()` the Garage from DB before snapshotting so that DB defaults (e.g. `compliance_reminders_recipient='customer'`) don't read as `null` in memory and produce a spurious `null → 'customer'` diff on first save.

## Related migrations

- `021_garages_staff_settings.md` — earlier per-garage reminder/notification settings.
- `025_compliance_records.md` — the table the reminders point at.
- `027_compliance_reminders_sent.md` — dispatch audit + dedup.
