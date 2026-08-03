# 031 — `garages.crm_company_id` + `garages.crm_company_slug`

Migration: `20260803000001_add_crm_company_id_to_garages_table.php`

## Why

`inteteam_crm` is multi-tenant per `companies.id`, and every one of its `api/v1/internal/*` endpoints is company-scoped — `InternalController::byEmail` returns `400 company_id parameter required` without it. This app had no CRM company column at all, so no CRM call could be tenant-correct regardless of auth. Adding the mapping was a precondition for the integration working at all.

Two columns because the CRM keys the two route families differently and offers no id↔slug lookup:

| Route | Identifier |
|---|---|
| `GET /api/v1/internal/customers/by-email?company_id=` | ULID |
| `POST /api/v1/auth/customer/{company}/sso/exchange` | slug (path segment) |

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `crm_company_id` | `varchar(36)` | yes | `null` | CRM `companies.id` (ULID, 26 chars). Sized 36 to tolerate a future uuid without another migration. |
| `crm_company_slug` | `varchar(255)` | yes | `null` | CRM `companies.slug` |

Position: `after('slug')`.

## Indexes

| Name | Columns |
|---|---|
| `idx_garages_crm_company` | `crm_company_id` |

## Foreign keys

**None.** The referenced row lives in a different database owned by another service. A cross-service FK is not expressible and would be wrong even if it were — the CRM company can be deleted or renamed independently.

## Nullability

Deliberately nullable:

- a garage can exist before its CRM company is provisioned
- `CrmApiService::resolveCompanyId()` falls back to the app-wide `CRM_COMPANY_ID` when the column is null, so a single-tenant install needs no per-garage value at all

## How it gets populated

1. **Automatically** — `SsoLoginController::bindGarageToCrmCompany()` writes it from the SSO `company_id` claim on the first garage-admin login. `TokenClaimsService` in `inteteam_sso` emits that claim whenever the access token carries a company context.
2. **Manually** — Settings → CRM Connection (`UpdateGarageSettingsRequest`, admin-only via `GaragePolicy`).

The automatic path is **fill-only**: it never overwrites a non-empty value. A mechanic whose SSO account belongs to more than one company could otherwise silently repoint the entire garage's CRM traffic by logging in.

## Model

`Garage` — added to `$fillable` and to the class docblock. No cast (plain strings). Not added to `GarageSettingsService::snapshot()`, so a change does **not** append `EVENT_PREFERENCE_CHANGED` rows to every active job — it is deployment configuration, not a customer-visible policy switch, and follows the same reasoning as `compliance_reminders_{windows,types}`.

## Rollback

`down()` drops the index then both columns. Safe: no FK, no data derived from these values.

## Tests

`tests/Feature/Auth/SsoLoginControllerTest.php`
- `test_callback_binds_garage_to_crm_company_from_claims`
- `test_callback_does_not_overwrite_an_existing_crm_company`

`tests/Feature/Crm/CrmApiContractTest.php`
- `test_per_garage_company_override_wins`
- `test_customer_lookup_is_skipped_without_a_company_id`

## See also

`docs/features/crm-integration/contract.md`
