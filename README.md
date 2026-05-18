# inteteam-garage

Garage management module for independent mechanics. Connects to `inteteam_crm` via API and shared SSO.

## What it does

Manages the full lifecycle of a vehicle repair job — from booking through diagnostics, estimates, customer approvals, and final sign-off — with photo/video evidence at every stage stored in Google Cloud Storage.

Customers receive a read-only portal link (no SSO required) where they can review media, approve or decline individual estimate line items, and ask questions pinned to specific photos or videos.

## Languages

English and Polish. Locale is resolved per user: if both mechanic and customer are Polish, the UI and all content stays in Polish. If either party is English-speaking, English is the default. Mechanics who write in Polish get LLM-assisted translation so customers always receive professional, accurate English — and vice versa. Technical automotive terminology is preserved via a domain-specific translation prompt.

## Connected systems

- **inteteam_crm** — source of truth for customer identity and bookings (read via API)
- **SSO** — mechanics log in through the shared InteTeam SSO; customer portal links are signed and require no login
- **CRM notification channels** — job status updates, estimate pushes, and approval alerts reuse existing CRM notification infrastructure
- **Google Cloud Storage** — all photos and videos; short-lived signed URLs served to customer portal

## Key docs

- [`docs/planning.md`](./docs/planning.md) — concept, domain model, job state machine, i18n strategy, data ownership

## Status

Pre-development. Concept phase.
