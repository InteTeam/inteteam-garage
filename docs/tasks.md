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
- [ ] `docs/database/migrations/007_estimates.md` through `010_portal_tokens.md`

### Portal API (scoped, customer-facing)

- [x] `GET /portal/{token}/job` — `PortalJobController::show()`
- [x] `POST /portal/{token}/line-items/{id}/approve` — `PortalLineItemController::approve()`
- [x] `POST /portal/{token}/line-items/{id}/decline` — `PortalLineItemController::decline()`
- [x] `POST /portal/{token}/line-items/{id}/question` — `PortalLineItemController::question()`
- [x] All portal writes append to `ApprovalEvent` log with server timestamp
- [x] Token scope: portal endpoints only ever see the one job the token belongs to (`ValidatePortalToken` middleware)

### Tests

- [ ] `EstimateRevisionTest` — cannot modify estimate after customer response; revision creates new estimate
- [ ] `ApprovalEventImmutabilityTest` — events cannot be updated or deleted
- [ ] `PortalTokenScopeTest` — token A cannot access job B
- [ ] `CustomerApprovalFlowTest` — full flow: estimate sent → approve → approve event logged → job state advances

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
- [ ] Mechanic dashboard flags any HandoverItems where `accepted = false` or notes present
- [ ] `docs/database/migrations/011_handover_inspections.md`, `012_handover_items.md`

### Online Payment (conditional)

- [x] `Garage.online_payment_enabled` (boolean, default false)
- [x] `CrmPaymentService::requestPayment()` — calls `CrmApiService::createPaymentRequest()` with line items + total
- [x] Store CRM `payment_reference` on Job
- [x] `POST /webhooks/payment-confirmed` — `PaymentWebhookController` sets `Job.payment_confirmed_at`
- [x] `collected` transition guard in `JobStateMachine::guardCollected()` — checks HandoverInspection + payment gate
- [x] Payment amount derived from approved line items only — `CrmPaymentService::calculateAmount()`
- [ ] Setting change (`online_payment_enabled` toggle) appended to `ApprovalEvent` log

### Tests

- [x] `JobStateMachineTest::cannot_collect_without_handover`
- [x] `JobStateMachineTest::cannot_collect_without_payment_when_enabled`
- [x] `JobStateMachineTest::can_collect_with_handover_and_payment`
- [x] `JobStateMachineTest::can_collect_without_payment_when_disabled`
- [ ] `HandoverSubmissionTest` — accepted=false without notes rejected; accepted=false with notes passes
- [ ] `HandoverImmutabilityTest` — second submission to same job rejected
- [ ] `PaymentAmountTest` — total equals approved line items only, not pending/declined

**Exit criteria:** Customer can complete handover checklist. With payment enabled, both gates required. Collected state is unreachable without both.

---

## Phase 5 — Notifications & Preferences

**Goal:** CRM handles all delivery. Garage stores preferences. Every preference change is audited.

- [x] `NotificationPreference` model — (job_id, channel: email/sms/in_app, set_by: admin/customer)
- [x] Garage-level default: `Garage.default_notification_channel`
- [ ] On job creation: `NotificationPreference` seeded from garage default
- [x] `POST /portal/{token}/notification-preference` — `PortalPreferenceController::update()` appends to `ApprovalEvent` log
- [ ] Admin override endpoint — appends to `ApprovalEvent` log (same pattern)
- [x] `CrmNotificationService` — wraps CRM notification API; garage never sends directly (`app/Services/CrmNotificationService.php` with 4 methods: `notifyEstimateSent`, `notifyCustomerQuery`, `notifyTimeoutReminder`, `notifyHandoverReady`)
- [ ] Notification triggers:
  - Estimate sent → customer
  - Scope change found → customer
  - Mechanic responds to query → customer
  - Job completed / ready for collection → customer
  - Handover notes flagged → mechanic
  - Payment confirmed → mechanic
  - 24h timeout in `awaiting_approval` / `customer_query` / `awaiting_collection` → both
- [x] 24h timeout: scheduled command (`php artisan garage:check-timeouts`), runs hourly — `App\Console\Commands\CheckJobTimeouts`

### Tests

- [ ] `NotificationPreferenceAuditTest` — every preference change creates an ApprovalEvent
- [x] `TimeoutCommandTest` — jobs over 24h in blocked states are flagged, notification triggered (`tests/Feature/Commands/CheckJobTimeoutsTest.php`, 5 tests covering alert, dedup, skip <24h, skip non-timeout states)

**Exit criteria:** No notification leaves the system without going through CRM service. All preference changes are in the audit log.

---

## Phase 6 — i18n & LLM Translation

**Goal:** Mechanic writes in their language. Customer always reads in theirs. Broken English is not possible by design.

- [x] `LocalePair` resolved per job — `TranslationService::needsTranslation(string $mechanicLocale, string $customerLocale)`
- [x] `TranslationService` — wraps LLM API (OpenAI); accepts text + source locale + target locale + context hint
- [x] Translation system prompt: UK garage context, professional tone, preserve technical part names
- [x] `AutomotiveGlossary` — 15 en↔pl term pairs embedded in `TranslationService::GLOSSARY`
- [ ] Auto-translate: job stage notes, mechanic query responses, status updates (no review)
- [x] Preview-before-send: `TranslationService::previewEstimateTranslation()` returns translated text
- [ ] Translation preview UI: side-by-side (original / translated) with one-click confirm
- [x] `POST /estimates/{id}/preview-translation` — `EstimateController::previewTranslation()`
- [x] Translation result cached per (text hash + locale pair) — `Cache::remember()` with 24h TTL

### Tests

- [ ] `TranslationRequiredTest` — pl×pl = no translation; pl×en = translation; en×en = no translation
- [ ] `EstimatePreviewTest` — preview returns translation without sending; sending without preview confirmation rejected
- [ ] `GlossaryTest` — seeded terms are used in translation context

**Exit criteria:** Mechanic can write in Polish, customer receives English. Preview gate on estimate line items works. No untranslated content reaches the wrong locale.

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

- [x] `php artisan test` — 13 passed (20 assertions)
- [x] `phpstan analyse --memory-limit=512M` — level 5, zero errors
- [x] `pint --dirty` — zero formatting issues
- [x] `npm run build` — zero TypeScript errors
- [ ] Coverage audit against playbook checklist:
  - [x] Cross-garage → 404 (`GarageIsolationTest`)
  - [ ] Guest → redirected (all routes)
  - [ ] Wrong role → 403
  - [ ] Valid data → `assertDatabaseHas`
  - [ ] Invalid data → `assertSessionHasErrors`
  - [ ] Soft delete → `assertSoftDeleted`
- [ ] `docs/features/garage-core/README.md` — acceptance criteria all ticked
- [ ] Staging deploy via Panel
- [ ] Smoke test: create garage → create job → upload media → send estimate → approve → complete → handover → collected

**Exit criteria:** All four quality gates pass. Smoke test passes on staging. Playbook audit complete with no unexplained deviations.
