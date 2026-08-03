<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\InternalSecretGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateGarageApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        InternalSecretGuard::assert(
            configured: config('services.garage.internal_secret'),
            provided: $request->header('X-Garage-Secret'),
            context: 'internal job API',
        );

        return $next($request);
    }
}
