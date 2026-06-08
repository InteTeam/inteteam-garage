# App Map — inteteam-garage

The single reference for "what lives where" in this repo. Read this **before** modifying a feature so you don't re-explore the codebase. Per `inte-playbook/workflow/README.md` Step 0 + Step 11.

**Stack:** Laravel 13 / PHP 8.3 · React 19 + Inertia.js + TypeScript · MariaDB 11.8 · Redis 7 · GCS · MariaDB sessions

---

## Roles

| Role | Auth source | Model | Where used |
|---|---|---|---|
| `garage_admin` | Inte.Team SSO → User → Mechanic | `Mechanic::ROLE_GARAGE_ADMIN` | Mechanic dashboard, settings, full CRUD |
| `mechanic` | Inte.Team SSO → User → Mechanic | `Mechanic::ROLE_MECHANIC` | Mechanic dashboard, no settings |
| Customer (portal) | Signed `SignedPortalToken` URL — no SSO | none | Portal routes (`routes/portal.php`), token-scoped to one job |
| System | n/a | `ApprovalEvent::ACTOR_SYSTEM` | Scheduled commands, webhooks |
| Anonymous | n/a | n/a | `GET /login` (SSO redirect), `POST /webhooks/payment-confirmed` |

Multi-tenancy is per-`Garage`. Every tenant model uses `HasGarageScope` (resolves from `session('current_garage_id')` via `EnsureGarageContext` middleware).

---

## Routes

### `routes/web.php` — mechanic dashboard (`auth` + `garage` middleware)

| Method | URI | Controller@method | Notes |
|---|---|---|---|
| GET | `/login` | `Auth\SsoLoginController@redirect` | Redirects to SSO `/oauth/authorize` |
| GET | `/auth/callback` | `Auth\SsoLoginController@callback` | Exchanges code → token, resolves Mechanic |
| POST | `/logout` | `Auth\SsoLoginController@logout` | |
| GET | `/` | `DashboardController@index` | Garage overview |
| resource | `/vehicles` | `VehicleController` | CRUD |
| resource | `/mechanics` | `MechanicController` | CRUD (admin only) |
| resource | `/jobs` | `JobController` | CRUD + show |
| POST | `/jobs/{job}/transition` | `JobController@transition` | Triggers `JobStateMachine` |
| resource | `/jobs/{job}/stages` | `JobStageController` | CRUD |
| POST | `/jobs/{job}/stages/{stage}/media` | `MediaController@store` | Upload to GCS, locked stages reject |
| resource | `/jobs/{job}/estimates` | `EstimateController` | CRUD |
| POST | `/jobs/{job}/estimates/{estimate}/send` | `EstimateLifecycleController@send` | Triggers `awaiting_approval`. Cross-locale estimates blocked by `EstimateService::guardSendable` unless `preview_confirmed_at` is set (returns to form with `assertSessionHasErrors('estimate')`). |
| POST | `/jobs/{job}/estimates/{estimate}/preview-translation` | `EstimateLifecycleController@previewTranslation` | LLM preview before send. Resolves `fromLocale` via `Mechanic::resolvedLocale()` + auto-detect override; `toLocale` via `CrmApiService::getCustomerLocale()` with garage fallback. Returns JSON: `{translations, from_locale, to_locale, configured_from_locale, auto_detected_override}`. |
| POST | `/jobs/{job}/estimates/{estimate}/confirm-translation` | `EstimateLifecycleController@confirmTranslation` | Atomic confirm step. Per-line-item payload `{id, translated_text, llm_raw_text}` → persists `translation_confirmed_text` + `translation_llm_raw` + sets `translation_edited_by_mechanic_id` when the mechanic edited the LLM output; flips `Estimate.preview_confirmed_at` to `now()` only if all rows persist (single `DB::transaction`). Required before `send` for cross-locale estimates. |
| POST | `/jobs/{job}/line-items/{lineItem}/preview-response` | `LineItemResponseController@preview` | LLM preview for cross-locale mechanic responses. Same locale resolution as estimate preview; returns `{original, translated, from_locale, to_locale, translated_by_ai}`. |
| POST | `/jobs/{job}/mechanics/assign` | `JobMechanicController@sync` | Pivot sync |
| PUT | `/jobs/{job}/notification-preference` | `JobNotificationPreferenceController@update` | Admin override — appends `EVENT_PREFERENCE_CHANGED` w/ actor=mechanic; no-op when channel unchanged |
| POST | `/jobs/{job}/scope-change` | `ScopeChangeController@store` | Mechanic raises scope change. `ScopeChangeService` creates new `Estimate` revision + LineItems, transitions `approved → scope_change`, logs `EVENT_SCOPE_CHANGE`, fires `notifyScopeChange`. Atomic. |
| POST | `/jobs/{job}/line-items/{lineItem}/respond` | `LineItemResponseController@store` | Mechanic responds to customer question. **Cross-locale requires `translated_message` field** (422 otherwise — Q3 Poka-Yoke). Logs `EVENT_MECHANIC_RESPONSE` with `{message, translated_message?, from_locale, to_locale}` payload; auto-transitions `customer_query → awaiting_approval` if applicable; fires `notifyMechanicResponse` with the translated copy when present. |
| GET | `/jobs/{job}/portal-link` | `PortalLinkController@show` | View signed URL for customer |
| POST | `/jobs/{job}/portal-link/regenerate` | `PortalLinkController@regenerate` | Revokes old token, mints new |
| GET | `/settings` | `GarageSettingsController@index` | Admin only |
| PUT | `/settings` | `GarageSettingsController@update` | Admin only |

### `routes/portal.php` — customer-facing (`portal.token` middleware, no SSO)

All routes are prefixed with `portal/{token}` and resolve `RepairJob` from the token. The middleware (`ValidatePortalToken`) verifies expiry + revocation and binds `$job` to the route.

| Method | URI | Controller@method |
|---|---|---|
| GET | `portal/{token}` | `Portal\PortalJobController@show` |
| GET | `portal/{token}/timeline` | `Portal\PortalJobController@timeline` |
| GET | `portal/{token}/handover` | `Portal\PortalHandoverController@show` |
| POST | `portal/{token}/handover` | `Portal\PortalHandoverController@submit` |
| POST | `portal/{token}/line-items/{lineItem}/approve` | `Portal\PortalLineItemController@approve` |
| POST | `portal/{token}/line-items/{lineItem}/decline` | `Portal\PortalLineItemController@decline` |
| POST | `portal/{token}/line-items/{lineItem}/question` | `Portal\PortalLineItemController@question` |
| POST | `portal/{token}/notification-preference` | `Portal\PortalPreferenceController@update` |
| GET | `portal/{token}/payment` | `Portal\PortalPaymentController@show` |
| POST | `portal/{token}/payment/request` | `Portal\PortalPaymentController@request` |

### `routes/api.php` — internal API (`api.garage` middleware = `AuthenticateGarageApiKey`)

| Method | URI | Controller@method |
|---|---|---|
| GET | `api/v1/jobs/{job}` | `Api\JobApiController@show` |
| GET | `api/v1/jobs/{job}/media` | `Api\JobApiController@media` |

### Webhooks (no auth — verified by signature)

| Method | URI | Controller@method |
|---|---|---|
| POST | `/webhooks/payment-confirmed` | `Webhooks\PaymentWebhookController@handle` |

### `routes/console.php` — scheduled

| Command | Schedule | Class |
|---|---|---|
| `garage:check-timeouts` | hourly | `App\Console\Commands\CheckJobTimeouts` |

---

## State Machine

`App\Services\JobStateMachine::transition($job, $toState)` — single entry point. `RepairJob::state` is **not** in `$fillable`; can only change via this service. Every transition appends to `JobStateTransition` (append-only, server timestamp).

### Allowed transitions

```
created      → booked
booked       → in_progress
in_progress  → awaiting_approval
awaiting_approval → customer_query | approved
customer_query    → awaiting_approval
scope_change      → awaiting_approval | in_progress
approved     → completed | scope_change
completed    → awaiting_collection
awaiting_collection → collected
```

### Transition guards

| To state | Guard | Throws if… |
|---|---|---|
| `awaiting_approval` | `guardAwaitingApproval` | current estimate has 0 line items |
| `completed` | `guardCompleted` | any line item still `pending` |
| `collected` | `guardCollected` | no `HandoverInspection` OR `online_payment_enabled` and no `payment_confirmed_at` |

### JobStage auto-lock policy

After every successful transition, `lockStagesPastActivity()` locks stages whose `STATE_ORDER` for their final-active-state is **less than** the new state's order.

| Stage | Active during | Locks at (or after) |
|---|---|---|
| `pre-inspection` | created · booked · in_progress | `awaiting_approval` |
| `disassembly` | in_progress | `awaiting_approval` |
| `fault-found` | in_progress | `awaiting_approval` |
| `repair` | approved | `completed` |
| `complete` | completed | `awaiting_collection` |

Lock is idempotent (`forceFill(['locked_at' => now()])` only when `locked_at IS NULL`).

---

## Approval Events (audit log)

`ApprovalEvent` is append-only (`$timestamps = false`, no `updated_at`, no soft delete). All writes go through `ApprovalEventService::record()` or `recordBySystem()`.

| Constant | When |
|---|---|
| `EVENT_LINE_ITEM_APPROVED` / `_DECLINED` | Customer portal `PortalLineItemController` |
| `EVENT_CUSTOMER_QUESTION` | Customer portal — adds `notes` payload |
| `EVENT_MECHANIC_RESPONSE` | `LineItemResponseController@store` — payload `{line_item_id, message}` |
| `EVENT_ESTIMATE_SENT` | `EstimateController@send` |
| `EVENT_SCOPE_CHANGE` | `ScopeChangeController@store` (via `ScopeChangeService`) — payload `{estimate_id, revision_number, line_item_count}` |
| `EVENT_PREFERENCE_CHANGED` | Customer/admin changes notification channel · also fires per non-collected job when admin toggles `Garage.online_payment_enabled` (via `GarageSettingsService`) |
| `EVENT_HANDOVER_SUBMITTED` | Customer submits handover inspection |
| `EVENT_PAYMENT_REQUESTED` / `_CONFIRMED` | `CrmPaymentService` / `PaymentWebhookController` |
| `EVENT_TIMEOUT_ALERT` | `CheckJobTimeouts` finds blocked job >24h. Now annotates `staff_recipients` count alongside state + timeout_hours. |
| `EVENT_STAFF_NOTIFICATION_DISPATCHED` | `CrmStaffNotificationService` dispatches a staff-side alert (handover-flagged, payment-confirmed, timeout-reminder). Payload: `{mechanic_id, channel, trigger, crm_dispatched}`. **Only written when the mechanic's `User.crm_user_id` is set** — otherwise dispatch is logged as a warning and no audit row appears (honest log). `crm_dispatched` reflects whether the CRM HTTP call actually went out (feature flag `services.garage.staff_notifications_via_crm_enabled`). |
| `EVENT_STAFF_TOGGLE_LOCK_CHANGED` | `GarageSettingsService` detects a change to `Garage.staff_channel_toggle_default`. Written per non-collected job in the garage. Payload: `{setting, from, to}`. |
| `EVENT_TIMEOUT_POLICY_CHANGED` | `GarageSettingsService` detects a change to `Garage.timeout_reminder_policy`. Written per non-collected job in the garage. Payload: `{setting, from, to}`. |

---

## Models (key relationships)

```
Garage
 ├─hasMany─► Mechanic ──belongsTo──► User  (User.crm_user_id → CRM staff record, nullable)
 ├─hasMany─► Vehicle ──belongsTo──► (CRM customer via crm_customer_id)
 ├─hasMany─► MechanicOnCall ──belongsTo──► Mechanic  (on-call rotation; covers a time window)
 └─hasMany─► RepairJob
              ├─belongsTo──► Vehicle
              ├─belongsToMany──► Mechanic  (pivot: repair_job_mechanic)
              ├─hasMany──► JobStage ──hasMany──► Media (GCS)
              ├─hasMany──► Estimate ──hasMany──► LineItem
              ├─hasMany──► ApprovalEvent          (append-only audit)
              ├─hasMany──► JobStateTransition     (append-only audit)
              ├─hasOne ──► SignedPortalToken      (customer-facing URL)
              ├─hasOne ──► NotificationPreference (channel: email/sms/in_app)
              └─hasOne ──► HandoverInspection ──hasMany──► HandoverItem
```

`Garage` itself does **not** use `HasGarageScope` (it is the tenant root). Every other model in `app/Models/` uses it. Tinker queries that need cross-garage data must call `Model::withoutGlobalScopes()`.

---

## Services

All business logic lives in `app/Services/`. Controllers delegate to services; services own DB writes, relationship loading, and external API calls. Each service is `final` and under the 250-line cap.

| Service | Owns |
|---|---|
| `JobStateMachine` | State transitions + guards (`guardAwaitingApproval`, `guardCompleted`, `guardCollected`); writes `JobStateTransition`; auto-locks stages on transition |
| `ApprovalEventService` | Only writer of `ApprovalEvent`. `record()` + `recordBySystem()` only |
| `EstimateService` | CRUD on `Estimate`. `update()` **throws** when `Estimate::hasCustomerResponse()` is true — must create a new revision instead. `markSent()` wraps the state transition + sent timestamp in a transaction. `guardSendable()` throws on cross-locale send without confirmation. `confirmTranslation()` atomically persists per-line-item LLM-raw + edited text + editor mechanic FK and flips `Estimate.preview_confirmed_at`. |
| `SignedPortalTokenService` | Generate / regenerate / revoke `SignedPortalToken`; builds `portal.show` URL |
| `GarageSettingsService` | Persists garage settings. Generic diff/audit loop fires `EVENT_PREFERENCE_CHANGED` (toggle online payment), `EVENT_STAFF_TOGGLE_LOCK_CHANGED`, or `EVENT_TIMEOUT_POLICY_CHANGED` per non-collected job for the matching changed setting. |
| `ScopeChangeService` | Atomically: new `Estimate` revision (`max(revision_number)+1`, `sent_at = now()`), new `LineItem` rows (status pending), state transition `approved → scope_change`, `EVENT_SCOPE_CHANGE` audit row. Wrapped in `DB::transaction` — partial-write impossible. |
| `MechanicService`, `VehicleService` | Thin CRUD wrappers over their models |
| `JobStageService` | CRUD wrapper + `updateNotes(JobStage, string, Mechanic)` does eager translation at write time (mechanic locale → customer locale via CRM), persists `notes_translated` + locale pair + timestamp; cached by (text hash, locale pair) for 24h. Constructor now requires `TranslationService` + `CrmApiService`. |
| `GcsService` | Only path to GCS — upload, signed URL generation, object naming |
| `CrmApiService` | Bottom-layer HTTP client to CRM (`X-Internal-Secret`); customers + notifications + payment requests. `getCustomerLocale($id)` caches per CRM customer for 1h and catches all errors → `null` (caller falls back to garage locale). `sendStaffNotification($crmUserId, $channel, …)` is the polymorphic-recipient endpoint feature-flagged via `services.garage.staff_notifications_via_crm_enabled` — when off, just logs and returns. |
| `CrmNotificationService` | Customer-side job-event wrappers over `CrmApiService::sendNotification()` (recipient_type=customer). |
| `CrmStaffNotificationService` | Staff-side wrappers (`notifyHandoverFlaggedToMechanic`, `notifyPaymentConfirmedToMechanic`, `notifyTimeoutReminderToMechanic`). Resolves channels via `Mechanic::canToggleChannels()`. **Skips dispatch + audit entirely (logs warning) when `User.crm_user_id` is null** — no false audit claims while SSO claim wiring is pending. |
| `CrmPaymentService` | `calculateAmount()` (approved line items only), `requestPayment()` (calls CRM + logs `EVENT_PAYMENT_REQUESTED`), `confirmPayment()` |
| `TranslationService` | OpenAI wrapper with embedded glossary + 24h cache per (text hash, locale pair). `detectLanguage($text)` calls OpenAI to identify ISO 639-1 from `SUPPORTED_LOCALES`. `verifySourceLocale($configured, $sample)` returns the detected locale (and logs a warning) when it disagrees with `$configured`. `previewEstimateTranslation()` includes a `translated_by_ai` flag per line item. |

## External integrations

| System | Service | Auth |
|---|---|---|
| Inte.Team SSO | `Auth\SsoLoginController` | OAuth2 authorization code, client_id + client_secret in `.env` |
| CRM (notifications, customers, payments) | `CrmApiService`, `CrmNotificationService`, `CrmPaymentService` | Shared secret in `CRM_API_SECRET` header |
| Google Cloud Storage | `GcsService` | Service account JSON at `GCS_KEY_FILE_PATH`, signed URLs via `temporaryUrl()` |
| OpenAI (translation) | `TranslationService` | `OPENAI_API_KEY` |

---

## Gotchas

- **`SSO_URL` vs `SSO_PUBLIC_URL`.** Browser redirect to `/oauth/authorize` uses `services.sso.public_url` (`http://localhost:8088` for dev). Server-side token/userinfo calls use `services.sso.url` (`http://host.docker.internal:8088`). Container-side `localhost` ≠ host's `localhost`.
- **GCS keys path.** Passport-style: `Passport::loadKeysFrom(base_path())` in `AppServiceProvider` (not storage_path).
- **`APP_URL` must include `:8085`.** `route('auth.callback')` builds the redirect URI from `APP_URL`; if port is missing the SSO client redirect_uri whitelist won't match.
- **OneDrive bind mount + opcache.** `opcache.validate_timestamps=0` is on (set in `docker/php/php.ini`); after any PHP edit run `docker compose exec php-fpm php artisan optimize:clear` or restart `php-fpm` + `nginx` so the new code is loaded.
- **nginx caches php-fpm IP.** After `docker compose restart php-fpm`, also restart `nginx` or you'll get 502 (upstream IP stale).
- **`current_garage_id` session key.** Set in `Auth\SsoLoginController@callback`. Tests acting as a user must `withSession(['current_garage_id' => $garage->id])` before HTTP calls.
- **State change is service-only.** Never `$job->update(['state' => ...])`. Always `JobStateMachine::transition($job, $toState)`. State is excluded from `$fillable` and guards run on transition.
- **`JobStage` lock is one-way.** Auto-lock fires on transition; manual unlock not implemented. Adding a stage to a job past `awaiting_approval` will auto-lock it on the next transition.
- **Portal token scope.** `ValidatePortalToken` middleware binds the matching `RepairJob` to the route — portal endpoints only ever see the one job their token belongs to. Token A can never load job B even if both belong to the same garage.
- **Estimate immutability after customer response.** `EstimateService::update()` throws `RuntimeException` if `Estimate::hasCustomerResponse()` is true (any line item approved/declined). To change scope after the customer has responded, create a new `Estimate` row with `revision_number = previous + 1` — the controller's `store()` already does this.
- **`Mechanic`/`Vehicle` Form Requests do not accept `garage_id` from the client.** `garage_id` is set server-side via the `HasGarageScope::creating` hook from `session('current_garage_id')`. Posting `garage_id` in the payload has no effect — defence-in-depth against tenant spoofing.
- **`RepairJob::booted()` seeds `NotificationPreference` on create.** `firstOrCreate(['job_id' => …])` with the garage's `default_notification_channel` and `set_by = 'admin'`. Idempotent — does not fire on re-saves. Auto-seed is **not** audited (no `EVENT_PREFERENCE_CHANGED`); only mutations (admin endpoint, customer portal endpoint, garage settings toggle) log to the audit. If a test or seeder needs a different starting channel, write through `JobNotificationPreferenceController` / `PortalPreferenceController` (logs the change) or override via `NotificationPreference::withoutGlobalScopes()->updateOrCreate(...)` directly (no log).
- **`JobStageController` auths via `RepairJobPolicy`, not a `JobStagePolicy`.** No `JobStagePolicy` exists. Controller calls `$this->authorize('view'|'update', $job)` against the parent `RepairJob`. Cross-job tampering is blocked by `ensureStageBelongsToJob($stage, $job)` — checks `$stage->job_id === $job->id` and throws `RuntimeException` if not. Tests for the cross-job case live in `tests/Feature/Jobs/JobStageControllerTest::test_update_rejects_stage_from_different_job`.
- **Form Request `'datetime'` is not a real Laravel rule.** Laravel exposes `'date'` and `'date_format:...'` only — `'datetime'` throws `BadMethodCallException: Validator::validateDatetime does not exist` at runtime. Use `['nullable', 'date']` (or `['required', 'date']`) for ISO datetime strings. Audit 2026-06-07 cleaned three offenders: `StoreJobStageRequest.locked_at`, `UpdateJobStageRequest.locked_at`, `UpdateEstimateRequest.sent_at`.
- **Scope change creates a NEW Estimate revision; the old one is not mutated.** `currentEstimate` resolves to the highest `revision_number`, so the portal automatically shows the new items after `ScopeChangeService` runs. The OLD estimate's approved/declined line items remain frozen — `EstimateService::update()` would throw on them anyway (`hasCustomerResponse()`). `guardCompleted` checks `currentEstimate->allLineItemsResolved()` and so it only requires the new revision's items to be resolved before re-completing; previously approved work doesn't need re-approval.
- **Mechanic response auto-transitions `customer_query → awaiting_approval`, never any other pair.** `LineItemResponseController` guards the transition by checking the current state. From `awaiting_approval`, response logs the event but state stays put (mechanic was just adding context). Customer questions today do **not** auto-transition `awaiting_approval → customer_query` — mechanic must manually trigger that via the existing `JobController@transition` endpoint to "pause the clock"; otherwise the response endpoint's auto-resume has nothing to resume from.
- **Translation locale resolution: per-mechanic with auto-detect failsafe.** `fromLocale` resolves via `Mechanic::resolvedLocale()` (`Mechanic.locale ?? Garage.locale ?? 'en'`). `TranslationService::verifySourceLocale($configured, $sampleText)` runs an OpenAI detect call on the first sample; if the detector returns a different ISO 639-1 from `SUPPORTED_LOCALES = ['en','pl']`, the detected one wins **and** a `Log::warning` is emitted (mechanic locale misconfigured). `toLocale` comes from `CrmApiService::getCustomerLocale($crmCustomerId)` — cached 1h, returns `null` on any `Throwable` so the caller falls back to garage locale (Poka-Yoke: never lose a translation step over a transient CRM failure).
- **Cross-locale send gate is enforced, not advisory.** `EstimateService::guardSendable()` throws `RuntimeException` when `fromLocale !== toLocale` and `Estimate.preview_confirmed_at` is null. `EstimateLifecycleController::send` catches → session error `'estimate'`. Same-locale estimates skip the gate. Q5 audit: `line_items.translation_llm_raw` captures the AI baseline; `line_items.translation_confirmed_text` captures what shipped; `translation_edited_by_mechanic_id` is set only when the two differ.
- **Stage notes auto-translate at write time, not read time.** `JobStageService::updateNotes($stage, $text, $mechanic)` is the only writer. Eager strategy chosen so translation failures surface to the mechanic at save time (fixable), not to the customer at read time (broken English). Same 24h cache key as estimate translations. Portal disclaimer flips via `JobStage::notesWereTranslatedByAi()`. **Not yet wired through `JobStageController`** — controller form input + UI is a follow-up; service helper is callable today.
- **Mechanic query responses require explicit translation confirmation when cross-locale.** `LineItemResponseController::store` throws a `422` `translated_message` validation error if `fromLocale !== toLocale` and the payload omits the confirmed translation. Same-locale responses skip the gate. Both copies (`message`, `translated_message`) land in the `EVENT_MECHANIC_RESPONSE` audit payload. Companion `POST /jobs/{job}/line-items/{lineItem}/preview-response` returns the LLM translation for the side-by-side UI.
- **Staff notifications are stubbed end-to-end with an honest audit log.** `CrmStaffNotificationService` is wired into `PortalHandoverController` (flagged item), `PaymentWebhookController` (payment confirmed), and `CheckJobTimeouts` (policy-aware dispatch). The polymorphic CRM endpoint (`recipient_type=staff`) is feature-flagged via `GARAGE_STAFF_NOTIFICATIONS_VIA_CRM_ENABLED` (default off). When the flag is off the HTTP call short-circuits but the `EVENT_STAFF_NOTIFICATION_DISPATCHED` audit row still appears, with `crm_dispatched: false`. **When `User.crm_user_id` is null** (currently all SSO users until callback wiring lands), dispatch is skipped entirely and **no audit row is written** — only a `Log::warning` — so the audit log cannot lie about dispatch.
- **Timeout reminder policy on `Garage`.** `Garage.timeout_reminder_policy` is one of `Garage::TIMEOUT_POLICIES = ['24_7', 'working_hours', 'on_call']`. `CheckJobTimeouts::dispatchStaffTimeout()` consults the policy: `24_7` fires immediately; `working_hours` calls `Garage::isWithinWorkingHoursNow()` (parses the `Garage.working_hours` JSON keyed by lowercase 3-letter day code), skipping dispatch when outside the window (the next hourly run picks it up — alerts are queued, never dropped); `on_call` resolves the current `MechanicOnCall` row covering `now()` and routes to that mechanic only, falling back to broadcasting to all assigned mechanics when the rotation has a gap (Poka-Yoke).
- **Working hours JSON format.** `Garage.working_hours` shape: `{"mon": {"open": "08:00", "close": "17:00"}, "tue": null, ...}`. Day key is `strtolower($now->format('D'))` (3-letter), missing or `null` = closed. Open/close are `H:i` strings, validated as `date_format:H:i` in `UpdateGarageSettingsRequest`.
- **Staff channel toggle meta-permission.** `Mechanic.canToggleChannels()` returns `mechanic.channel_toggle_allowed ?? garage.staff_channel_toggle_default ?? true`. When `false`, the mechanic is locked to all channels (Poka-Yoke for safety-critical alerts); `true` lets them later opt out of email/SMS individually. **In-app dashboard surface is always mandatory** — never silenced — regardless of toggle state.

---

## Where the docs live

| File | Purpose |
|---|---|
| `CLAUDE.md` | Project overview + docker commands + conventions |
| `docs/planning.md` | Domain model, state machine, i18n strategy, Poka Yoke design |
| `docs/tasks.md` | Phased implementation checklist (verify before trusting checkboxes) |
| `docs/features/garage-core/{README,architecture,COMPONENT_INVENTORY}.md` | SOP feature docs |
| `docs/database/migrations/NNN_*.md` | One doc per migration — schema, indexes, FKs, deviations |
| `docs/api/portal.yaml` | OpenAPI spec for customer portal endpoints |
| `docs/DATABASE_CONVENTIONS.md`, `docs/WORKFLOW_ENFORCEMENT.md` | Project-level overrides to playbook |
