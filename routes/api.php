<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\RackController;
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
            Route::delete('{warehouseId}', 'delete')->name('warehouses-delete')->where('warehouseId', '[0-9]+');
        });

        Route::controller(RackController::class)->group(function () {
            Route::post('{warehouseId}/racks', 'store')->name('racks-create')->where('warehouseId', '[0-9]+');
            Route::get('{warehouseId}/racks', 'index')->name('racks-index')->where('warehouseId', '[0-9]+');
        });
    });

    Route::prefix('inventories')->group(function () {
        Route::controller(InventoryController::class)->group(function () {
            Route::post('', 'store')->name('inventory-create');
            Route::patch('{inventoryId}', 'update')->name('inventory-update')->where('inventoryId', '[0-9]+');
            Route::get('{inventoryId}', 'show')->name('inventory-show')->where('inventoryId', '[0-9]+');
        });
    });
});
