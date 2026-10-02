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
            Route::patch('{warehouseId}', 'update')->name('warehouses-update')->where('warehouseId', '[0-9]+');
            Route::get('{warehouseId}', 'show')->name('warehouses-show')->where('warehouseId', '[0-9]+');
            Route::get('', 'index')->name('warehouses-index');
        });
    });
});
