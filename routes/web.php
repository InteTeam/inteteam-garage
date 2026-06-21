<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoLoginController;
use App\Http\Controllers\ComplianceRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\EstimateLifecycleController;
use App\Http\Controllers\GarageSettingsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobMechanicController;
use App\Http\Controllers\JobNotificationPreferenceController;
use App\Http\Controllers\JobStageController;
use App\Http\Controllers\LineItemController;
use App\Http\Controllers\LineItemResponseController;
use App\Http\Controllers\MechanicController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PortalLinkController;
use App\Http\Controllers\ScopeChangeController;
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
| Public Landing
|--------------------------------------------------------------------------
| Guests see the marketing landing; authenticated mechanics are forwarded
| to /dashboard so the `garage` middleware (EnsureGarageContext) runs.
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Authenticated Mechanic/Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'garage'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('vehicles', VehicleController::class);
    Route::post('/vehicles/{vehicle}/compliance', [ComplianceRecordController::class, 'store'])
        ->name('vehicles.compliance.store');
    Route::post('/vehicles/{vehicle}/compliance/refresh', [ComplianceRecordController::class, 'refresh'])
        ->name('vehicles.compliance.refresh');
    Route::resource('mechanics', MechanicController::class);

    Route::prefix('jobs')->name('jobs.')->group(function () {
        // JobController implements only index/create/store/show — edit/update/destroy not wired (no UI link).
        Route::resource('/', JobController::class)
            ->only(['index', 'create', 'store', 'show'])
            ->parameters(['' => 'job']);
        Route::post('/{job}/transition', [JobController::class, 'transition'])->name('transition');
        // Stages have no create/edit screens — managed inline on RepairJobs/Show.tsx.
        Route::resource('/{job}/stages', JobStageController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy'])
            ->parameters(['stages' => 'stage']);
        Route::patch('/{job}/stages/{stage}/notes', [JobStageController::class, 'updateNotes'])->name('stages.notes.update');
        Route::post('/{job}/stages/{stage}/media', [MediaController::class, 'store'])->name('stages.media.store');
        // Estimates use revision-create-on-update flow — no separate create/edit screens.
        Route::resource('/{job}/estimates', EstimateController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy'])
            ->parameters(['estimates' => 'estimate']);
        Route::post('/{job}/estimates/{estimate}/send', [EstimateLifecycleController::class, 'send'])->name('estimates.send');
        Route::post('/{job}/estimates/{estimate}/preview-translation', [EstimateLifecycleController::class, 'previewTranslation'])->name('estimates.preview-translation');
        Route::post('/{job}/estimates/{estimate}/confirm-translation', [EstimateLifecycleController::class, 'confirmTranslation'])->name('estimates.confirm-translation');
        Route::post('/{job}/estimates/{estimate}/line-items', [LineItemController::class, 'store'])->name('estimates.line-items.store');
        Route::post('/{job}/mechanics/assign', [JobMechanicController::class, 'sync'])->name('mechanics.sync');
        Route::put('/{job}/notification-preference', [JobNotificationPreferenceController::class, 'update'])->name('notification-preference.update');
        Route::post('/{job}/scope-change', [ScopeChangeController::class, 'store'])->name('scope-change.store');
        Route::post('/{job}/line-items/{lineItem}/respond', [LineItemResponseController::class, 'store'])->name('line-items.respond');
        Route::post('/{job}/line-items/{lineItem}/preview-response', [LineItemResponseController::class, 'preview'])->name('line-items.preview-response');
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
