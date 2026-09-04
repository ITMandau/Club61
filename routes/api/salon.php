<?php

use App\Http\Controllers\Api\V1\Salon\SalonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/salon')->group(function () {
    Route::get('/services', [SalonController::class, 'services']);
});
