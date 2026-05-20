<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\GarageSettingsController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobMechanicController;
use App\Http\Controllers\JobStageController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PortalLinkController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes (SSO)
|--------------------------------------------------------------------------
*/
Route::get('/login', [SsoLoginController::class, 'redirect'])->name('login');
Route::get('/auth/callback', [SsoLoginController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [SsoLoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Mechanic/Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'garage'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('vehicles', VehicleController::class);
    Route::resource('mechanics', MechanicController::class);

    Route::prefix('jobs')->name('jobs.')->group(function () {
        Route::resource('/', JobController::class)->parameters(['' => 'job']);
        Route::post('/{job}/transition', [JobController::class, 'transition'])->name('transition');
        Route::resource('/{job}/stages', JobStageController::class)->parameters(['stages' => 'stage']);
        Route::post('/{job}/stages/{stage}/media', [MediaController::class, 'store'])->name('stages.media.store');
        Route::resource('/{job}/estimates', EstimateController::class)->parameters(['estimates' => 'estimate']);
        Route::post('/{job}/estimates/{estimate}/send', [EstimateController::class, 'send'])->name('estimates.send');
        Route::post('/{job}/estimates/{estimate}/preview-translation', [EstimateController::class, 'previewTranslation'])->name('estimates.preview-translation');
        Route::post('/{job}/mechanics/assign', [JobMechanicController::class, 'sync'])->name('mechanics.sync');
        Route::get('/{job}/portal-link', [PortalLinkController::class, 'show'])->name('portal-link');
        Route::post('/{job}/portal-link/regenerate', [PortalLinkController::class, 'regenerate'])->name('portal-link.regenerate');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [GarageSettingsController::class, 'index'])->name('index');
        Route::put('/', [GarageSettingsController::class, 'update'])->name('update');
    });
});

/*
|--------------------------------------------------------------------------
| CRM Webhooks (no auth — verified by signature)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/payment-confirmed', [PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payment-confirmed');
