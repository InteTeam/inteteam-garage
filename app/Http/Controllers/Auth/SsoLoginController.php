<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

final class SsoLoginController extends Controller
{
    public function redirect(): RedirectResponse|Response
    {
        $ssoUrl = config('services.sso.public_url');
        $clientId = config('services.sso.client_id');

        if (empty($ssoUrl) || empty($clientId)) {
            return Inertia::render('Auth/SsoSetup', [
                'callbackUrl' => route('auth.callback'),
                'missing' => array_filter([
                    empty($ssoUrl) ? 'SSO_URL' : null,
                    empty($clientId) ? 'SSO_CLIENT_ID' : null,
                    empty(config('services.sso.client_secret')) ? 'SSO_CLIENT_SECRET' : null,
                ]),
            ]);
        }

        $callbackUrl = route('auth.callback');

        return redirect("{$ssoUrl}/oauth/authorize?client_id={$clientId}&redirect_uri={$callbackUrl}&response_type=code&scope=");
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        $response = Http::post(config('services.sso.url') . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.sso.client_id'),
            'client_secret' => config('services.sso.client_secret'),
            'redirect_uri' => route('auth.callback'),
            'code' => $code,
        ]);

        if ($response->failed()) {
            return redirect()->route('login')->withErrors(['sso' => 'Authentication failed.']);
        }

        $tokenData = $response->json();

        $userResponse = Http::withToken($tokenData['access_token'])
            ->get(config('services.sso.url') . '/oauth/userinfo');

        if ($userResponse->failed()) {
            return redirect()->route('login')->withErrors(['sso' => 'Could not retrieve user.']);
        }

        $ssoUser = $userResponse->json();

        $user = User::firstOrCreate(
            ['email' => $ssoUser['email']],
            ['name' => $ssoUser['name'], 'password' => ''],
        );

        $mechanic = Mechanic::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();

        if ($mechanic === null) {
            return redirect()->route('login')->withErrors(['sso' => 'No garage association found for your account.']);
        }

        Auth::login($user);
        session(['current_garage_id' => $mechanic->garage_id]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): BaseResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Inertia::location() forces a full browser navigation; the Inertia <Link method="post">
        // XHR cannot follow the cross-origin redirect that route('login') triggers (→ SSO).
        // Landing on `/` (home) skips the OAuth flow so the SSO session does not auto-relogin.
        return Inertia::location(route('home'));
    }
}
