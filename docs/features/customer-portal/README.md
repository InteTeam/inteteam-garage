# Feature: Customer Account Portal (SSO)

## Overview

A logged-in workspace at `/account/*` where a customer can:
- See every vehicle on file under their email (across all garages they've used)
- See every repair job ever opened for those vehicles, with read-only timeline + media + line items
- Approve / decline / question individual line items on an active estimate (same actions as the signed-token portal, but identity comes from SSO)
- Review past transactions pulled from CRM

Coexists with the existing **signed-token portal** at `/portal/{token}/*` — that flow stays in place for one-off no-login customer interactions (e.g. an emailed magic link to approve a single estimate). The account portal is additive.

## User stories

**Customer (post-SSO):**
- As a customer I log in once with my Inte.Team account and see everything tied to my email — no per-job magic links to track.
- As a customer with vehicles serviced at multiple garages, I see them all in one overview rather than juggling separate portals.
- As a customer I can approve/decline line items on the latest estimate, with the same per-item granularity as the signed link.
- As a customer I see my full transaction history (paid + pending) without asking the mechanic.

## Routing

| Method | Path | Controller | Notes |
|---|---|---|---|
| GET | `/account/login` | `Auth\CustomerSsoLoginController@redirect` | Renders `Auth/CustomerSsoSetup` when SSO env vars are unset. |
| GET | `/account/callback` | `Auth\CustomerSsoLoginController@callback` | OAuth2 code exchange + CRM email match + login on guard `customer`. |
| POST | `/account/logout` | `Auth\CustomerSsoLoginController@logout` | Inertia::location → home (see `SsoLoginController` for the same gotcha). |
| GET | `/account` | `Customer\DashboardController@index` | Vehicles list (with compliance traffic-light) + recent jobs. |
| GET | `/account/vehicles/{vehicle}` | `Customer\VehicleController@show` | Details + Compliance tab, read-only. |
| GET | `/account/jobs/{job}` | `Customer\JobController@show` | Read-only timeline + stages + media + line items. |
| POST | `/account/jobs/{job}/line-items/{lineItem}/approve` | `Customer\LineItemController@approve` | Triple ownership guard. |
| POST | `/account/jobs/{job}/line-items/{lineItem}/decline` | `Customer\LineItemController@decline` | `notes` required. |
| POST | `/account/jobs/{job}/line-items/{lineItem}/question` | `Customer\LineItemController@question` | `notes` required. |
| GET | `/account/transactions` | `Customer\TransactionController@index` | Pulled from CRM `/api/v1/internal/payments`. |

## Auth model

- **Guard**: `customer` (session driver). Distinct from `web` so a mechanic User can never accidentally satisfy a customer-scoped check.
- **Provider**: `customers` → Eloquent → `App\Models\Customer`.
- **Identity binding**: SSO `userinfo.email` → `customers.email` (unique, case-folded). On first login we call `CrmApiService::findCustomerByEmail()` and cache the resulting `crm_customer_id` on the row. If CRM doesn't recognise the email, the customer is still logged in but `crm_customer_id` stays null; dashboard renders a banner explaining the unlinked state.

## Data model

| Column | Notes |
|---|---|
| `id` (ULID) | Primary key. |
| `crm_customer_id` | Nullable. The bridge to CRM-owned data (vehicles, jobs, payments). Indexed. |
| `email` | Unique. Folded to lowercase before insert (mirrors SSO behaviour). |
| `name` | Display name. Prefers CRM-provided name once linked. |
| `last_login_at` | Stamped on every successful callback. |

`customers` has **no `garage_id`** — by design. A customer is a platform-level entity that may have vehicles across multiple garages.

## Poka-Yoke guards

Every controller that returns a vehicle/job uses one pattern:

```php
$resource = Model::withoutGlobalScopes()
    ->where('id', $id)
    ->when(
        $customer->isLinkedToCrm(),
        fn ($q) => $q->where('crm_customer_id', $customer->crm_customer_id),
        fn ($q) => $q->whereRaw('1 = 0'),
    )
    ->firstOrFail();
```

- `withoutGlobalScopes()` because customer queries are explicitly cross-garage; the mechanic guard (`HasGarageScope` + `current_garage_id` session) does not apply.
- `firstOrFail()` instead of any explicit 403 — leaking the existence of a foreign vehicle/job is a smaller hit than confirming it.
- Unlinked customer (`crm_customer_id IS NULL`) gets `whereRaw('1 = 0')` — every detail endpoint returns 404 even though they're logged in. The dashboard banner is the only thing they see.

For line item actions a **third** check sits on top of the above: the line item's `estimate_id` must match the job's `currentEstimate->id`. A line item from an older revision (or another job) of the same customer's collection cannot be acted upon.

## DVLA

The customer compliance view is intentionally **read-only**: no Refresh from DVLA button. Customers don't need a route into the shared gov.uk rate-limit bucket; mechanics control refresh cadence.

## CRM endpoints used

| Endpoint | Method | Notes |
|---|---|---|
| `/api/v1/internal/customers?email={email}` | GET | Email-to-id lookup. 404 = unknown (treated as null), other non-2xx → log warning + null. |
| `/api/v1/internal/payments?customer_id={crmCustomerId}` | GET | Transaction list. Any failure → empty list (the dashboard must not 500 on CRM blips). |

Both wrapped in `CrmApiService` with a 60s cache on the email lookup (multi-tab logins shouldn't hammer CRM).

## SSO setup (operator)

1. Create a **second** OAuth2 client in `inteteam_sso` admin (distinct from the mechanic client).
2. `redirect_uri` = `<garage-base>/account/callback` (`http://localhost:8085/account/callback` for dev).
3. Set in garage `.env`:
   ```
   SSO_CUSTOMER_CLIENT_ID=...
   SSO_CUSTOMER_CLIENT_SECRET=...
   ```
4. `docker compose restart php-fpm queue-worker`.
5. The Home page now shows two CTAs — "Customer login" + "Mechanic sign in".

## Acceptance criteria

- [x] Customer logs in via SSO callback; `customers` row created with `last_login_at` stamped.
- [x] CRM email match populates `crm_customer_id`; unmatched login still succeeds with banner.
- [x] Dashboard lists only the customer's own vehicles, sourced cross-garage.
- [x] Vehicle detail page returns 404 for foreign customers / unlinked customers.
- [x] Job detail page returns 404 for foreign customers / unlinked customers.
- [x] Line item approve/decline/question succeed only for the customer who owns the parent job; foreign customers get 404, no state change.
- [x] Form Requests reject decline/question without `notes`.
- [x] Transaction history pulled from CRM; CRM 5xx → empty list, not 500.
- [x] Logout invalidates session and redirects to home (not back to SSO login → would auto-relogin).
- [x] Customer guard cannot access mechanic routes; mechanic guard cannot access `/account/*` routes (each guard's middleware enforces this).

## Related docs

- `docs/features/customer-portal/architecture.md` — guard model, identity binding, failure modes
- `docs/features/customer-portal/COMPONENT_INVENTORY.md` — React reuse + new components + prop-shape gotchas
- `docs/database/migrations/028_customers.md` — initial `customers` table
- `docs/database/migrations/029_remember_token_on_customers.md` — idempotent follow-up adding `remember_token`
- `docs/database/migrations/030_widen_sessions_user_id.md` — `sessions.user_id` widened to hold ULIDs
- `docs/features/garage-core/portal.md` — original signed-token portal (still active).
- `inte-playbook/laravel/README.md` — guard/provider conventions this feature follows.
