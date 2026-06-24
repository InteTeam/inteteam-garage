<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CrmApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

final class CustomerSsoLoginController extends Controller
{
    public function __construct(private readonly CrmApiService $crm) {}

    public function redirect(): RedirectResponse|Response
    {
        $ssoUrl = config('services.sso.public_url');
        $clientId = config('services.sso.customer_client_id');

        if (empty($ssoUrl) || empty($clientId)) {
            return Inertia::render('Auth/CustomerSsoSetup', [
                'callbackUrl' => route('customer.callback'),
                // array_values re-indexes after array_filter — frontend + tests
                // expect a list, not a sparse assoc.
                'missing' => array_values(array_filter([
                    empty($ssoUrl) ? 'SSO_URL' : null,
                    empty($clientId) ? 'SSO_CUSTOMER_CLIENT_ID' : null,
                    empty(config('services.sso.customer_client_secret')) ? 'SSO_CUSTOMER_CLIENT_SECRET' : null,
                ])),
            ]);
        }

        $callbackUrl = route('customer.callback');

        return redirect("{$ssoUrl}/oauth/authorize?client_id={$clientId}&redirect_uri={$callbackUrl}&response_type=code&scope=");
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()->route('customer.login')->withErrors(['sso' => 'Authentication failed.']);
        }

        $response = Http::post(config('services.sso.url') . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.sso.customer_client_id'),
            'client_secret' => config('services.sso.customer_client_secret'),
            'redirect_uri' => route('customer.callback'),
            'code' => $code,
        ]);

        if ($response->failed()) {
            return redirect()->route('customer.login')->withErrors(['sso' => 'Authentication failed.']);
        }

        $tokenData = $response->json();

        $userResponse = Http::withToken($tokenData['access_token'])
            ->get(config('services.sso.url') . '/oauth/userinfo');

        if ($userResponse->failed()) {
            return redirect()->route('customer.login')->withErrors(['sso' => 'Could not retrieve user.']);
        }

        $ssoUser = $userResponse->json();
        $email = (string) ($ssoUser['email'] ?? '');
        $name = (string) ($ssoUser['name'] ?? '');

        if ($email === '') {
            return redirect()->route('customer.login')->withErrors(['sso' => 'SSO response missing email.']);
        }

        // Try to resolve the CRM customer record by email. If CRM doesn't know
        // this person yet, we still log them in — the dashboard will show a
        // "no CRM link yet" empty state instead of vehicles/jobs.
        $crmCustomer = $this->crm->findCustomerByEmail($email);

        $customer = Customer::firstOrNew(['email' => strtolower($email)]);
        $customer->name = $customer->name ?: ($name ?: $email);

        if ($crmCustomer !== null) {
            $crmId = $crmCustomer['id'] ?? null;
            if (is_string($crmId) && $crmId !== '') {
                $customer->crm_customer_id = $crmId;
            }
            $crmName = $crmCustomer['name'] ?? null;
            if (is_string($crmName) && $crmName !== '') {
                $customer->name = $crmName;
            }
        }

        $customer->last_login_at = now();
        $customer->save();

        Auth::guard('customer')->login($customer, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request): BaseResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Mirror SsoLoginController::logout — land on home, not on the SSO
        // login flow, so the SSO session does not auto-relogin the same user.
        return Inertia::location(route('home'));
    }
}
