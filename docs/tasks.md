# inteteam-garage — Phased Tasks

**SOP reference:** inte-playbook `workflow/README.md` Steps 0–10  
**Stack:** Laravel 13 + PHP 8.3 + React 19 (canonical — confirmed via `composer.json` + `CLAUDE.md`)  
**Generator:** `integen laravel:resource` / `integen react:page` for all boilerplate

---

## Phase 0 — Scaffold & Documentation

**Goal:** Project exists, runs locally, SOP docs are in place. No business logic yet.

- [ ] Create project repo under InteTeam org
- [x] Copy Docker Compose setup from inteteam_crm (php-fpm, nginx, mysql, npm services)
- [x] `CLAUDE.md` + `.sop.md` created at project root
- [x] `docs/DATABASE_CONVENTIONS.md` created (FK types, naming)
- [x] `docs/WORKFLOW_ENFORCEMENT.md` created
- [x] `docs/features/garage-core/README.md` — business requirements + user stories + acceptance criteria
- [x] `docs/features/garage-core/architecture.md` — technical decisions (state machine, multi-tenancy, GCS, CRM API boundary)
- [x] `docs/features/garage-core/COMPONENT_INVENTORY.md` — list existing CRM components that can be reused before planning new ones
- [x] `docs/database/migrations/` directory created, sequential numbering starts at `001`
- [x] `phpunit.xml` configured (SQLite in-memory, queue sync, mail log)
- [x] `pint.json` configured (standard preset from playbook)
- [x] `phpstan.neon` configured (level 5, larastan extension)
- [x] `composer.json` scripts: `test`, `phpstan`, `pint`, `analyse`
- [x] Laravel app boots, health check route returns 200

**Exit criteria:** `php artisan test` passes (zero tests, zero failures), `php artisan serve` works, Docker stack runs.

---

## Phase 1 — Multi-Tenancy & Core Models

**Goal:** Garages, mechanics, vehicles, and jobs exist. State machine enforced. Multi-tenant isolation in place.

### Scope rename strategy (do once, before anything else)

integen generates `company_id` and `HasCompanyScope`. This project uses `garage_id` and `HasGarageScope`.

- [x] Create `app/Models/Traits/HasGarageScope.php` — copy of `HasCompanyScope` from CRM, scoped to `garage_id`
- [x] After each `integen` run, apply rename to generated files only

### Models & Migrations

- [x] `integen laravel:resource Garage --no-company-scoped --no-soft-deletes`
- [x] `integen laravel:resource Mechanic`
- [x] `integen laravel:resource Vehicle`
- [x] `integen laravel:resource Job`
- [x] `integen laravel:resource JobStage`
- [x] `integen laravel:resource Estimate`
- [x] `integen laravel:resource LineItem`
- [x] `integen laravel:resource NotificationPreference --no-soft-deletes`
- [x] Apply scope rename to all generated files
- [x] Migration: `job_mechanic` pivot table (job_id, mechanic_id) — manual, no integen
- [x] `docs/database/migrations/001_garages.md` through `009_repair_job_mechanic.md` (renumbered — actual migration sequence on disk uses `009_repair_job_mechanic`, not `008_job_mechanic`)

### React Pages (admin-facing CRUD)

- [x] `integen react:page Mechanic` — `Pages/Mechanics/Index.tsx` + `Form.tsx`
- [x] `integen react:page Vehicle` — `Pages/Vehicles/Index.tsx` + `Form.tsx`
- [x] `integen react:page Job` — `Pages/RepairJobs/Index.tsx` + `Form.tsx` + `Show.tsx`
- [x] `integen react:page Estimate` — `Pages/Estimates/Index.tsx` + `Form.tsx`

### State Machine

- [x] `JobStateMachine` service — enforces valid transitions, throws on invalid
- [x] Transition guards:
  - `in_progress → awaiting_approval` requires at least one line item
  - `approved → completed` requires all line items resolved
  - `completed → awaiting_collection` — auto-transition on mechanic marking complete
  - `awaiting_collection → collected` requires HandoverInspection submitted + (payment confirmed if `online_payment_enabled`)
- [x] State change writes to `JobStateTransition` log (who, from, to, timestamp)

### SSO

- [x] Mechanic login via InteTeam SSO (same flow as CRM — OIDC/token exchange) — `Auth/SsoLoginController`
- [x] `GarageMiddleware` — resolves current garage from authenticated mechanic (`EnsureGarageContext` + `CheckGarageRole`)
- [x] Role: `garage_admin` vs `mechanic` — `Mechanic::ROLE_GARAGE_ADMIN` / `ROLE_MECHANIC`

### Tests

- [x] `GarageIsolationTest` — cross-garage data access returns 404
- [x] `JobStateMachineTest` — each valid transition succeeds; each invalid transition throws (9 tests)
- [x] `MechanicAssignmentTest` — single / multiple / all assignment variations (9 tests, pending stack verification)

**Exit criteria:** Multi-tenant isolation verified. All state transitions covered by tests. PHPStan passes.

---

## Phase 2 — Media & GCS

**Goal:** Mechanics can upload photos/videos to GCS, locked to a job stage.

- [x] `GcsService` — upload, signed URL generation (15–60 min expiry), object naming (`{job_id}/{stage}/{timestamp}_{filename}`)
- [x] `integen laravel:resource Media` — (job_id, job_stage_id, gcs_path, mime_type, uploaded_by, uploaded_at)
- [x] Stage lock: `Media` cannot be attached to a stage once `JobStage.locked_at` is set (checked in `GcsService::upload()`)
- [x] `JobStage` locks automatically when job transitions past that stage (`JobStateMachine::lockStagesPastActivity()` — policy: stage locks when `STATE_ORDER[newState] > STATE_ORDER[STAGE_FINAL_ACTIVE_STATE[stage]]`)
- [x] API endpoint: `POST /jobs/{job}/stages/{stage}/media` — `MediaController::store()`
- [x] API endpoint: `GET /jobs/{job}/media` — `Api/JobApiController::media()` returns signed URLs
- [x] `docs/database/migrations/010_media.md` (filename is `010_` not `006_` — actual disk order; renumbering noted in `verify-task-list-vs-code` audit)

### Tests

- [x] `MediaUploadTest` — upload succeeds for current stage, fails for locked stage (3 tests: unlocked success, locked rejection, mime validation)
- [x] `SignedUrlTest` — signed URL generated, expires correctly (2 tests: configured expiry honored, batch `signedUrls()` keyed by media id)
- [x] `StageLockTest` — stage locks on job state transition, rejects subsequent uploads (3 tests: diagnosis stages lock at awaiting_approval, repair stage unlocked at approved then locks at completed, lock idempotent across repeat transitions)

**Exit criteria:** Photo upload to GCS works end-to-end. Signed URL returns accessible URL. Locked stage rejects upload.

---

## Phase 3 — Estimates & Customer Approval

**Goal:** Mechanic creates an estimate. Customer approves or declines line items via signed portal link. All events are immutable.

### Backend

- [x] `Estimate` model — (job_id, sent_at, revision_number)
- [x] `LineItem` model — (estimate_id, description, price, status: pending/approved/declined)
- [x] `ApprovalEvent` model — append-only (job_id, actor_type, actor_id, event_type, payload, occurred_at)
- [x] `ApprovalEventService` — single write path (`record()`, `recordBySystem()`)
- [x] Estimate revision rule — `Estimate::hasCustomerResponse()` guards new revisions
- [x] `SignedPortalToken` — short-lived token (job_id, expires_at, revoked_at); `SignedPortalTokenService::regenerate()`
- [x] `docs/database/migrations/007_estimates.md` through `010_portal_tokens.md` — present on disk under renumbered filenames: `005_estimates.md`, `007_line_items.md`, `011_signed_portal_tokens.md`, `012_approval_events.md` (renumbering noted in `verify-task-list-vs-code` audit)

### Portal API (scoped, customer-facing)

- [x] `GET /portal/{token}/job` — `PortalJobController::show()`
- [x] `POST /portal/{token}/line-items/{id}/approve` — `PortalLineItemController::approve()`
- [x] `POST /portal/{token}/line-items/{id}/decline` — `PortalLineItemController::decline()`
- [x] `POST /portal/{token}/line-items/{id}/question` — `PortalLineItemController::question()`
- [x] All portal writes append to `ApprovalEvent` log with server timestamp
- [x] Token scope: portal endpoints only ever see the one job the token belongs to (`ValidatePortalToken` middleware)

### Tests

- [x] `EstimateRevisionTest` — `tests/Feature/Portal/EstimateRevisionTest.php` (7 tests, 11 assertions). Also added the missing `EstimateService::update()` guard that throws when `Estimate::hasCustomerResponse()` is true — the model helper existed but no caller enforced it.
- [x] `ApprovalEventImmutabilityTest` — `tests/Feature/Portal/ApprovalEventImmutabilityTest.php` (6 tests, 17 assertions). Schema has no `created_at`/`updated_at`/`deleted_at`; model has `$timestamps = false` and no `SoftDeletes`; service only exposes `record` + `recordBySystem` (no update/delete methods).
- [x] `PortalTokenScopeTest` — `tests/Feature/Portal/PortalTokenScopeTest.php` (6 tests, 10 assertions). Covers expired/revoked/unknown tokens → 404, and that a token bound to job A cannot mutate a line item belonging to job B (same garage *or* different garage).
- [x] `CustomerApprovalFlowTest` — `tests/Feature/Portal/CustomerApprovalFlowTest.php` (5 tests, 25 assertions). Full happy path: portal view → approve both line items → `ApprovalEvent` rows logged → `JobStateMachine::transition` to `approved`. Also covers decline-with-notes, decline-without-notes (rejected), question event, and `completed` blocked while any line item is pending.

**Exit criteria:** Customer approval flow works end-to-end via signed token. Approval events are immutable. Token scoping is airtight.

---

## Phase 4 — Customer Handover & Online Payment

**Goal:** Customer inspects work at collection. Optional Pay Now via CRM API. `collected` state requires both gates.

### Handover Inspection

- [x] `HandoverInspection` model — (job_id, submitted_at, submitted_by_token)
- [x] `HandoverItem` model — (handover_inspection_id, line_item_id, accepted: bool, notes: nullable)
- [x] Validation rule: if `accepted = false`, `notes` is required — enforced in `PortalHandoverController::submit()`
- [x] `POST /portal/{token}/handover` — creates HandoverInspection + HandoverItems atomically; immutable after write
- [x] Appends `handover_submitted` event to `ApprovalEvent` log
- [x] Mechanic dashboard flags any HandoverItems where `accepted = false` or notes present — `resources/js/Pages/RepairJobs/Show.tsx` renders an amber alert banner with the affected line items; `JobController::show` eagerloads `handoverInspection.items.lineItem`. Also fixed a latent bug in `PortalHandoverController::submit` where `garage_id` was not being set on `HandoverItem` rows (NOT NULL violation surfaced once integration tests existed).
- [x] `docs/database/migrations/011_handover_inspections.md`, `012_handover_items.md` — present on disk as `014_handover_inspections.md` and `015_handover_items.md` (renumbered with the rest; renumbering noted in `verify-task-list-vs-code` audit).

### Online Payment (conditional)

- [x] `Garage.online_payment_enabled` (boolean, default false)
- [x] `CrmPaymentService::requestPayment()` — calls `CrmApiService::createPaymentRequest()` with line items + total
- [x] Store CRM `payment_reference` on Job
- [x] `POST /webhooks/payment-confirmed` — `PaymentWebhookController` sets `Job.payment_confirmed_at`
- [x] `collected` transition guard in `JobStateMachine::guardCollected()` — checks HandoverInspection + payment gate
- [x] Payment amount derived from approved line items only — `CrmPaymentService::calculateAmount()`
- [x] Setting change (`online_payment_enabled` toggle) appended to `ApprovalEvent` log — extracted `App\Services\GarageSettingsService`; toggle change appends an `EVENT_PREFERENCE_CHANGED` event per active job (state ≠ `collected`) with `from`/`to` payload. `GarageSettingsController::update` now delegates to the service.

### Tests

- [x] `JobStateMachineTest::cannot_collect_without_handover`
- [x] `JobStateMachineTest::cannot_collect_without_payment_when_enabled`
- [x] `JobStateMachineTest::can_collect_with_handover_and_payment`
- [x] `JobStateMachineTest::can_collect_without_payment_when_disabled`
- [x] `HandoverSubmissionTest` — `tests/Feature/Portal/HandoverSubmissionTest.php` (5 tests, 16 assertions). Covers happy path, declined-without-notes rejected, declined-with-notes accepted, missing items array, empty items array.
- [x] `HandoverImmutabilityTest` — `tests/Feature/Portal/HandoverImmutabilityTest.php` (2 tests, 4 assertions). Second submission to same job rejected; `(handover_inspection_id, line_item_id)` unique constraint blocks duplicate items at DB level.
- [x] `PaymentAmountTest` — `tests/Feature/Portal/PaymentAmountTest.php` (4 tests, 4 assertions). Zero when no estimate, zero with all-pending, sums only `STATUS_APPROVED`, excludes `STATUS_DECLINED`.
- [x] `OnlinePaymentToggleAuditTest` — `tests/Feature/Portal/OnlinePaymentToggleAuditTest.php` (2 tests, 8 assertions). Toggle change appends an `EVENT_PREFERENCE_CHANGED` event per non-collected job; settings save without toggle change does not log. Not in original task list — added to lock the toggle-audit contract.

**Exit criteria:** Customer can complete handover checklist. With payment enabled, both gates required. Collected state is unreachable without both.

---

## Phase 5 — Notifications & Preferences

**Goal:** CRM handles all delivery. Garage stores preferences. Every preference change is audited.

- [x] `NotificationPreference` model — (job_id, channel: email/sms/in_app, set_by: admin/customer)
- [x] Garage-level default: `Garage.default_notification_channel`
- [x] On job creation: `NotificationPreference` seeded from garage default — `RepairJob::booted()` hooks `created` and inserts the row via `firstOrCreate(['job_id' => ...])`, idempotent for re-saves. `set_by = 'admin'` because the garage admin's setting is what's being applied.
- [x] `POST /portal/{token}/notification-preference` — `PortalPreferenceController::update()` appends to `ApprovalEvent` log
- [x] Admin override endpoint — `PUT /jobs/{job}/notification-preference` (`JobNotificationPreferenceController::update`). Appends `EVENT_PREFERENCE_CHANGED` with `actor_type=mechanic` and `actor_id` = acting user; no-op + `info` flash when the channel is unchanged so we don't pollute the audit log.
- [x] `CrmNotificationService` — wraps CRM notification API; garage never sends directly. 6 methods: `notifyEstimateSent`, `notifyCustomerQuery`, `notifyTimeoutReminder`, `notifyHandoverReady`, `notifyScopeChange`, `notifyMechanicResponse`.
- [~] Notification triggers (7/7 customer-side wired; 2 mechanic-side wired via `CrmStaffNotificationService` stub + dashboard, **awaiting CRM staff-recipient endpoint** — see project memory `garage-phase5-deferred.md`):
  - [x] Estimate sent → customer — `EstimateController::send` → `notifyEstimateSent`
  - [x] Scope change found → customer — `POST /jobs/{job}/scope-change` (`ScopeChangeController`) wraps `ScopeChangeService::create()`: new `Estimate` revision + LineItems + `JobStateMachine::transition(approved → scope_change)` + `EVENT_SCOPE_CHANGE` + `notifyScopeChange`, all in one DB transaction.
  - [x] Mechanic responds to query → customer — `POST /jobs/{job}/line-items/{lineItem}/respond` (`LineItemResponseController`) records `EVENT_MECHANIC_RESPONSE`, auto-transitions `customer_query → awaiting_approval` (if applicable), fires `notifyMechanicResponse`.
  - [x] Job completed / ready for collection → customer — `JobController::transition` fires `notifyHandoverReady` when target state is `STATE_AWAITING_COLLECTION`.
  - [x] Handover notes flagged → mechanic — dashboard banner (`RepairJobs/Show.tsx`) + 2026-06-08 also wired through `CrmStaffNotificationService::notifyHandoverFlaggedToMechanic()` from `PortalHandoverController::submit()`. The CRM call is feature-flagged (`services.garage.staff_notifications_via_crm_enabled`, default off) until the CRM team ships the `recipient_type=staff` endpoint; audit `EVENT_STAFF_NOTIFICATION_DISPATCHED` fires regardless so the staff-alert chain is testable end-to-end today.
  - [x] Payment confirmed → mechanic — `Job.payment_confirmed_at` from `PaymentWebhookController` → dashboard + 2026-06-08 also wired through `CrmStaffNotificationService::notifyPaymentConfirmedToMechanic()`. Same feature-flag + audit pattern as handover.
  - [x] 24h timeout in `awaiting_approval` / `customer_query` / `awaiting_collection` → both — `CheckJobTimeouts` command fires `notifyTimeoutReminder` and writes `EVENT_TIMEOUT_ALERT` for the mechanic-dashboard side.
- [x] 24h timeout: scheduled command (`php artisan garage:check-timeouts`), runs hourly — `App\Console\Commands\CheckJobTimeouts`. 2026-06-08: rewritten for policy-aware dispatch (`Garage.timeout_reminder_policy` enum: `24_7` / `working_hours` / `on_call`). Working-hours mode defers the alert to the next in-hours run (Poka-Yoke: never drops); on-call mode resolves the current `MechanicOnCall` row, fallback to broadcast when nobody is on duty.

### Tests

- [x] `NotificationPreferenceAuditTest` — `tests/Feature/Jobs/NotificationPreferenceAuditTest.php` (5 tests, 17 assertions). Covers: auto-seed on job creation writes preference but **does not** log a `preference_changed` event, admin override logs with `actor_type=mechanic` and correct from/to payload, no-op admin override (same channel) does **not** log, non-admin mechanic is forbidden, customer portal override logs with `actor_type=customer`.
- [x] `ScopeChangeFlowTest` — `tests/Feature/Jobs/ScopeChangeFlowTest.php` (5 tests, 18 assertions). Happy path from `approved` (new revision_number, all items pending, audit event, notification), reject from wrong starting state (RuntimeException → 500), reject when `line_items` missing/empty (sessionHasErrors), 403 for non-admin.
- [x] `MechanicResponseTest` — `tests/Feature/Jobs/MechanicResponseTest.php` (4 tests, 11 assertions). Happy path from `customer_query` (audit + auto-transition to `awaiting_approval` + notification), response from `awaiting_approval` records event without transition, empty message rejected, 403 for non-admin.
- [x] `TimeoutCommandTest` — jobs over 24h in blocked states are flagged, notification triggered (`tests/Feature/Commands/CheckJobTimeoutsTest.php`, 5 tests covering alert, dedup, skip <24h, skip non-timeout states)

**Exit criteria:** No notification leaves the system without going through CRM service. All preference changes are in the audit log. **Status: garage-side complete, CRM-endpoint pending 2026-06-08.** All 7 customer-side triggers wired + tested. 2 staff-side triggers (handover-flag, payment-confirmed → mechanic) now wired through `CrmStaffNotificationService` stub — feature-flagged off until CRM team ships the `recipient_type=staff` polymorphic endpoint. Local audit (`EVENT_STAFF_NOTIFICATION_DISPATCHED`) fires regardless so the dispatch chain is testable today. Channel-toggle meta-permission, timeout-policy enum (`24_7`/`working_hours`/`on_call`), `MechanicOnCall` rotation table, audit events for staff-toggle-lock + timeout-policy changes — all in place. **Remaining blocker:** CRM team needs to ship the polymorphic recipient endpoint (decision (a) from `garage-phase5-deferred.md`). When ready, flip `GARAGE_STAFF_NOTIFICATIONS_VIA_CRM_ENABLED=true` and the same code path goes live. **Do not mark Phase 5 complete in release/deploy until this is verified end-to-end against the real CRM endpoint.**

---

## Phase 6 — i18n & LLM Translation

**Goal:** Mechanic writes in their language. Customer always reads in theirs. Broken English is not possible by design.

- [x] `LocalePair` resolved per job — `TranslationService::needsTranslation(string $mechanicLocale, string $customerLocale)`
- [x] `TranslationService` — wraps LLM API (OpenAI); accepts text + source locale + target locale + context hint
- [x] Translation system prompt: UK garage context, professional tone, preserve technical part names
- [x] `AutomotiveGlossary` — 15 en↔pl term pairs embedded in `TranslationService::GLOSSARY`
- [x] Auto-translate: job stage notes (Q2 — 2026-06-08), mechanic query responses (Q3 — 2026-06-08, preview-gated for Poka-Yoke). Stage notes: new `job_stages.notes` + `notes_translated` + locale-pair + timestamp columns; `JobStageService::updateNotes()` does eager translation at write time + caches by (text-hash, locale-pair) for 24h; portal shows "Translated by AI" disclaimer via `JobStage::notesWereTranslatedByAi()`. Query responses: `LineItemResponseController` now requires a `translated_message` field when the locale pair differs (422 otherwise); both versions recorded in the `ApprovalEvent` payload; companion `POST /jobs/{job}/line-items/{lineItem}/preview-response` endpoint returns the LLM translation for the side-by-side UI. **Not yet wired:** `JobStageController` form (next UI commit) — `updateNotes()` is service-callable today.
- [x] Preview-before-send: `TranslationService::previewEstimateTranslation()` returns translated text
- [~] Translation preview UI side-by-side — backend contract shipped 2026-06-08 (Q4 + Q5): `preview-translation` returns translations + `from_locale`/`to_locale`/`configured_from_locale`/`auto_detected_override`; `confirm-translation` accepts `confirmations[]` with `{id, translated_text, llm_raw_text}` per line item; service does diff-detection to set `translation_edited_by_mechanic_id` audit FK; atomic transaction flips `preview_confirmed_at` only if all rows persist. React component for the side-by-side editor + retry button is the remaining UI commit.
- [x] `POST /estimates/{id}/preview-translation` — `EstimateController::previewTranslation()`. **Q1 fully closed 2026-06-08:** `fromLocale` resolves via `Mechanic::resolvedLocale()` (`Mechanic.locale` column, nullable, runtime fallback to `Garage.locale`); `toLocale` resolves via `CrmApiService::getCustomerLocale()` (cached 1h, graceful `\Throwable` fallback to garage locale when CRM unreachable); `TranslationService::verifySourceLocale()` runs auto-detect on the first line-item sample and overrides `fromLocale` if the detector disagrees with the configured value (logged as `warning`).
- [x] Translation result cached per (text hash + locale pair) — `Cache::remember()` with 24h TTL

### Tests

- [x] `TranslationRequiredTest` — `tests/Feature/Translation/TranslationRequiredTest.php` (4 tests, 8 assertions). pl×pl + en×en short-circuit without hitting HTTP; pl×en + en×pl both call OpenAI exactly once.
- [x] `EstimatePreviewTest` — 2026-06-08 (Q4 + Q5): 5 tests covering (a) send blocks cross-locale without confirmation (`assertSessionHasErrors`), (b) same-locale send works without confirmation, (c) send works after `confirm-translation`, (d) confirm marks unedited line items with no editor FK, (e) confirm attributes the editor mechanic when `translated_text !== llm_raw_text`. Gate logic in `EstimateService::guardSendable()` throws `RuntimeException`; controller catches → session error.
- [x] `GlossaryTest` — `tests/Feature/Translation/GlossaryTest.php` (3 tests, 5 assertions). All 15 en↔pl glossary pairs verified present in captured system message; `estimate` context adds "repair estimate line item" guidance; identical-payload calls served from cache (1 HTTP call, not 2).

**Exit criteria:** Mechanic can write in Polish, customer receives English. Preview gate on estimate line items works. No untranslated content reaches the wrong locale.

**Status: backend complete, UI pending 2026-06-08.** All 5 questions (Q1–Q5) resolved by Piotr; backend implementation of every Phase 6 acceptance item shipped. Outstanding: (a) React side-by-side editor component for `confirm-translation` flow, (b) `JobStageController` form to accept `notes` field, (c) `MechanicController` form to accept `locale` field. None of these are blocked on questions — they are pure Inertia/React work and can be sequenced freely. See memory `garage-phase6-deferred.md` for the locked decisions.

---

## Phase 7 — Customer Portal API Surface

**Goal:** Portal service (store_front / customer portal app) can fetch all job data. Served on garage's own domain.

- [x] Define portal API contract: OpenAPI spec at `docs/api/portal.yaml` (280 lines)
- [x] All portal endpoints are token-scoped — `ValidatePortalToken` middleware, all routes in `routes/portal.php`
- [x] `GET /portal/{token}/timeline` — `PortalJobController::timeline()`
- [x] `GET /portal/{token}/handover` — `PortalHandoverController::show()`
- [~] CORS config: portal domain(s) allowed, not wildcard — `config/cors.php` exists; portal-domain allow-list still needs setting once production portal URL is confirmed
- [x] Signed token includes `garage_id` — stored on `SignedPortalToken` model
- [ ] Integration test: simulate full customer journey via portal API (estimate → approve → handover → collected)

**Exit criteria:** Portal API spec complete. Full journey integration test passes. CORS locked to portal domains.

---

## Phase 8 — Quality Gates & Launch Readiness

**Goal:** All gates pass. Playbook audit complete. Ready for staging deploy.

- [x] `php artisan test` — 117 passed (302 assertions) — count updated after 2026-06-07 playbook audit (added JobStageControllerTest, surfaced + fixed 5 pre-existing blockers)
- [x] `phpstan analyse --memory-limit=512M` — level 5, zero errors (107 files)
- [x] `pint --dirty` — zero formatting issues
- [x] `npm run build` — zero TypeScript errors
- [x] Coverage audit against playbook checklist (6/6):
  - [x] Cross-garage → 404 (`GarageIsolationTest`)
  - [x] Guest → redirected — `tests/Feature/GuestRedirectTest.php` parameterised across 8 protected routes (dashboard, jobs index/create, vehicles index/create, mechanics index/create, settings)
  - [x] Wrong role → 403 (`tests/Feature/RoleEnforcementTest.php` — 4 tests: non-admin cannot create/destroy mechanic, cannot update settings; admin can update settings)
  - [x] Valid data → `assertDatabaseHas` (widely covered across Portal, Handover, Approval-event tests)
  - [x] Invalid data → `assertSessionHasErrors` (covered in `HandoverSubmissionTest`, `CustomerApprovalFlowTest`, `PortalPaymentRequestTest`)
  - [x] Soft delete → `assertSoftDeleted` (`tests/Feature/SoftDeleteTest.php` — 3 tests covering Mechanic, Vehicle, Estimate destroy paths)
- [x] **Playbook audit (2026-06-08)** — third pass after the Phase 5 + Phase 6 implementation session. Eight 🔴 blockers found + closed:
  - `CrmStaffNotificationService` referenced `User.crm_user_id` but the column did not exist. Audit log was claiming `EVENT_STAFF_NOTIFICATION_DISPATCHED` on every call while the actual CRM HTTP call was always silently skipped. Added `users.crm_user_id` column (migration `023`, nullable, indexed); refactored `CrmStaffNotificationService::dispatch()` to early-return + `Log::warning` when the column is null and **not** write an audit row in that case. Honest audit log; SSO callback wiring is the remaining production task.
  - `GarageSettingsController::update` ignored the three new staff settings (`staff_channel_toggle_default`, `timeout_reminder_policy`, `working_hours`) — `GarageSettingsService::AUDITED_SETTINGS` was effectively dead code for `EVENT_STAFF_TOGGLE_LOCK_CHANGED` + `EVENT_TIMEOUT_POLICY_CHANGED`. Extracted `UpdateGarageSettingsRequest` Form Request with the new fields + `working_hours.*.open|close` `date_format:H:i` validation; controller delegates via `$request->validated()`.
  - `EstimateController` was 185 lines after Q1/Q4/Q5 wiring (cap is 150). Split into `EstimateController` (CRUD only — 72 lines) + `EstimateLifecycleController` (send/preview/confirm — 111 lines). Lifecycle helper `resolveLocalePair()` deduplicates the mechanic+customer locale resolution shared between `send` and `previewTranslation`. Extracted `Estimate\ConfirmTranslationRequest` Form Request. `EstimateService` gained `markSent()` so the controller no longer wraps state transition + estimate update + notification dispatch inline.
  - `GuestRedirectTest` parameterised provider was missing the new POST routes. Added rows for `estimates.confirm-translation`, `estimates.preview-translation`, `line-items.preview-response`.
  - `PaymentWebhookController` had zero coverage. New `tests/Feature/Webhooks/PaymentWebhookTest.php` (5 tests, 19 assertions) covers missing-secret 401, wrong-secret 401, valid payload → confirms payment + dispatches staff notification, valid payload without `crm_user_id` → confirms payment but **no** staff audit row, invalid payload → 422.
  - Handover-flagged staff notification dispatch had no integration coverage. New `tests/Feature/Portal/HandoverStaffNotificationTest.php` (4 tests, 11 assertions) covers declined-item dispatch, accepted-with-notes dispatch, all-clean → no dispatch, mechanic without `crm_user_id` → no audit row.
  - `LineItemResponseController::preview` (new Q3 endpoint) had no tests. New `tests/Feature/Jobs/MechanicResponsePreviewTest.php` (3 tests, 9 assertions) covers cross-locale → translation hits OpenAI, same-locale short-circuits, empty message → 422.
  - `docs/app-map.md` had zero references to any Phase 5/6 artifact. Refreshed routes table with the 3 new POST endpoints + the controller rename; services table updated for `EstimateService`, `GarageSettingsService`, `JobStageService`, `CrmApiService`, `TranslationService`, and a new `CrmStaffNotificationService` row; models tree gained `Garage ─hasMany─► MechanicOnCall` + `User.crm_user_id` annotation; approval-events table gained the three new constants; gotchas section gained seven new entries covering locale resolution, send-gate, eager stage notes, response-gate, staff-stub honesty, timeout policy semantics, working-hours JSON format, and the channel toggle meta-permission.
- [x] **Playbook audit (2026-06-07)** — second pass after Phase 5/6 work. Five additional pre-existing blockers found + closed:
  - 19 migrations under `database/migrations/` were missing `declare(strict_types=1);` (Rule 1). Batch-fixed via PowerShell regex replace. Includes the 3 framework defaults (users/cache/jobs) so the codebase is now 100% on rule 1.
  - `StoreJobStageRequest`, `UpdateJobStageRequest`, `UpdateEstimateRequest` had `garage_id` + `job_id` in `rules()` (Rule 8 — tenant spoof + redundant with route param). Removed all 6 keys. Servers derives `garage_id` via `HasGarageScope::creating`, `job_id` comes from route param.
  - `JobStageController` was the same Lesson #9 bug class as 2026-06-06's `EstimateController` fix — none of `index`/`store`/`show`/`update`/`destroy` accepted `RepairJob $job`, and the stage param was named `$jobStage` instead of route-matching `$stage`. Plus `authorize('viewAny', JobStage::class)` would have thrown (no `JobStagePolicy` exists). Fully refactored; auth now goes through `RepairJobPolicy::view|update`, `ensureStageBelongsToJob` guards cross-job stage tampering.
  - Latent bug surfaced during JobStageControllerTest: Form Requests used non-existent `'datetime'` validation rule (Laravel's `Validator::validateDatetime` doesn't exist — throws `BadMethodCallException`). Replaced with `'date'` across `StoreJobStageRequest.locked_at`, `UpdateJobStageRequest.locked_at`, `UpdateEstimateRequest.sent_at`. All three are now `['nullable', 'date']`.
  - New test file `tests/Feature/Jobs/JobStageControllerTest.php` (4 tests, 8 assertions) locks Lesson #9 + cross-job tamper guard + role enforcement, so this bug class can't regress on JobStageController again.
- [x] **Playbook audit (2026-06-06)** — full report run against `inte-playbook/laravel/README.md` + `workflow/README.md`. All blockers + all fixable items closed.

  **4 🔴 blockers fixed:**
  - `RepairJob.state` removed from `$fillable` (was bypassing `JobStateMachine` enforcement); 15 test files migrated `create([])` → `forceCreate([])`, `update(['state' => x])` → direct property + `->save()`.
  - `mechanics.user_id` migration upgraded from `unsignedBigInteger` to `foreignId('user_id')->constrained()->cascadeOnDelete()` (was a missing FK constraint).
  - Coverage gap: `PortalPaymentController` had zero tests — added `tests/Feature/Portal/PortalPaymentRequestTest.php` (5 tests, 11 assertions) covering happy path, disabled toggle, double-confirm, expired token.
  - Coverage gap: zero 403 tests + zero `assertSoftDeleted` — added the two test files above.
  - **Side fixes surfaced by tests**: `EstimateController::show|update|destroy` were missing the `{job}` route parameter (TypeError on destroy in tests); `StoreMechanicRequest` + `UpdateMechanicRequest` validated `user_id` as `ulid` while `users` is bigint. Both fixed.

  **10 🟡 fixable closed:**
  - `app/Providers/AppServiceProvider.php` — added `declare(strict_types=1);` (last file without it; 70/70 now).
  - New migration `20260606000002_add_remaining_garage_created_indexes.php` — `(garage_id, created_at)` on `vehicles`, `estimates`, `line_items`, `media`, `signed_portal_tokens`, `notification_preferences`; also `(garage_id, channel)` on `notification_preferences`.
  - Form Requests: removed `garage_id` rule from `StoreMechanicRequest`, `UpdateMechanicRequest`, `StoreVehicleRequest`, `UpdateVehicleRequest`. `garage_id` now derived server-side via `HasGarageScope::creating` hook from `session('current_garage_id')` — defence-in-depth against tenant spoofing.
  - 23 flash messages rewritten to canonical `'The X was Y.'` format across 12 controllers (Estimate, Job, GarageSettings, Mechanic, JobMechanic, JobStage, Vehicle, PortalLink, Portal/Handover, Portal/LineItem, Portal/Payment, Portal/Preference).
  - `tests/Feature/GuestRedirectTest.php` — parameterised over 8 protected routes, asserts redirect to `/login` for each.
  - `docs/app-map.md` refreshed: new "Services" section, `EVENT_PREFERENCE_CHANGED` second trigger noted, two new gotchas (estimate immutability after customer response, `garage_id` not accepted from client).
- [ ] `docs/features/garage-core/README.md` — acceptance criteria all ticked
- [ ] Staging deploy via Panel
- [ ] Smoke test: create garage → create job → upload media → send estimate → approve → complete → handover → collected

**Exit criteria:** All four quality gates pass. Smoke test passes on staging. Playbook audit complete with no unexplained deviations.
