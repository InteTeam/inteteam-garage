<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Mechanic;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGarageContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        /** @var User $user */
        /** @var Mechanic|null $mechanic */
        $mechanic = $user->mechanic;

        if ($mechanic === null) {
            abort(403, 'No garage association found for this user.');
        }

        if (session('current_garage_id') === null) {
            session(['current_garage_id' => $mechanic->garage_id]);
        }

        return $next($request);
    }
}
