# inteteam-garage — Planning Doc

**Status:** Concept phase — no code written  
**Date:** 2026-05-18  
**Stack target:** Laravel 12 + React 19 (mirrors inteteam_crm)

---

## Problem

Independent mechanics have no structured way to share repair progress with customers. The result is anxiety, disputes ("he said, she said"), and no paper trail for consent. The goal is a system where every action — diagnostic photo, estimate line item, customer approval — is timestamped, attributed, and immutable.

---

## Architecture

```
[ inteteam_crm ]  ←→  API  ←→  [ inteteam-garage ]
  Customer master                  Vehicles
  Bookings                         Jobs
  Notification channels            Estimates + Line Items
  SSO                              Media (GCS)
                                   Approval Event Log
                                   Customer Portal (signed link, no SSO)
```

**Data ownership boundary:**
- CRM owns: Customer identity, Bookings
- Garage owns: everything else — Vehicle, Job, Estimate, Line Item, Media, Approval events
- Nothing garage-specific flows back to CRM except a job-completed webhook to update booking status

---

## Domain Model

```
Customer (via CRM API)
  └── Vehicle
        └── Job (repair process)
              ├── JobStage (Pre-inspection / Disassembly / Fault Found / Repair / Complete)
              ├── Media (GCS) — attached to a stage, with timestamp + stage lock
              ├── Estimate
              │     └── LineItem (description, price, status: pending/approved/declined)
              └── ApprovalEvent (immutable log: who, what, when)
```

---

## Job State Machine

```
created
  → booked          (booking confirmed in CRM)
  → in_progress     (mechanic starts work)
  → awaiting_approval   (estimate sent to customer)
  → customer_query      (customer asked a question — mechanic must respond)
  → scope_change        (mechanic found hidden issue, new line item pending approval)
  → approved        (all line items resolved)
  → completed       (mechanic marks work done, final media uploaded)
  → collected       (customer collected vehicle)
```

**Timeout rule:** If job sits in `awaiting_approval` or `customer_query` for >24h, mechanic gets a dashboard flag and customer gets a chaser notification via CRM channels.

**Mid-disassembly scope change:** When a hidden issue is found, mechanic uploads photo to GCS, adds a line item, and pushes a notification. Customer can approve or pause. "Pause" is an explicit state — the mechanic acknowledges work is halted pending response. This prevents the car sitting stripped with no one knowing why.

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

Notifications (all channels): reuse `inteteam_crm` notification infrastructure. No new notification system built.

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

**Mechanic portfolio (future):**
- Completed jobs can be made public with customer consent
- Not V1

---

## Estimate Flow

1. Mechanic creates estimate with one or more line items
2. Estimate pushed to customer via CRM notification (email/in-app)
3. Customer views portal — each line item shows: description, price, [Approve] / [Decline]
4. For questions about a specific photo or line item: [Ask a Question] creates a `customer_query` event pinned to that item
5. Mechanic responds in dashboard; customer notified
6. All approvals/declines are appended to the immutable `ApprovalEvent` log with server timestamp

**For line items involving money:** before the estimate is sent, mechanic sees a preview of the translated content (if translation was applied). One-click confirm. This protects against mistranslated part names in a £400 line item.

---

## Audit Trail

Append-only event log. No record is ever deleted or updated in place — only new events are appended. Server-side timestamps only. This eliminates "he said, she said" disputes and provides a legally meaningful record of consent.

Customer read-only timeline shows:
- When car was booked
- When diagnostics began
- When each media item was uploaded (and to which stage)
- When estimate was sent
- When each line item was approved/declined/queried
- When work was completed
- When vehicle was collected

---

## i18n Strategy

Locale is resolved per user pair (mechanic × customer):

| Mechanic | Customer | UI + content lang | Translation |
|----------|----------|-------------------|-------------|
| pl | pl | Polish | None |
| en | en | English | None |
| pl | en | English default | Mechanic writes pl → LLM → customer sees en |
| en | pl | English default | Customer writes pl → LLM → mechanic sees en |

**LLM translation rules:**
- Free-text (status notes, job updates): auto-translate, no review needed
- Estimate line items with prices: show mechanic a translation preview before sending — one-click confirm
- Translation system prompt includes: UK garage context, professional tone instruction, preserve technical part names
- Common automotive terms (en ↔ pl): seeded glossary to reduce model guessing

**Mechanic never writes in English.** The system handles it. Poka Yoke: broken English to customers is not possible by design.

---

## SSO Integration

- Mechanic staff log in via shared InteTeam SSO (same as CRM)
- Customer portal: no SSO. Access is via a signed URL with a short-lived token tied to the job
- Token regeneration: customer can request a new link via the notification channel

---

## Open Questions (to resolve before SOP)

1. **Vehicle data:** UK DVLA registration lookup? MOT history integration? Or manual entry only for V1?
2. **Payment:** When all line items approved, does inteteam-garage trigger Monzo team payments (already built in CRM), or is payment handled outside the system for V1?
3. **Notification channel specifics:** Which CRM channels are used — email only, or also in-app? Confirm with CRM notification service.
4. **Multi-mechanic garage:** Is this single-mechanic (one user per garage) or does a garage have a team with roles (owner, technician)?
5. **Customer portal URL format:** `garage.inte.team/job/{signed_token}` or subdomain per garage?

---

## Next Steps

1. Resolve open questions above
2. Start SOP Step 1: feature directory + business requirements in project repo
3. `integen laravel:resource` for Vehicle, Job, Estimate, LineItem
4. Design GCS service layer
5. Design customer portal (Next.js or Laravel Blade + signed middleware?)
