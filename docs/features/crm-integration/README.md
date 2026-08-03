# CRM Integration

How `inteteam-garage` connects to `inteteam_crm`, matching the pattern the other Inte.Team apps use.

- **Contract, auth, tenancy, and the endpoints still owed by the CRM:** [`contract.md`](contract.md)
- Playbook references: `architecture/ecosystem-data-flow.md`, `architecture/sso-integration.md`, `cms/README.md` → "CRM Integration Pattern"

## Overview

The garage needs three things from the CRM: **who the customer is**, **a channel to notify them**, and **a way to take payment**. It is a consumer, not a writer — mirroring how `store_front` reads from the CRM and `inteteam_cms` reads one-way over HTTP.

```
                    Inte.Team SSO (auth.inte.team)
                    config/apps.php → garage.roles
                      ├── mechanic client → /auth/callback
                      └── customer client → /account/callback
                                  │
                       access token + claims
                       (sub, email, name, company_id)
                                  │
        ┌─────────────────────────┴──────────────────────────┐
        │                inteteam-garage                      │
        │                                                     │
        │  users.crm_user_id     ← SSO `sub`                  │
        │  garages.crm_company_id ← SSO `company_id`          │
        │  customers.crm_customer_id ← CRM SSO exchange       │
        └─────────────────────────┬──────────────────────────┘
                                  │
              Bearer CRM_INTERNAL_TOKEN (internal/*)
              public + throttled  (sso/exchange)
                                  │
                          inteteam_crm
                    source of truth: customers, money
                                  │
              X-Internal-Secret ──┘
              POST /webhooks/payment-confirmed
```

## Identity: three separate bindings

| Local column | Source | Bound where | Used for |
|---|---|---|---|
| `users.crm_user_id` | SSO `sub` claim | `SsoLoginController::syncCrmIdentity` | staff notification `recipient_id` |
| `garages.crm_company_id` | SSO `company_id` claim | `SsoLoginController::bindGarageToCrmCompany` (fill-only) | tenant scope on every internal call |
| `customers.crm_customer_id` | CRM SSO-exchange response | `CustomerSsoLoginController::callback` | the whole `/account` portal |

All three are self-configuring on first login. Nothing needs a ULID hand-copied into `.env`, though `CRM_COMPANY_ID` / `CRM_COMPANY_SLUG` remain as an app-wide fallback and Settings → CRM Connection allows correcting a wrong binding.

`Customer::isLinkedToCrm()` is the single source of truth for whether the account portal has data — never branch on `crm_customer_id === null` in callers.

## Service layers

```
Controllers
    ↓
CrmNotificationService  (customer events, never throws)
CrmStaffNotificationService (staff events, never throws, honest audit)
CrmPaymentService       (amount calc, request, confirm)
    ↓
CrmApiService           (HTTP, auth, tenancy, caching, retry)
```

`CrmApiService` is the only class that talks HTTP to the CRM. The domain wrappers above it own the failure policy: notifications are best-effort, payment failures are user-visible. See the degradation table in `contract.md`.

## Vehicle ↔ customer

`Vehicle.crm_customer_id` is entered by the mechanic on the vehicle form, with a `<datalist>` of ids already seen in this garage (`VehicleService::getReturningCustomerIds()`). Server-side existence checking against the CRM is gated on `CRM_CUSTOMER_LOOKUP_BY_ID_ENABLED` — see `contract.md` §1 for why it defaults off.

## Testing

| File | Covers |
|---|---|
| `tests/Feature/Crm/CrmApiContractTest.php` | request shape: bearer auth, route paths, required `company_id`, per-garage override, internal-vs-public URL |
| `tests/Feature/Crm/CrmOutageResilienceTest.php` | every CRM call failing: domain writes survive, payment surfaces a flash error |
| `tests/Feature/Auth/SsoLoginControllerTest.php` | claim → column bindings, OAuth state |
| `tests/Feature/Customer/CustomerSsoLoginControllerTest.php` | customer identity via the exchange endpoint |
| `tests/Feature/Webhooks/PaymentWebhookTest.php` | inbound webhook auth + idempotency |
| `tests/Feature/Api/InternalJobApiTest.php` | outbound-facing internal job API auth |

Assert on the **request**, not just a faked response. Four separate contract defects (wrong header, wrong path, missing `company_id`, wrong response key) survived a 244-test suite because nothing checked what was actually sent.

## Local development without a CRM

Leave `CRM_API_URL` empty. Every call short-circuits or logs and degrades; the app is fully usable. The account portal shows its unlinked-state banner, which is the correct representation of "no CRM".
