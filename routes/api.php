<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Club 61 Central REST API Routes (V1)
|--------------------------------------------------------------------------
|
| Modularized API architecture grouped by business domains.
| Anti-Bloat, highly scalable, consumed cleanly by Flutter Mobile & Web.
|
*/

// Health Check Endpoint for Flutter / Monitoring
Route::get('/v1/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Club 61 Central API Engine is running healthy.',
        'timestamp' => now()->toIso8601String(),
        'version' => '1.0.0',
    ]);
});

// Domain Modular Routes
require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/padel.php';
require __DIR__ . '/api/wellness.php';
require __DIR__ . '/api/salon.php';
require __DIR__ . '/api/gym.php';
require __DIR__ . '/api/fnb.php';
require __DIR__ . '/api/merch.php';
require __DIR__ . '/api/pos.php';
