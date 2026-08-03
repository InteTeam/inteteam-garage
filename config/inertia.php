<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | This app has no SSR bundle — `vite.config.js` builds a client bundle only.
    | The package default is `enabled => true`, which makes every Inertia
    | response try to reach the SSR runtime before falling back to client-side
    | rendering. Disabling it explicitly removes that per-request round trip.
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | The package default is `resource_path('js/pages')` (lowercase). This app
    | uses `resources/js/Pages` (capital P). On a case-insensitive filesystem
    | (Windows/macOS dev) the default resolves anyway; on a case-sensitive one
    | (Linux CI, the Docker php-fpm container, production) it does not — the
    | `assertInertia` page-existence check then fails every test that asserts a
    | component name, with "Inertia page component file [X] does not exist".
    |
    | Keep this path in sync with the `resolve()` glob in `resources/js/app.tsx`.
    |
    */

    'pages' => [

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [
            'js',
            'jsx',
            'ts',
            'tsx',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | Left ON deliberately: it is what catches a controller rendering a page
    | component that was renamed or never created (playbook lesson #19 —
    | `Pages/Estimates/Form.tsx` was removed for exactly this reason).
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
