# Feature: Vehicle Compliance (MOT / Road Tax / Insurance)

## Overview

Per-vehicle compliance lifecycle: track MOT, Road Tax, and Insurance expiry dates; surface them on the vehicle detail page; auto-fill MOT + Tax from the DVLA Vehicle Enquiry Service; and send daily reminders before expiry to customers and/or garage admins via the existing CRM notification pipeline.

## User Stories

**Garage admin:**
- As an admin I can see the current MOT, Road Tax, and Insurance expiry dates for every vehicle on file, with traffic-light colouring (red = expired, amber = ≤30 days, green = healthy).
- As an admin I can manually record any of the three dates with a free-form note.
- As an admin I can press one button on the vehicle page to refresh MOT + Tax from DVLA, so I don't have to hunt for the dates myself.
- As an admin I can opt my garage in to automated expiry reminders, choose the channel (email / SMS / in-app), pick which days-before-expiry to fire at, and decide whether the customer, my team, or both get notified.
- As an admin I can see the full history of any vehicle's compliance entries (who recorded what, when, from what source).

**Customer (via configured reminders, no login):**
- As a customer I receive a notification through whatever channel my garage uses, the configured number of days before my MOT / Tax / Insurance expires, so I can book renewal in time.

## External API

DVLA Vehicle Enquiry Service — `https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles`. Free tier, register at `https://register-for-the-vehicle-enquiry-service-vehicle-checker-api.service.gov.uk/`. Returns MOT expiry + status, Tax expiry + status, plus enrichment (make, colour, year of manufacture, fuel type). Insurance is **not** exposed by DVLA — that lives in MIB / askMID, a paid contract. Insurance remains manual-only in this feature.

## Backend surface

| Layer | File | Notes |
|---|---|---|
| Routes | `routes/web.php` | `POST /vehicles/{vehicle}/compliance` (manual) + `POST /vehicles/{vehicle}/compliance/refresh` (DVLA). |
| Controller | `app/Http/Controllers/ComplianceRecordController.php` | Thin — authorize, delegate, flash. |
| Policy | `app/Policies/ComplianceRecordPolicy.php` | `createForVehicle` requires garage admin and same garage. |
| Form Request | `app/Http/Requests/Vehicle/StoreComplianceRecordRequest.php` | `type` (enum), `expires_on` (date), `note` (nullable string). |
| Service (records) | `app/Services/VehicleComplianceService.php` | `currentForVehicle`, `historyForVehicle`, `record` (manual), `applyDvlaResult` (DVLA + dedup). |
| Service (DVLA) | `app/Services/Dvla/{VehicleEnquiryService,VehicleEnquiryResult,DvlaException}.php` | HTTP client + readonly DTO + named exceptions. |
| Service (reminders) | `app/Services/ComplianceReminderService.php` | Daily dispatcher; insert-first dedup; CRM-backed delivery. Capped at 250 lines per playbook. |
| Helper (copy) | `app/Services/ComplianceReminderCopy.php` | Subject/body strings keyed by locale (en/pl). Extracted from the dispatcher so future copy edits don't touch dispatch logic. |
| Console | `app/Console/Commands/DispatchComplianceReminders.php` | Scheduled `dailyAt('09:00')`. |
| Model | `app/Models/ComplianceRecord.php` | `HasGarageScope`, `HasUlids`, `ComplianceType` + `ComplianceSource` casts, `belongsTo` Vehicle + User. |
| Model | `app/Models/ComplianceReminderSent.php` | Dispatch audit + dedup row. |
| Enums | `app/Enums/{ComplianceType,ComplianceSource,ComplianceRecipient}.php` | Domain enums. |
| Config | `config/services.php` `dvla` section | `ves_url`, `ves_api_key`, `ves_timeout`. |

## Frontend surface

| Page | File | Notes |
|---|---|---|
| Vehicle detail | `resources/js/Pages/Vehicles/Show.tsx` | Two-tab layout — Details / Compliance. Compliance tab shows 3 cards (MOT / Tax / Insurance) + DVLA Refresh banner (only when `dvlaEnabled` prop is true) + collapsible history. |
| Vehicle form | `resources/js/Pages/Vehicles/Form.tsx` | New optional VIN field. |
| Garage settings | `resources/js/Pages/Settings/Index.tsx` | New "Compliance Reminders" section: enable toggle → channel + windows (30/14/7/1) + types (MOT/Tax/Insurance) + recipient (customer / both / mechanic). Save is disabled when the toggle is on and either array is empty (mirrors server-side Poka-Yoke). |

## Database

| Migration | Adds |
|---|---|
| `024_vehicles_vin.md` | `vehicles.vin` (nullable, 17 chars). |
| `025_compliance_records.md` | `compliance_records` table — append-only history. |
| `026_garages_compliance_reminder_settings.md` | 5 reminder columns on `garages`. |
| `027_compliance_reminders_sent.md` | Dispatch audit + dedup unique index. |

## Acceptance criteria

- [x] Admin can manually record MOT / Tax / Insurance with a note.
- [x] Non-admin mechanic cannot record compliance (403).
- [x] Cross-garage attempt to record on another garage's vehicle returns 404 (tenant scope).
- [x] Form Request rejects invalid `type` and missing `expires_on`.
- [x] Vehicle detail page returns latest record per type, plus full history.
- [x] DVLA refresh: friendly error when API key is not configured.
- [x] DVLA refresh: pulls MOT + Tax dates, sets `source='dvla'`, ignores Insurance.
- [x] DVLA refresh: skips insert when the date matches the latest stored value (dedup).
- [x] DVLA refresh: handles 404 (registration not in DVLA) gracefully — error flash, no record written.
- [x] Reminder dispatcher: skips garages with reminders disabled.
- [x] Reminder dispatcher: only fires on records whose `expires_on` is exactly N days away where N is one of the configured windows.
- [x] Reminder dispatcher: dedupes across repeat runs (unique constraint).
- [x] Reminder dispatcher: only considers the latest record per (vehicle, type) — older rows that happen to fall in a window are ignored.
- [x] Reminder dispatcher: respects the configured `compliance_reminders_types` filter.
- [x] Reminder dispatcher: routes to customer / mechanic admin / both per `compliance_reminders_recipient`.
- [x] Settings: enabling reminders without picking ≥1 window OR ≥1 type returns session errors (Poka-Yoke).
- [x] Settings: window values outside `1..90` are rejected per element.
- [x] Settings: changing reminder toggle / channel / recipient appends `EVENT_PREFERENCE_CHANGED` per non-collected job (audit).

## Design decisions worth remembering

- **Insurance is manual-only.** Documented in code (`applyDvlaResult` iterates only MOT + TAX) and in the UI (Insurance card has no Refresh CTA). Bolting MIB on later is additive — no table changes.
- **Per-garage Poka-Yoke beats hardcoded defaults.** We don't know the end mechanic's preference for channel / windows / recipient, so every dimension is configurable. Defaults (`[30, 7]` windows, all three types, `customer` recipient) only kick in when the column is null.
- **Dedup is a DB unique index, not application logic.** `compliance_reminders_sent` carries `unique(compliance_record_id, window_days, recipient_type, recipient_id)`. The service writes the row FIRST, then calls CRM. This means a hammered scheduler / job retry cannot double-notify; it also means a freshly-recorded MOT (new `compliance_record_id`) earns its own reminder cycle.
- **History is append-only.** Updates are inserts. `currentForVehicle` returns the latest per type. DVLA refresh dedupes by comparing the incoming date to the latest stored date so daily clicks don't bloat history with no-ops.
- **DVLA is feature-gated by API key presence.** `Dvla\VehicleEnquiryService::isConfigured()` short-circuits when `DVLA_VES_API_KEY` is empty, and the Refresh banner hides on the frontend (`dvlaEnabled` prop). No noisy errors in dev where the key isn't set.
- **`whereDate` over `whereBetween` for date columns.** SQLite (test DB) stores Laravel `'date'` cast values as TEXT with the time suffix; `whereBetween` then drops boundary rows lexicographically. `whereDate` casts column to date in SQL so the range comparison is correct cross-engine. See gotcha in `docs/app-map.md`.

## Operator setup (production)

1. Register at `https://register-for-the-vehicle-enquiry-service-vehicle-checker-api.service.gov.uk/` and obtain an API key.
2. Add to the server `.env`:
   ```
   DVLA_VES_API_KEY=...
   ```
3. Restart php-fpm so opcache + config cache pick it up: `docker compose restart php-fpm`.
4. The Refresh banner now appears on every vehicle's Compliance tab.
5. In `/settings`, the garage admin opts in to reminders and picks channel + windows + types + recipient. The scheduled job (`compliance:dispatch-reminders`, daily 09:00) will pick them up from the next run.

## Related docs

- `docs/database/migrations/024..027` — schema.
- `docs/app-map.md` — routes, services, gotchas.
- `inte-playbook/laravel/README.md` — Step 0 conventions this feature follows.
