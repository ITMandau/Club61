<?php

use App\Http\Controllers\Api\V1\Gym\GymController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/gym')->group(function () {
    Route::get('/packages', [GymController::class, 'packages']);
});
