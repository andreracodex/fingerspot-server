<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FingerspotController;

// Public test endpoint
Route::get('/fingerspot/test', [FingerspotController::class, 'test']);

// Webhook endpoint (no authentication for now - add later)
Route::post('/fingerspot/webhook', [FingerspotController::class, 'webhook']);

// Logs endpoint (add auth later)
Route::get('/fingerspot/logs', [FingerspotController::class, 'logs']);