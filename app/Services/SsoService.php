<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shared OAuth2 authorization-code plumbing for the two Inte.Team SSO clients
 * this app registers (mechanic dashboard + customer account portal, see
 * `inteteam_sso/config/apps.php` → `garage.roles`).
 *
 * Both controllers previously inlined their own copy of the authorize-URL build,
 * token exchange and userinfo fetch, and **neither sent or validated the OAuth
 * `state` parameter**. Without it the callback accepts any `?code=` an attacker
 * can get the browser to visit, which is login CSRF: the victim ends up
 * authenticated as the attacker's SSO identity (and, on the customer guard, the
 * attacker's CRM customer record). `state` is required by RFC 6749 §10.12 and by
 * the playbook's OAuth pattern ("validate state with timing-safe comparison").
 *
 * Two clients share one session, so state is namespaced per client key —
 * otherwise opening the mechanic and customer flows in two tabs would have the
 * second redirect clobber the first's state and break the first callback.
 */
final class SsoService
{
    private const STATE_SESSION_PREFIX = 'sso_state:';

    /**
     * Build the `/oauth/authorize` URL and stash a fresh state value under
     * `$clientKey` for `consumeState()` to check on the way back.
     *
     * `public_url` (browser-facing) is deliberate — `services.sso.url` is the
     * container-to-container host and is not resolvable from the user's browser.
     */
    public function authorizeUrl(string $clientKey, string $clientId, string $redirectUri): string
    {
        $state = Str::random(40);

        session([self::STATE_SESSION_PREFIX . $clientKey => $state]);

        $base = rtrim((string) config('services.sso.public_url'), '/');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]);

        return "{$base}/oauth/authorize?{$query}";
    }

    /**
     * Timing-safe, single-use check of the returned state. Always forgets the
     * stored value — a replayed callback must fail even if it carries the
     * correct state.
     */
    public function consumeState(string $clientKey, mixed $provided): bool
    {
        $key = self::STATE_SESSION_PREFIX . $clientKey;
        $expected = session()->pull($key);

        if (! is_string($expected) || $expected === '') {
            Log::warning('SSO callback carried no stored state', ['client' => $clientKey]);

            return false;
        }

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('SSO callback state mismatch', ['client' => $clientKey]);

            return false;
        }

        return true;
    }

    /**
     * Exchange the authorization code for tokens. Returns null on any failure —
     * callers surface a generic "Authentication failed." rather than leaking the
     * grant error to the user.
     *
     * `asForm()` is required: Passport's PSR-7 request returns null from
     * `getParsedBody()` for a JSON body, so every grant misses `grant_type` and
     * the server answers `unsupported_grant_type`. Laravel's `Http::post()`
     * defaults to JSON.
     *
     * @return array<string, mixed>|null
     */
    public function exchangeCode(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $code,
    ): ?array {
        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post($this->internalBaseUrl() . '/oauth/token', [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SSO token exchange threw', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('SSO token exchange failed', ['status' => $response->status()]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            Log::warning('SSO token response missing access_token');

            return null;
        }

        return $data;
    }

    /**
     * Fetch the user claims. Shape per `inteteam_sso`'s `UserInfoController` +
     * `TokenClaimsService`: always `sub`, `email`, `name`; plus `company_id`,
     * `company_name`, `subscriptions`, `role`, `token_validation` when the token
     * carries a company context.
     *
     * @return array<string, mixed>|null
     */
    public function fetchUserInfo(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->acceptJson()
                ->get($this->internalBaseUrl() . '/oauth/userinfo');
        } catch (\Throwable $e) {
            Log::warning('SSO userinfo threw', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('SSO userinfo failed', ['status' => $response->status()]);

            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /** Browser-facing logout URL that also clears the SSO-side session. */
    public function logoutUrl(string $returnTo): string
    {
        $base = rtrim((string) config('services.sso.public_url'), '/');

        return "{$base}/logout?redirect_to=" . urlencode($returnTo);
    }

    private function internalBaseUrl(): string
    {
        return rtrim((string) config('services.sso.url'), '/');
    }
}
