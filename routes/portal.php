<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\PortalHandoverController;
use App\Http\Controllers\Portal\PortalJobController;
use App\Http\Controllers\Portal\PortalLineItemController;
use App\Http\Controllers\Portal\PortalPaymentController;
use App\Http\Controllers\Portal\PortalPreferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Portal Routes (signed token, no SSO)
|--------------------------------------------------------------------------
*/
Route::prefix('portal/{token}')->middleware('portal.token')->name('portal.')->group(function () {
    Route::get('/', [PortalJobController::class, 'show'])->name('show');
    Route::get('/timeline', [PortalJobController::class, 'timeline'])->name('timeline');
    Route::get('/handover', [PortalHandoverController::class, 'show'])->name('handover.show');
    Route::post('/handover', [PortalHandoverController::class, 'submit'])->name('handover.submit');

    Route::prefix('line-items/{lineItem}')->name('line-items.')->group(function () {
        Route::post('/approve', [PortalLineItemController::class, 'approve'])->name('approve');
        Route::post('/decline', [PortalLineItemController::class, 'decline'])->name('decline');
        Route::post('/question', [PortalLineItemController::class, 'question'])->name('question');
    });

    Route::post('/notification-preference', [PortalPreferenceController::class, 'update'])->name('preference.update');
    Route::get('/payment', [PortalPaymentController::class, 'show'])->name('payment.show');
    Route::post('/payment/request', [PortalPaymentController::class, 'request'])->name('payment.request');
});
