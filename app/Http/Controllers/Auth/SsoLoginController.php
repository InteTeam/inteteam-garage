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

final class SsoLoginController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $ssoUrl = config('services.sso.url');
        $clientId = config('services.sso.client_id');
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
            ->get(config('services.sso.url') . '/api/user');

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

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
