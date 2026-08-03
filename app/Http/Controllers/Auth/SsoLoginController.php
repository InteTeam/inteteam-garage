<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use App\Services\SsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

final class SsoLoginController extends Controller
{
    /** Namespaces the OAuth `state` so the two SSO clients don't clobber each other. */
    private const CLIENT_KEY = 'mechanic';

    public function __construct(private readonly SsoService $sso) {}

    public function redirect(): RedirectResponse|Response
    {
        $ssoUrl = config('services.sso.public_url');
        $clientId = config('services.sso.client_id');

        if (empty($ssoUrl) || empty($clientId)) {
            return Inertia::render('Auth/SsoSetup', [
                'callbackUrl' => route('auth.callback'),
                // array_values re-indexes after array_filter — the frontend and
                // the tests expect a list, not a sparse assoc.
                'missing' => array_values(array_filter([
                    empty($ssoUrl) ? 'SSO_URL' : null,
                    empty($clientId) ? 'SSO_CLIENT_ID' : null,
                    empty(config('services.sso.client_secret')) ? 'SSO_CLIENT_SECRET' : null,
                ])),
            ]);
        }

        return redirect($this->sso->authorizeUrl(
            clientKey: self::CLIENT_KEY,
            clientId: (string) $clientId,
            redirectUri: route('auth.callback'),
        ));
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->fail('Authentication failed.');
        }

        if (! $this->sso->consumeState(self::CLIENT_KEY, $request->query('state'))) {
            return $this->fail('The sign-in request expired or was tampered with. Please try again.');
        }

        $tokenData = $this->sso->exchangeCode(
            clientId: (string) config('services.sso.client_id'),
            clientSecret: (string) config('services.sso.client_secret'),
            redirectUri: route('auth.callback'),
            code: $code,
        );

        if ($tokenData === null) {
            return $this->fail('Authentication failed.');
        }

        $ssoUser = $this->sso->fetchUserInfo((string) $tokenData['access_token']);

        if ($ssoUser === null || ! is_string($ssoUser['email'] ?? null) || $ssoUser['email'] === '') {
            return $this->fail('Could not retrieve user.');
        }

        $user = User::firstOrCreate(
            ['email' => $ssoUser['email']],
            ['name' => (string) ($ssoUser['name'] ?? $ssoUser['email']), 'password' => ''],
        );

        $this->syncCrmIdentity($user, $ssoUser);

        $mechanic = Mechanic::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();

        if ($mechanic === null) {
            return $this->fail('No garage association found for your account.');
        }

        $this->bindGarageToCrmCompany($mechanic, $ssoUser);

        Auth::login($user);
        session(['current_garage_id' => $mechanic->garage_id]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): BaseResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear the SSO session too — otherwise the next login attempt (any role)
        // gets auto-passed back as the same user. SSO's logout?redirect_to bounces
        // the user back here once it has invalidated its own session.
        return Inertia::location($this->sso->logoutUrl(route('home')));
    }

    /**
     * Persist the SSO subject as `crm_user_id`. This was never written, so
     * `CrmStaffNotificationService::dispatch()` short-circuited on every single
     * staff alert (handover-flagged, payment-confirmed, timeout reminder) with
     * "mechanic has no crm_user_id mapping" — the feature was inert in production.
     * SSO `sub` is the shared user identity across the ecosystem, which is exactly
     * what the CRM's `recipient_id` expects for `recipient_type=staff`.
     *
     * @param  array<string, mixed>  $ssoUser
     */
    private function syncCrmIdentity(User $user, array $ssoUser): void
    {
        $sub = $ssoUser['sub'] ?? null;

        if (! is_string($sub) || $sub === '' || $user->crm_user_id === $sub) {
            return;
        }

        $user->forceFill(['crm_user_id' => $sub])->save();
    }

    /**
     * Learn the garage's CRM company from the SSO token claims.
     *
     * `TokenClaimsService` emits `company_id` (and `company_name`) whenever the
     * access token carries a company context — the same company row the CRM is
     * tenanted on. Capturing it here means an operator never has to hand-copy a
     * ULID into `.env`: the first admin login wires the garage up. Only ever
     * *fills* the column; an explicit value set in Settings is not overwritten,
     * because a mechanic whose SSO account belongs to several companies could
     * otherwise silently repoint the whole garage's CRM traffic.
     *
     * @param  array<string, mixed>  $ssoUser
     */
    private function bindGarageToCrmCompany(Mechanic $mechanic, array $ssoUser): void
    {
        $companyId = $ssoUser['company_id'] ?? null;

        if (! is_string($companyId) || $companyId === '') {
            return;
        }

        $garage = Garage::withoutGlobalScopes()->find($mechanic->garage_id);

        if ($garage === null || ($garage->crm_company_id !== null && $garage->crm_company_id !== '')) {
            return;
        }

        $garage->forceFill(['crm_company_id' => $companyId])->save();

        Log::info('Garage bound to CRM company from SSO claims', [
            'garage_id' => $garage->id,
            'crm_company_id' => $companyId,
        ]);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('home')->withErrors(['sso' => $message]);
    }
}
