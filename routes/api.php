<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Phase 1
|--------------------------------------------------------------------------
|
| Pattern: routes that need a tenant context use the {tenant} route param,
| which the ResolveTenant middleware reads. The same routes also accept
| X-Tenant-Id header for the SPA case (set when the user switches workspace).
|
*/

// --- Public ---
Route::post('/auth/signup', [AuthController::class, 'signup'])->middleware('throttle:auth');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

// TODO Phase 1.1:
//   GET   /auth/verify-email/{token}
//   POST  /auth/forgot-password
//   POST  /auth/reset-password
//   GET   /auth/oauth/google
//   GET   /auth/oauth/google/callback

// --- Authenticated, no tenant required ---
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/me/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store'])->middleware('throttle:tenant-create');
    Route::post('/invites/accept', [TenantController::class, 'acceptInvite']);
});

// --- Authenticated + tenant required ---
Route::middleware(['auth:api', 'tenant'])->prefix('tenants/{tenant}')->group(function () {
    // Admin-only invite management.
    Route::middleware('tenant.role:admin')->group(function () {
        Route::get('/invites', [InviteController::class, 'index']);
        Route::post('/invites', [InviteController::class, 'store'])->middleware('throttle:invites');
        Route::delete('/invites/{invite}', [InviteController::class, 'destroy']);
    });

    // Phase 2+ resource routes (findings, scans, etc.) will go here.
});

// --- Health check (for uptime monitoring + load balancer) ---
Route::get('/health', fn () => response()->json([
    'ok' => true,
    'time' => now()->toIso8601String(),
    'version' => config('app.version', 'phase-1'),
]));
