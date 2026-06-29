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

        return redirect("{$ssoUrl}/apps/garage/continue");
    }
}
