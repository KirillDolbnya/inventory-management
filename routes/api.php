<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('sign-in', 'signIn')->name('auth-sign-in');
        Route::post('logout', 'logout')->name('auth-logout')->middleware('auth:sanctum');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('warehouses')->group(function () {
        Route::controller(WarehouseController::class)->group(function () {
            Route::post('', 'store')->name('warehouses-create');
        });
    });
});
