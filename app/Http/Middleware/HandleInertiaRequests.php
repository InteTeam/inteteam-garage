<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $customer = $request->user('customer');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                ] : null,
            ],
            'flash' => [
                'alert' => fn () => $request->session()->get('alert'),
                'type' => fn () => $request->session()->get('type'),
            ],
            // Browser-facing SSO base — Home.tsx links to {ssoPublicUrl}/logout
            // when a callback bounces back with an "sso" error, so the user can
            // clear the SSO session and retry as a different role.
            'ssoPublicUrl' => fn () => config('services.sso.public_url'),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ]);
    }
}
