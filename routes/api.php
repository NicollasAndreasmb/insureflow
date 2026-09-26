<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\ClaimStatusController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CoverageController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\InsuranceProductController;
use App\Http\Controllers\Api\InsurerController;
use App\Http\Controllers\Api\OptionsController;
use App\Http\Controllers\Api\PolicyController;
use App\Http\Controllers\Api\PolicyStatusController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Identificadores são bigint no PostgreSQL: valores não numéricos ou maiores
 * que o tipo suporta chegariam ao banco e causariam erro 500. Com o padrão,
 * a rota simplesmente não casa e a resposta é 404.
 */
Route::patterns(array_fill_keys(
    ['client', 'insurer', 'insurance_product', 'policy', 'coverage', 'installment', 'claim', 'user'],
    '[0-9]{1,18}',
));

Route::get('/health', HealthCheckController::class)->name('api.health');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'me'])->name('user');

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('insurers', InsurerController::class);
    Route::apiResource('insurance-products', InsuranceProductController::class);

    Route::apiResource('policies', PolicyController::class);
    Route::patch('/policies/{policy}/status', PolicyStatusController::class)->name('policies.status');
    Route::apiResource('policies.coverages', CoverageController::class)->shallow();

    Route::get('/installments', [InstallmentController::class, 'index'])->name('installments.index');
    Route::get('/installments/{installment}', [InstallmentController::class, 'show'])->name('installments.show');
    Route::post('/installments/{installment}/payment', [InstallmentController::class, 'pay'])->name('installments.pay');

    Route::apiResource('claims', ClaimController::class);
    Route::patch('/claims/{claim}/status', ClaimStatusController::class)->name('claims.status');

    Route::apiResource('users', UserController::class);
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/options', OptionsController::class)->name('options');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
