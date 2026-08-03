<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Mechanic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered as the `role` alias in bootstrap/app.php but **not currently applied
 * to any route** — role checks in this app all go through policies
 * (`Mechanic::isAdmin()` via `#[UsePolicy]`), which is the playbook pattern.
 *
 * Kept rather than deleted because it is the correct tool for a route group that
 * should be admin-only *before* a controller resolves. If you reach for it, note
 * that `$request->user()?->mechanic` is garage-scoped, so this must run after the
 * `garage` middleware has set `current_garage_id` — see the `EnsureGarageContext`
 * gotcha in docs/app-map.md.
 */
final class CheckGarageRole
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
