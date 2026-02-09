<?php

use App\Http\Controllers\FingerprintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

 Route::prefix('fingerprint')->name('fingerprint.')->group(function () {
    Route::get('/', [FingerprintController::class, 'index'])->name('index');
    Route::get('/realtime', [FingerprintController::class, 'realtime'])->name('realtime');
    Route::get('/{id}', [FingerprintController::class, 'show'])->name('show');
    Route::get('/api/realtime-data', [FingerprintController::class, 'getRealtimeData']);
    Route::get('/api/server-status', [FingerprintController::class, 'serverStatus']);
    Route::post('/send-command', [FingerprintController::class, 'sendTestCommand']);
});