<?php

use App\Http\Controllers\Api\V1\Merch\MerchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/merch')->group(function () {
    Route::get('/products', [MerchController::class, 'index']);
});
