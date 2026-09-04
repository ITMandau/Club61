<?php

use App\Http\Controllers\Api\V1\Fnb\FnbController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/fnb')->group(function () {
    Route::get('/menu', [FnbController::class, 'menu']);
});
