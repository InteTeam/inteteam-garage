# Customer Account Portal — Architecture

## Coexistence with the signed-token portal

Two customer-facing entry points share the codebase:

| Portal | Identity | Lifetime | Use case |
|---|---|---|---|
| `/portal/{token}/*` (signed-token) | per-job HMAC token | until token expiry/rotation | one-off email magic links — "approve this estimate" |
| `/account/*` (SSO account) | SSO email → `Customer` row | session-bound, refreshable | always-on workspace across all the customer's jobs/garages |

They are **additive**, not a migration. The signed-token portal stays in place. The two flows share the same approval domain model (`Estimate`, `LineItem`, `ApprovalEvent`) and the same service layer (`LineItemDecisionService`); they differ only in how the actor identity is resolved at the edge.

## Guard model

Two distinct Laravel auth guards:

```
config/auth.php
├── guards.web         driver=session, provider=users      → App\Models\User (mechanic, bigint id)
└── guards.customer    driver=session, provider=customers  → App\Models\Customer (ULID id)
```

Both use the database session driver and share the `sessions` table (column `user_id` widened to `varchar(36)` in migration 030). The customer guard cannot satisfy `auth:web` checks and vice versa; `bootstrap/app.php` `redirectGuestsTo` sends unauth `/account/*` to `customer.login` and everything else to `login` so the two flows do not cross-bleed at the redirect layer.

## Identity binding to CRM

SSO `userinfo.email` is the join key. On first login the callback calls `CrmApiService::findCustomerByEmail` (60s cache) and:

- **Match found** → `crm_customer_id` written to the local `Customer` row; banner suppressed; dashboard renders vehicles/jobs.
- **No CRM record** → `Customer` row created with `crm_customer_id = null`; banner shown; **every detail endpoint returns 404** via the unlinked-guard pattern (`whereRaw('1 = 0')`), even though the user is technically logged in.
- **CRM outage** → identical to "no record" — fail-closed; the dashboard's CRM dependency is the right level of coupling.

A customer who later gets added in CRM will pick up `crm_customer_id` on their next login (callback retries the lookup every time).

## Ownership / Poka-Yoke triple guard

Every controller method that returns customer-owned data follows the same pattern:

```php
Model::withoutGlobalScopes()
    ->where('id', $id)
    ->when(
        $customer->isLinkedToCrm(),
        fn ($q) => $q->where('crm_customer_id', $customer->crm_customer_id),
        fn ($q) => $q->whereRaw('1 = 0'),
    )
    ->firstOrFail();
```

- `withoutGlobalScopes()` — customer queries are cross-garage; mechanic `HasGarageScope` + `current_garage_id` session does not apply.
- `firstOrFail()` not `403` — the existence of a foreign vehicle/job is itself information; 404 is the safer reveal.
- Unlinked guard returns no data via a hard-false predicate, not a special branch — one query path, two outcomes.

`LineItemController` adds a state gate (`LineItemDecisionService::ACTIONABLE_STATES`) so a customer cannot mutate line items on a `collected` job via direct POST; the gate returns 409 (separate from 404) so the audit trail distinguishes "trying to act on the wrong moment of your own job" from "trying to act on a stranger's job."

## Service-layer responsibilities

| Service | Role in the customer portal |
|---|---|
| `CrmApiService` | Email → CRM customer lookup (60s cache); payment history fetch. Both swallow non-2xx into `null`/`[]` so dashboard renders even when CRM is degraded. |
| `LineItemDecisionService` | Shared with the signed-token portal. Holds the actionable-states whitelist + writes audit events. Now requires `$actorId` — `customer.id` for the SSO portal, `token` for the signed portal. |
| `GcsService::signedUrl` | Mints presigned URLs for `Media` rows. Customer's `JobController` calls it during serialization — Eloquent's auto `toArray` only returns `gcs_path`, which is not browser-fetchable. |

## What is deliberately *not* in scope

- **Customer self-registration.** Identity originates in SSO; the garage cannot mint customer accounts.
- **Email change.** If a customer changes their SSO email upstream, the next login creates a fresh `Customer` row at the new email; the CRM link re-resolves via the new lookup. The orphan row at the old email is harmless but accumulates — out of scope to clean up here.
- **DVLA refresh from the customer view.** Vehicle compliance is read-only; mechanics own the gov.uk rate-limit bucket.
- **Cross-garage payment view.** Transactions are pulled from CRM by `crm_customer_id` only; there is no per-garage payment aggregation beyond what CRM already exposes.

## Failure modes worth documenting

| Failure | Behavior |
|---|---|
| CRM API down at login | `Customer` row created, `crm_customer_id = null`, banner shown. Login still succeeds. |
| CRM API down on transactions page | Empty list rendered. No 500. |
| SSO session sticky across roles (e.g., logged in as customer, click Mechanic sign in) | Mechanic callback finds no `Mechanic` row → `redirect(route('home'))` with `errors.sso`. Home renders the error banner with "Log out of SSO" link (calls SSO's `logoutWithRedirect`). |
| Customer logs out | Both garage session and SSO session cleared; redirected back to home. Next login button shows a real form. |
| Customer SSO email changes upstream | New `Customer` row created on next login; old row orphaned but no data leak (the unique CRM id moves with them). |
