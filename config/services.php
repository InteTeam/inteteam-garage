<?php

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
    ],

    'garage' => [
        'internal_secret' => env('GARAGE_INTERNAL_SECRET'),
    ],

    'sso' => [
        'url' => env('SSO_URL'),
        'public_url' => env('SSO_PUBLIC_URL', env('SSO_URL')),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
    ],

];
