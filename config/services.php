<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | InteTeam CRM
    |--------------------------------------------------------------------------
    |
    | The CRM's `internal/*` endpoints sit behind `VerifyInternalToken`, which
    | reads `$request->bearerToken()` and compares it to the CRM's own
    | `services.internal.token`. This app used to send `X-Internal-Secret`, a
    | header the CRM never reads — every internal call was unauthenticated and
    | answered 401. `token` is the value that actually authenticates; `secret` is
    | kept as the fallback so an existing deploy that only ever set
    | `CRM_API_SECRET` (with the right value) keeps working after this change.
    |
    | `internal_url` mirrors the SSO_INTERNAL_URL pattern: container-to-container
    | over the shared `proxy-tier` network, no public-internet round trip. Falls
    | back to the public URL when unset.
    |
    | The CRM is multi-tenant per company, and its internal endpoints are
    | company-scoped, so a garage cannot make a tenant-correct call without
    | knowing which CRM company it belongs to. `company_id` / `company_slug` are
    | the app-wide default; a `Garage` may override via `garages.crm_company_id`
    | (see GarageSettingsService) for multi-garage installs.
    |
    */

    'crm' => [
        'url' => env('CRM_API_URL'),
        'internal_url' => env('CRM_INTERNAL_URL', env('CRM_API_URL')),
        'token' => env('CRM_INTERNAL_TOKEN'),
        'secret' => env('CRM_API_SECRET'),
        'company_id' => env('CRM_COMPANY_ID'),
        'company_slug' => env('CRM_COMPANY_SLUG'),

        // Capability flag for `GET /api/v1/internal/customers/{id}`, which the CRM
        // does not expose yet (see docs/features/crm-integration/contract.md). The
        // vehicle form's "does this customer exist in CRM" rule reads a 404 as
        // "customer not found" — and a missing route also answers 404, so with the
        // endpoint absent the rule rejected every valid customer id and blocked
        // vehicle creation outright. Default off: skip the check rather than fail it.
        'customer_lookup_by_id_enabled' => (bool) env('CRM_CUSTOMER_LOOKUP_BY_ID_ENABLED', false),
    ],

    'gcs' => [
        'project_id' => env('GCS_PROJECT_ID'),
        'key_file_path' => env('GCS_KEY_FILE_PATH'),
        'bucket' => env('GCS_BUCKET'),
        'signed_url_expiry_minutes' => (int) env('GCS_SIGNED_URL_EXPIRY_MINUTES', 30),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'translation_model' => env('TRANSLATION_MODEL', 'gpt-4o-mini'),
        'detect_model' => env('DETECT_MODEL', 'gpt-4o-mini'),
    ],

    'garage' => [
        'internal_secret' => env('GARAGE_INTERNAL_SECRET'),
        'staff_notifications_via_crm_enabled' => (bool) env('GARAGE_STAFF_NOTIFICATIONS_VIA_CRM_ENABLED', false),
    ],

    'sso' => [
        'url' => env('SSO_URL'),
        'public_url' => env('SSO_PUBLIC_URL', env('SSO_URL')),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        // Separate OAuth2 client for the customer-facing account portal —
        // distinct from mechanic SSO so customer logins land on a different
        // redirect_uri and cannot be auto-elevated to the mechanic guard.
        'customer_client_id' => env('SSO_CUSTOMER_CLIENT_ID'),
        'customer_client_secret' => env('SSO_CUSTOMER_CLIENT_SECRET'),
    ],

    'dvla' => [
        // DVLA Vehicle Enquiry Service (VES) — returns MOT + Tax expiry plus vehicle metadata.
        // Register at https://register-for-the-vehicle-enquiry-service-vehicle-checker-api.service.gov.uk/
        'ves_url' => env('DVLA_VES_URL', 'https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles'),
        'ves_api_key' => env('DVLA_VES_API_KEY'),
        'ves_timeout' => (int) env('DVLA_VES_TIMEOUT', 10),
    ],

];
