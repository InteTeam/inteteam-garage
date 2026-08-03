<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Mechanic;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class EnsureGarageContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        /** @var User $user */
        // `withoutGlobalScopes()` is load-bearing: `Mechanic` uses `HasGarageScope`,
        // so `$user->mechanic` is filtered by the *session* garage. Resolving the
        // mechanic through the scope makes the lookup depend on the value it is
        // supposed to produce — if the session ever holds a different garage's id,
        // the relation returns null and this middleware aborts 403 on every route.
        // A mechanic moved between garages was locked out of the whole dashboard
        // with an opaque "No garage association found" until their session expired.
        // Same reason `SsoLoginController::callback` bypasses the scope.
        /** @var Mechanic|null $mechanic */
        $mechanic = Mechanic::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();

        if ($mechanic === null) {
            abort(403, 'No garage association found for this user.');
        }

        // The session value is authoritative for `HasGarageScope`, so it must never
        // outlive the mechanic record it was derived from. Re-assert it on every
        // request rather than only when absent; it is a session write only when the
        // value actually diverges.
        $current = session('current_garage_id');

        if ($current !== $mechanic->garage_id) {
            if (is_string($current) && $current !== '') {
                Log::warning('Stale current_garage_id replaced', [
                    'user_id' => $user->id,
                    'mechanic_id' => $mechanic->id,
                    'from' => $current,
                    'to' => $mechanic->garage_id,
                ]);
            }

            session(['current_garage_id' => $mechanic->garage_id]);
        }

        return $next($request);
    }
}
