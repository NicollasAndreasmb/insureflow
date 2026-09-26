<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\InsuranceProductController;
use App\Http\Controllers\Api\InsurerController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class)->name('api.health');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'me'])->name('user');

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('insurers', InsurerController::class);
    Route::apiResource('insurance-products', InsuranceProductController::class);
});
