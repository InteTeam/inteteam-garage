<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGarageApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Garage-Secret');

        if ($secret !== config('services.garage.internal_secret')) {
            abort(401);
        }

        return $next($request);
    }
}
