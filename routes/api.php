<?php

use App\Http\Controllers\Api\V1\DevicePlayController;
use App\Http\Middleware\EnsureActiveDevice;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device API (v1)
|--------------------------------------------------------------------------
|
| Used by registered classroom devices with a Bearer device token, so a tablet
| can refresh its studies and sync answers after working offline.
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', EnsureActiveDevice::class])
    ->group(function (): void {
        Route::get('/play/config', [DevicePlayController::class, 'config'])->name('play.config');
        Route::get('/studies/{study}/bundle', [DevicePlayController::class, 'bundle'])->name('play.bundle');
        Route::post('/sync', [DevicePlayController::class, 'sync'])->middleware('throttle:120,1')->name('play.sync');
    });
