<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SmsLogController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('throttle:api-public')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:api-protected'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::post('/invoices/generate-monthly', [InvoiceController::class, 'generateMonthly']);
    Route::apiResource('invoices', InvoiceController::class);

    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('tenants', TenantController::class);
    Route::apiResource('leases', LeaseController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('maintenance', MaintenanceController::class);

    Route::get('/sms-logs', [SmsLogController::class, 'index']);
});
