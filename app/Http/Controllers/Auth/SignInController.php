<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class SignInController extends Controller
{
    public function redirect(): RedirectResponse|Response
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if (Auth::guard('customer')->check()) {
            return redirect()->route('customer.dashboard');
        }

        $ssoUrl = config('services.sso.public_url');

        if (empty($ssoUrl)) {
            return Inertia::render('Auth/SsoSetup', [
                'callbackUrl' => route('auth.callback'),
                'missing' => array_values(array_filter([
                    'SSO_URL',
                    empty(config('services.sso.client_id')) ? 'SSO_CLIENT_ID' : null,
                    empty(config('services.sso.client_secret')) ? 'SSO_CLIENT_SECRET' : null,
                ])),
            ]);
        }

        // Local role picker instead of SSO's /apps/garage/continue — the SSO
        // picker requires GARAGE_*_CLIENT_ID env vars on the SSO server. Going
        // straight to /login or /account/login skips that entirely; each
        // controller builds /oauth/authorize using this app's own env.
        return Inertia::render('Auth/RolePicker', [
            'mechanicLoginUrl' => route('login'),
            'customerLoginUrl' => route('customer.login'),
        ]);
    }
}
