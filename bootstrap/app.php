<?php

use App\Http\Middleware\AuthenticateGarageApiKey;
use App\Http\Middleware\CheckGarageRole;
use App\Http\Middleware\EnsureGarageContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ValidatePortalToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/portal.php'));

            Route::middleware('web')
                ->group(base_path('routes/customer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Unauth requests to /account/* must land on customer SSO, not mechanic
        // SSO. Without this the default route('login') sends them through the
        // mechanic callback, which rejects non-mechanics and ping-pongs back.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('account', 'account/*')
            ? route('customer.login')
            : route('login'));

        $middleware->alias([
            'garage' => EnsureGarageContext::class,
            'role' => CheckGarageRole::class,
            'portal.token' => ValidatePortalToken::class,
            'api.garage' => AuthenticateGarageApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
