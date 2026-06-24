<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\CustomerSsoLoginController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\JobController;
use App\Http\Controllers\Customer\LineItemController;
use App\Http\Controllers\Customer\TransactionController;
use App\Http\Controllers\Customer\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Portal Routes (SSO-authenticated)
|--------------------------------------------------------------------------
| Coexists with routes/portal.php (signed-token, one-off-per-job). This
| account portal aggregates everything a logged-in customer can see across
| garages: vehicles, repair jobs (read-only), line-item actions, payment
| history.
*/
Route::prefix('account')->name('customer.')->group(function () {
    Route::get('/login', [CustomerSsoLoginController::class, 'redirect'])->name('login');
    Route::get('/callback', [CustomerSsoLoginController::class, 'callback'])->name('callback');
    Route::post('/logout', [CustomerSsoLoginController::class, 'logout'])->name('logout');

    Route::middleware('auth:customer')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');

        Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::post('/jobs/{job}/line-items/{lineItem}/approve', [LineItemController::class, 'approve'])->name('line-items.approve');
        Route::post('/jobs/{job}/line-items/{lineItem}/decline', [LineItemController::class, 'decline'])->name('line-items.decline');
        Route::post('/jobs/{job}/line-items/{lineItem}/question', [LineItemController::class, 'question'])->name('line-items.question');

        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    });
});
