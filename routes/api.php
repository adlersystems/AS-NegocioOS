<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->name('api.auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('api.me');

    Route::get('dashboard', DashboardController::class)->name('api.dashboard');

    Route::get('clients', [ClientController::class, 'index'])->name('api.clients.index');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('api.clients.show');

    Route::get('products', [ProductController::class, 'index'])->name('api.products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('api.products.show');

    Route::get('sales', [SaleController::class, 'index'])->name('api.sales.index');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('api.sales.show');

    Route::get('inventory', [InventoryController::class, 'index'])->name('api.inventory.index')
        ->middleware('role:admin,encargado');

    Route::get('reports', [ReportController::class, 'index'])->name('api.reports.index')
        ->middleware('role:admin,encargado');

    Route::get('settings', [SettingController::class, 'index'])->name('api.settings.index')
        ->middleware('role:admin');
});
