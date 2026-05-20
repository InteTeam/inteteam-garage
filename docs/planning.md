# inteteam-garage — Planning Doc

**Status:** Concept phase — no code written  
**Date:** 2026-05-20  
**Stack target:** Laravel 12 + React 19 (mirrors inteteam_crm)

---

## Problem

Independent mechanics have no structured way to share repair progress with customers. The result is anxiety, disputes ("he said, she said"), and no paper trail for consent. The goal is a system where every action — diagnostic photo, estimate line item, customer approval — is timestamped, attributed, and immutable.

---

## System Boundary

inteteam-garage is a **staff-facing backend only**. Mechanics and garage admins use it. Customers never log in here.

```
[ inteteam_crm ]  ←→  API  ←→  [ inteteam-garage ]  ←→  API  ←→  [ Customer Portal ]
  Customer master                  Vehicles                            Served on garage's
  Bookings                         Jobs                                own domain
  Notification channels            Estimates + Line Items              (e.g. autofix.co.uk)
  SSO                              Media (GCS)                         No SSO — signed link
  Payment (Monzo)                  Approval Event Log
                                   Notification preferences
```

**Data ownership boundary:**
- CRM owns: Customer identity, Bookings, Notification delivery, Payment
- Garage owns: Vehicle, Job, Estimate, Line Item, Media, Approval events, Notification preferences
- Customer portal: read-only view of garage data via signed token — no write access except approvals
- Nothing garage-specific flows back to CRM except a job-completed webhook to update booking status

---

## Hosting

- inteteam-garage backend: `garage.inte.team` (mechanic/admin dashboard — staff only)
- Customer portal: served on each garage's own domain (e.g. `autofix.co.uk/track/{token}`) via the customer portal service
- Growth: `garage2.inte.team`, `garage3.inte.team` etc. as tenant volume grows

> **Future Enhancement:** White-label subdomain routing — each garage gets `repairs.{their-domain}.co.uk` pointing to the portal, resolved at Nginx/NPM level without new deployments.

---

## Multi-Tenancy

Each garage is a tenant. Mechanics belong to a garage. The assignment model mirrors `inteteam_crm` bookings:

- A job can be assigned to a single mechanic, multiple mechanics, or the entire garage team
- All job actions are attributed to the acting mechanic (not just "the garage")
- Cross-garage data isolation enforced at service layer (same pattern as CRM `HasCompanyScope`)

> **Poka Yoke:** Job create form requires at least one mechanic assigned before submission — prevents orphaned jobs that no one owns.

> **Future Enhancement:** Mechanic roles within a garage — Owner, Senior Technician, Apprentice — with role-gated actions (e.g. only Owner can send estimate to customer).

---

## Domain Model

```
Garage (tenant)
  └── Mechanic (staff user, SSO via CRM)
        └── Job (repair process)
              ├── Vehicle (manual entry, linked to CRM customer)
              ├── JobStage (Pre-inspection / Disassembly / Fault Found / Repair / Complete)
              ├── Media (GCS) — attached to a stage, with timestamp + stage lock
              ├── Estimate
              │     └── LineItem (description, price, status: pending/approved/declined)
              ├── ApprovalEvent (immutable log: who, what, when)
              └── NotificationPreference (channel per job — set by admin, overridable by customer, all changes audited)
```

> **Future Enhancement:** Vehicle history across jobs — same registration plate links previous repair records for returning customers.

---

## Job State Machine

```
created
  → booked               (booking confirmed in CRM)
  → in_progress          (mechanic starts work)
  → awaiting_approval    (estimate sent to customer)
  → customer_query       (customer asked a question — mechanic must respond)
  → scope_change         (mechanic found hidden issue, new line item pending approval)
  → approved             (all line items resolved)
  → completed            (mechanic marks work done, final media uploaded)
  → awaiting_collection  (customer portal shows inspection checklist + optional Pay Now)
  → collected            (customer completed handover + payment confirmed if required)
```

**Timeout rule:** If job sits in `awaiting_approval`, `customer_query`, or `awaiting_collection` for >24h, mechanic gets a dashboard flag and customer gets a chaser notification via CRM channels.

**Mid-disassembly scope change:** When a hidden issue is found, mechanic uploads photo to GCS, adds a line item, and pushes a notification. Customer can approve or pause. "Pause" is an explicit state — the mechanic acknowledges work is halted pending response. This prevents the car sitting stripped with no one knowing why.

> **Poka Yoke:** Transition from `in_progress` → `awaiting_approval` is blocked unless at least one line item exists on the estimate. Mechanics cannot send a blank estimate.

> **Poka Yoke:** Transition to `completed` is blocked unless all line items have a resolved status (approved or declined). No silent scope items left hanging.

> **Poka Yoke:** Transition to `collected` is blocked until the customer submits the handover inspection. If online payment is enabled, payment confirmation is also required. Neither can be skipped.

> **Future Enhancement:** SLA tracking per state transition — garage owner sees average time in each state across all jobs, useful for spotting bottlenecks.

---

## Communication Flow

No free-form chat. All customer interaction is structured:

| Action | Who | Mechanic dashboard effect |
|--------|-----|--------------------------|
| Approve line item | Customer | Line item → `approved` |
| Decline line item | Customer | Line item → `declined`, job flagged |
| Ask a question | Customer | Pinned to specific media or line item; job → `customer_query` |
| Approve scope change | Customer | New line item → `approved`, work resumes |
| Pause scope change | Customer | Job → `scope_change` paused, mechanic alerted |

Notifications (all channels): reuse `inteteam_crm` notification infrastructure. No new notification system built in garage.

> **Future Enhancement:** Mechanic can add an internal note (never visible to customer) — useful for team handover or parts ordering context.

---

## Notification Preferences

Delivery is always handled by `inteteam_crm`. Garage controls the preference for each job:

1. **Garage admin sets the garage-level default** (e.g. email-only for all jobs)
2. **Customer can override their preference** via the portal (e.g. switch to SMS if available)
3. **Every change** — who changed it, what it was changed to, when — is appended to the `ApprovalEvent` audit log

> **Poka Yoke:** Preference changes by the customer are shown back to them as a confirmation step: "You've changed notifications for this job to SMS. Continue?" — prevents accidental taps.

> **Poka Yoke:** Admin changes to an active job's preference trigger a log entry automatically — the system never lets a preference change happen silently mid-job.

> **Future Enhancement:** Per-stage notification rules — customer only notified at estimate-send and completion, not at every photo upload. Reduces notification fatigue without losing the audit trail.

---

## Media Lifecycle

All photos and videos go to Google Cloud Storage.

**On upload:**
- Metadata locked at upload time: timestamp, uploader, job stage
- Stage is immutable after the job progresses past it
- GCS object name includes `job_id/stage/timestamp` — no renaming

**Customer portal access:**
- Short-lived signed URLs generated on demand (15–60 min expiry)
- Customer portal is a signed link, no SSO required
- Before/after view: diagnostic media contrasted with completed repair media

> **Poka Yoke:** Stage lock prevents retroactive media uploads to a completed stage. A mechanic cannot add a "pre-inspection" photo after the job is in `repair` state — preserves the integrity of the timeline.

> **Future Enhancement — Mechanic portfolio:** Completed jobs made public with customer consent. Not V1.

---

## Estimate Flow

1. Mechanic creates estimate with one or more line items
2. Before sending, mechanic sees a preview — including translated content if translation was applied
3. One-click confirm sends estimate to customer via CRM notification
4. Customer views portal — each line item shows: description, price, [Approve] / [Decline]
5. For questions about a specific photo or line item: [Ask a Question] creates a `customer_query` event pinned to that item
6. Mechanic responds in dashboard; customer notified
7. All approvals/declines are appended to the immutable `ApprovalEvent` log with server timestamp

> **Poka Yoke:** For line items with a price, mechanic sees a translation preview before sending — protects against a mistranslated part name in a £400 line item. Confirmation required, no auto-send.

> **Poka Yoke:** Estimate cannot be re-sent after the customer has already responded to it. A new estimate revision must be created — prevents overwriting a customer's recorded approval.

> **Future Enhancement:** Estimate templates — common repair types (brake pads, MOT prep) pre-fill standard line items and prices. Mechanic adjusts rather than types from scratch.

---

## Audit Trail

Append-only event log. No record is ever deleted or updated in place — only new events are appended. Server-side timestamps only. This eliminates "he said, she said" disputes and provides a legally meaningful record of consent.

Covers:
- Job state transitions
- Every approval, decline, and question
- Media uploads (stage, uploader, timestamp)
- Estimate sends and revisions
- Notification preference changes (who changed it, old value, new value)
- Mechanic assignments and reassignments

Customer read-only timeline shows:
- When car was booked
- When diagnostics began
- When each media item was uploaded (and to which stage)
- When estimate was sent
- When each line item was approved/declined/queried
- When work was completed
- When vehicle was collected

> **Poka Yoke:** Audit log is written by the system, never by the mechanic directly. There is no "add a note to the audit log" UI — all entries are system-generated from real actions.

> **Future Enhancement:** Export audit trail as a PDF — useful if a customer disputes a repair after collection. Legally verifiable record with all timestamps and approvals.

---

## i18n Strategy

Locale resolved per user pair (mechanic × customer):

| Mechanic | Customer | UI + content lang | Translation |
|----------|----------|-------------------|-------------|
| pl | pl | Polish | None |
| en | en | English | None |
| pl | en | English default | Mechanic writes pl → LLM → customer sees en |
| en | pl | English default | Customer writes pl → LLM → mechanic sees en |

**LLM translation rules:**
- Free-text (status notes, job updates): auto-translate, no review needed
- Estimate line items with prices: show mechanic translation preview before sending — one-click confirm
- Translation system prompt: UK garage context, professional tone, preserve technical part names
- Common automotive terms (en ↔ pl): seeded glossary to reduce model guessing

**Mechanic never writes in English.** The system handles it.

> **Poka Yoke:** Broken English to customers is not possible by design — there is no "send without translation" option when locale pair requires it.

> **Future Enhancement:** Additional language pairs beyond en/pl — driven by actual garage customer demographics. The glossary and system prompt pattern scales without code changes.

---

## Vehicle Data

V1: manual entry only (registration, make, model, year).

> **Future Enhancement:** UK DVLA registration lookup — enter plate, auto-fill make/model/year/colour. MOT history via DVSA API for pre-inspection context.

---

## Customer Handover Inspection

When the mechanic marks a job `completed`, the job transitions to `awaiting_collection`. The customer receives a notification and opens the portal to a handover screen.

**Handover screen shows:**
- All approved line items (what was worked on + price)
- Per-item: checkbox "I have inspected and accept this work" + optional notes field
- A general notes field for anything not tied to a specific item (e.g. "scratch on bumper pre-existing")
- If online payment is enabled: itemised total + Pay Now button

**Submission rules:**
- Every item must be either ticked (accepted) or have a note explaining why it was not accepted
- Submitting the form creates an immutable `HandoverInspection` record in the audit log (who, what time, which items accepted, all notes)
- If online payment is enabled, payment must be confirmed before `collected` is set

**On abnormality notes:**
- Any item with a note (whether ticked or not) is flagged on the mechanic's dashboard
- Mechanic is notified but collection is not blocked — the customer has the car back and the record is sealed
- The note is in the audit log permanently

> **Poka Yoke:** If a customer does not tick an item, the notes field for that item becomes required. "Not inspected, no reason given" is not a valid submission.

> **Poka Yoke:** Handover form is read-only once submitted — no editing after the fact.

> **Future Enhancement:** Customer can upload a photo alongside their abnormality note (e.g. pre-existing damage evidence). Creates a counter-record in the audit log.

---

## Online Payment (Optional, per Garage)

Garage admin can enable `online_payment` as a garage-level setting. Payment is processed entirely by `inteteam_crm` (Monzo team payments, already built). The garage only initiates and listens.

**Flow when enabled:**
1. Job reaches `completed` → garage calls CRM API to create a payment request with the total of all approved line items
2. CRM returns a payment reference + Pay Now URL
3. Customer portal shows the itemised breakdown and a Pay Now button (links to or embeds CRM payment flow)
4. CRM sends a payment-confirmed webhook to garage
5. Garage marks payment as confirmed; `collected` transition becomes available once handover is also complete

**Flow when disabled:**
- Handover inspection still happens
- `awaiting_collection` → `collected` triggered by handover submission alone

**Garage setting:** `online_payment_enabled` (boolean, per garage). Toggled by garage admin. Change is audit logged.

> **Poka Yoke:** Payment amount is calculated from approved line items only — no mechanic can manually enter a different total. The number the customer pays is the number they already approved.

> **Poka Yoke:** `collected` requires both handover + payment (if enabled). They can be done in either order, but both gates must be passed. No half-completed collections.

> **Future Enhancement:** Split payment — customer pays a deposit at estimate approval, remainder at collection. CRM payment service already supports this pattern.

---

## SSO Integration

- Mechanic staff log in via shared InteTeam SSO (same as CRM)
- Customer portal: no SSO. Access via signed URL with short-lived token tied to the job
- Token regeneration: customer requests a new link via their notification channel

---

## Customer Portal Integration

The customer-facing view is a **separate service** (customer portal / store_front) that calls the garage API. It is not part of inteteam-garage.

- Portal served on the garage's own domain (e.g. `autofix.co.uk/track/{signed_token}`)
- Garage API exposes a read-only, token-scoped endpoint per job
- Approvals and questions submitted via the portal are the only write operations customers can perform
- inteteam-garage never exposes the full admin API to the portal — scoped token only sees its own job

> **Future Enhancement:** Portal theming per garage — logo, brand colour, contact details — so the customer experience feels like the garage's own product, not InteTeam's.

---

## Next Steps

1. Start SOP Step 1: feature directory + business requirements in project repo
2. `integen laravel:resource` for Vehicle, Job, Estimate, LineItem
3. Design GCS service layer
4. Design mechanic assignment model (mirrors CRM booking assignment)
5. Design notification preference model + audit hook
6. Agree customer portal API contract with store_front team
