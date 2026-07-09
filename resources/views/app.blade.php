<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0f172a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Garage">
        <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon-180x180.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa-192x192.png') }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('pwa-512x512.png') }}">
        <title inertia>{{ config('app.name', 'InteTeam Garage') }}</title>
        {{-- Set the theme class before the CSS + React load, to prevent a
             flash of light UI on dark-mode users. Reads localStorage first,
             falls back to system preference. Mirror this logic in useTheme. --}}
        <script>
            (function() {
                try {
                    var stored = localStorage.getItem('theme');
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var isDark = stored ? stored === 'dark' : prefersDark;
                    if (isDark) document.documentElement.classList.add('dark');
                } catch (e) { /* localStorage blocked (e.g. private mode) — ignore */ }
            })();
        </script>
        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="antialiased bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        @inertia
    </body>
</html>
