<?php

use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// OTLP ingestion endpoints
Route::post('/v1/metrics', [IngestController::class, 'metrics']);
Route::post('/v1/logs', [IngestController::class, 'logs']);
Route::post('/v1/traces', [IngestController::class, 'traces']);

// Leaderboard API
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/leaderboard/me', [LeaderboardController::class, 'me'])->middleware('auth:sanctum');

// Device flow for CLI setup
Route::post('/auth/device', [SetupController::class, 'deviceStart']);
Route::post('/auth/device/token', [SetupController::class, 'deviceToken']);
