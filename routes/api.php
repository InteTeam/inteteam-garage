<?php

declare(strict_types=1);

use App\Http\Controllers\Api\JobApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('api.garage')->group(function () {
        Route::get('/jobs/{job}', [JobApiController::class, 'show'])->name('jobs.show');
        Route::get('/jobs/{job}/media', [JobApiController::class, 'media'])->name('jobs.media');
    });
});
