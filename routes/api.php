<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryReportController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Authentication ───────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api-auth');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api-auth');
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // ── Protected Routes ─────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        // Dashboard & Reports
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('reports/inventory/export', [InventoryReportController::class, 'export'])->name('reports.inventory.export');
        Route::get('products/export', [InventoryReportController::class, 'export'])->name('products.export');

        // Categories CRUD & Restore
        Route::post('categories/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
        Route::apiResource('categories', CategoryController::class);

        // Products CRUD, Restore & Stock Movements
        Route::get('products/{id}/movements', [ProductController::class, 'movements'])->name('products.movements');
        Route::post('products/{id}/restore', [ProductController::class, 'restore'])->name('products.restore');
        Route::apiResource('products', ProductController::class);
    });
});
