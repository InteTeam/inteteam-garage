<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Shared-secret check for the two unauthenticated server-to-server surfaces:
 * the CRM payment webhook (`X-Internal-Secret`) and the internal job API
 * (`X-Garage-Secret`).
 *
 * Both previously did `$request->header($name) !== config($key)`. That has two
 * defects:
 *
 *  1. **Open door when unconfigured.** With `GARAGE_INTERNAL_SECRET` unset,
 *     `config()` returns `null` and a request that sends no header also yields
 *     `null` — `null !== null` is `false`, so the check passes and the caller is
 *     treated as authenticated. A fresh deploy that forgets the env var exposes
 *     the webhook and the job API to the internet. We now fail closed.
 *  2. **Non-constant-time compare.** `!==` on strings short-circuits at the
 *     first differing byte, which leaks the secret's prefix to a timing oracle.
 *     `hash_equals` does not.
 *
 * Mirrors `inteteam_crm`'s `VerifyInternalToken` (500 when unconfigured, 401 on
 * mismatch) so both sides of the integration behave the same way under
 * misconfiguration.
 */
final class InternalSecretGuard
{
    /**
     * Aborts the request unless `$provided` matches the configured secret.
     *
     * @param  string  $context  Surface name, for the misconfiguration log line
     */
    public static function assert(mixed $configured, mixed $provided, string $context): void
    {
        if (! is_string($configured) || $configured === '') {
            Log::error('Internal secret is not configured — refusing the request', [
                'context' => $context,
            ]);

            abort(500, 'Internal secret is not configured.');
        }

        if (! is_string($provided) || $provided === '' || ! hash_equals($configured, $provided)) {
            abort(401, 'Unauthorized.');
        }
    }
}
