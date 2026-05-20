<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SignedPortalToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePortalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenValue = $request->route('token');

        $token = SignedPortalToken::with('job')
            ->where('token', $tokenValue)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($token === null) {
            abort(404);
        }

        $request->attributes->set('portal_token', $token);
        $request->attributes->set('portal_job', $token->job);

        return $next($request);
    }
}
