# CRM Integration Contract

The wire contract between `inteteam-garage` and `inteteam_crm`, what is live today, and what the CRM still needs to ship.

Verified against `inteteam_crm/routes/api.php` and `app/Http/Middleware/VerifyInternalToken.php` on 2026-08-03.

---

## Authentication

| Surface | Direction | Auth |
|---|---|---|
| CRM `api/v1/internal/*` | garage → CRM | `Authorization: Bearer <CRM_INTERNAL_TOKEN>` |
| CRM `api/v1/auth/customer/{company}/sso/exchange` | garage → CRM | none (public, throttled 10/min) |
| Garage `POST /webhooks/payment-confirmed` | CRM → garage | `X-Internal-Secret: <GARAGE_INTERNAL_SECRET>` |
| Garage `GET /api/v1/jobs/{job}` | CRM → garage | `X-Garage-Secret: <GARAGE_INTERNAL_SECRET>` |

The CRM's `VerifyInternalToken` reads `$request->bearerToken()` and `hash_equals` it against its own `services.internal.token`. It ignores custom headers, so a bearer token is the only thing that authenticates. `CRM_INTERNAL_TOKEN` here must equal `CRM_INTERNAL_TOKEN` there.

Both garage-side surfaces **fail closed** when `GARAGE_INTERNAL_SECRET` is empty (500 + logged error). Leaving it unset does not disable auth.

## Tenancy

The CRM is multi-tenant per `companies.id`, and its internal endpoints are company-scoped — `InternalController::byEmail` returns 400 without `company_id`.

The garage resolves the CRM company in this order:

1. `Garage.crm_company_id` — per-garage, auto-filled from the SSO `company_id` claim on the first admin login (`SsoLoginController::bindGarageToCrmCompany`), overridable in Settings → CRM Connection
2. `CRM_COMPANY_ID` — app-wide fallback

`crm_company_slug` / `CRM_COMPANY_SLUG` is stored separately because the customer SSO-exchange route takes the company **slug** as a path segment while the internal endpoints take the **id**, and the CRM exposes no id↔slug lookup.

---

## Live endpoints

### `GET /api/v1/internal/customers/by-email`

Query: `email`, `company_id` (both required).

```json
{ "found": true, "email": "...", "customer_name": "...", "crm_url": "...", "bookings": [] }
```

Consumed by `CrmApiService::findCustomerByEmail()`, cached 60s. Answers only "does the CRM know this email" — the response carries **no customer id**, which is why identity binding goes through the SSO exchange instead.

### `POST /api/v1/auth/customer/{company_slug}/sso/exchange`

Body: `{"sso_access_token": "..."}` → `200 {"data": {"token", "expires_in", "customer": {"id", "name", "email", "phone"}}}`, `401` on invalid token, `404` on unknown company.

Consumed by `CrmApiService::exchangeCustomerSsoToken()` from the customer account-portal callback. The only CRM route that returns a customer id. It also finds-or-creates the CRM `Customer` and back-links their existing bookings and orders by email, so a first-time garage customer gets a real CRM record. This is the same endpoint `store_front` uses (playbook → `architecture/sso-integration.md`, "Next.js apps (Token Exchange pattern)").

---

## Endpoints the CRM still needs to ship

Until these exist, the corresponding garage features degrade rather than fail: notification calls are logged and swallowed, payment requests surface a flash error, payment history returns an empty list, and the customer-by-id lookup is switched off by a capability flag. **Nothing 500s and no domain write is lost** — but the customer never receives the notification.

### 1. `GET /api/v1/internal/customers/{id}`

Wanted response: `{"data": {"id", "name", "email", "phone", "locale"}}`, `404` when absent.

Used for the vehicle-form "customer exists in CRM" rule and for `locale` (which drives the LLM translation locale pair — without it every job falls back to the garage locale).

**Gated behind `CRM_CUSTOMER_LOOKUP_BY_ID_ENABLED`, default `false`.** A missing Laravel route answers 404, which is indistinguishable from "no such customer", so with the endpoint absent the rule rejected every valid customer id and blocked vehicle creation entirely. Flip the flag on once the route is deployed.

### 2. `POST /api/v1/internal/notifications`

Request the garage already sends:

```json
{
  "recipient_type": "customer" | "staff",
  "recipient_id": "<crm customer id | SSO sub>",
  "customer_id": "<crm customer id>",
  "company_id": "<crm company id>",
  "channel": "email" | "sms" | "in_app",
  "subject": "...",
  "body": "...",
  "meta": { "job_id": "...", "trigger": "estimate_sent" }
}
```

`recipient_type=staff` is additionally gated by `GARAGE_STAFF_NOTIFICATIONS_VIA_CRM_ENABLED` (default `false`); `recipient_id` there is the SSO `sub`, persisted as `users.crm_user_id`.

Triggers in use: `estimate_sent`, `customer_query`, `mechanic_response`, `scope_change`, `handover_ready`, `timeout`, compliance reminders; staff: `handover_flagged`, `payment_confirmed`, `timeout_alert`.

### 3. `POST /api/v1/internal/payments`

`{job_id, line_items: [{description, price}], total, customer_id, company_id}` → `{"reference": "..."}`.

The CRM is expected to call back `POST {garage}/webhooks/payment-confirmed` with `{job_id, payment_reference}` and the `X-Internal-Secret` header once paid. That handler is idempotent — a retry is a logged no-op, so it will not duplicate audit rows or re-notify mechanics.

### 4. `GET /api/v1/internal/payments?customer_id=&company_id=`

`{"data": [...]}`. Powers `/account/transactions`. Returns an empty list on any failure so the page stays reachable.

---

## Degradation rules

| Failure | Behaviour |
|---|---|
| Customer notification fails | `error` log, domain write kept (`CrmNotificationService` never throws) |
| Staff notification fails | `error` log, audit row written with `crm_dispatched: false` |
| Payment request fails | Flash error on the portal payment page, no `payment_reference` written |
| Payment history fails | Empty list |
| Customer-by-email fails | `null` → customer stays unlinked, dashboard shows the banner |
| SSO exchange fails | `null` → customer still logs in, unlinked-state banner |
| Customer-by-id fails | Vehicle validation passes through (graceful) unless the failure is a 404 *and* the capability flag is on |

Covered by `tests/Feature/Crm/CrmApiContractTest.php` (request shape) and `tests/Feature/Crm/CrmOutageResilienceTest.php` (behaviour with every CRM call failing).

---

## Ecosystem position

Per playbook `architecture/ecosystem-data-flow.md`: the CRM is the source of truth for customer identity and money; the garage owns the repair lifecycle. The garage reads customer identity from the CRM and pushes notification/payment intents to it. It never writes CRM business records directly — the SSO exchange endpoint is the CRM creating its own `Customer` row, on its own terms.
