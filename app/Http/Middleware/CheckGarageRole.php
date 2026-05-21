<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Mechanic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGarageRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var Mechanic|null $mechanic */
        $mechanic = $request->user()?->mechanic;

        if ($mechanic === null || ! in_array($mechanic->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
