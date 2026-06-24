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

    'crm' => [
        'url' => env('CRM_API_URL'),
        'secret' => env('CRM_API_SECRET'),
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
