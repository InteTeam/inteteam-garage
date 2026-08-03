<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CrmApiService;
use App\Services\SsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

final class CustomerSsoLoginController extends Controller
{
    /** Namespaces the OAuth `state` so the two SSO clients don't clobber each other. */
    private const CLIENT_KEY = 'customer';

    public function __construct(
        private readonly CrmApiService $crm,
        private readonly SsoService $sso,
    ) {}

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

        return redirect($this->sso->authorizeUrl(
            clientKey: self::CLIENT_KEY,
            clientId: (string) $clientId,
            redirectUri: route('customer.callback'),
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
            clientId: (string) config('services.sso.customer_client_id'),
            clientSecret: (string) config('services.sso.customer_client_secret'),
            redirectUri: route('customer.callback'),
            code: $code,
        );

        if ($tokenData === null) {
            return $this->fail('Authentication failed.');
        }

        $accessToken = (string) $tokenData['access_token'];
        $ssoUser = $this->sso->fetchUserInfo($accessToken);

        if ($ssoUser === null) {
            return $this->fail('Could not retrieve user.');
        }

        $email = (string) ($ssoUser['email'] ?? '');
        $name = (string) ($ssoUser['name'] ?? '');

        if ($email === '') {
            return $this->fail('SSO response missing email.');
        }

        $customer = Customer::firstOrNew(['email' => strtolower($email)]);
        $customer->name = $customer->name ?: ($name !== '' ? $name : $email);

        // Bind the local row to the CRM's customer record by handing the SSO
        // access token to the CRM's own exchange endpoint. This replaces a lookup
        // against `/api/v1/internal/customers?email=`, a route the CRM does not
        // have, whose response was read for a `data.id` the CRM never returns —
        // so `crm_customer_id` stayed null forever and `Customer::isLinkedToCrm()`
        // was false for every user, which short-circuits every `/account` detail
        // endpoint to 404. The exchange endpoint is the one store_front uses, it
        // is the only CRM route that returns a customer id, and it also creates
        // the CRM customer (back-linking their bookings/orders by email) on first
        // sign-in — so a first-time garage customer gets a real CRM record rather
        // than a permanent "not linked" banner.
        //
        // A CRM miss is deliberately non-fatal: the customer still logs in and
        // sees the unlinked-state banner.
        $crmCustomer = $this->crm->exchangeCustomerSsoToken($accessToken);

        if ($crmCustomer !== null) {
            $customer->crm_customer_id = $crmCustomer['id'];

            if ($crmCustomer['name'] !== null && $crmCustomer['name'] !== '') {
                $customer->name = $crmCustomer['name'];
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

        // Clear SSO session too — otherwise clicking any login button right
        // after sign-out auto-passes the same user back in.
        return Inertia::location($this->sso->logoutUrl(route('home')));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('home')->withErrors(['sso' => $message]);
    }
}
