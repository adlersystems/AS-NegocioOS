<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.store');
});

Route::post('language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('sales/export/pdf', [SaleController::class, 'exportListPdf'])->name('sales.export.pdf')
        ->middleware('role:admin,vendedor,encargado');
    Route::get('sales/export/excel', [SaleController::class, 'exportExcel'])->name('sales.export.excel')
        ->middleware('role:admin,vendedor,encargado');
    Route::get('sales/{sale}/pdf', [SaleController::class, 'exportPdf'])->name('sales.invoice.pdf')
        ->middleware('role:admin,vendedor,encargado');
    Route::patch('sales/{sale}/paid', [SaleController::class, 'togglePaid'])->name('sales.paid.toggle')
        ->middleware('role:admin,encargado');

    Route::get('sales', [SaleController::class, 'index'])->name('sales.index')
        ->middleware('role:admin,vendedor,encargado');
    Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create')
        ->middleware('role:admin,vendedor,encargado');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store')
        ->middleware('role:admin,vendedor,encargado');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show')
        ->middleware('role:admin,vendedor,encargado');
    Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit')
        ->middleware('role:admin');
    Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update')
        ->middleware('role:admin');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy')
        ->middleware('role:admin');

    Route::controller(InventoryController::class)
        ->middleware('role:admin,encargado')
        ->name('inventory.')
        ->group(function () {
            Route::get('inventory', 'index')->name('index');
            Route::get('inventory/create', 'create')->name('create');
            Route::post('inventory', 'store')->name('store');
            Route::get('inventory/export/pdf', 'exportPdf')->name('export.pdf');
            Route::get('inventory/export/excel', 'exportExcel')->name('export.excel');
        });

    Route::controller(ReportController::class)
        ->middleware('role:admin,encargado')
        ->name('reports.')
        ->group(function () {
            Route::get('reports', 'index')->name('index');
            Route::get('reports/export/pdf', 'exportPdf')->name('export.pdf');
            Route::get('reports/export/excel', 'exportExcel')->name('export.excel');
        });

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index')
        ->middleware('role:admin');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update')
        ->middleware('role:admin');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index')
        ->middleware('role:admin');
    Route::get('audit/{log}', [AuditLogController::class, 'show'])->name('audit.show')
        ->middleware('role:admin');

    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');

    Route::get('clients/export/pdf', [ClientController::class, 'exportPdf'])->name('clients.export.pdf')
        ->middleware('role:admin,encargado');
    Route::get('clients/export/excel', [ClientController::class, 'exportExcel'])->name('clients.export.excel')
        ->middleware('role:admin,encargado');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit')
        ->middleware('role:admin,encargado');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update')
        ->middleware('role:admin,encargado');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')
        ->middleware('role:admin,encargado');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    Route::middleware('role:admin,encargado')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
    });

    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::middleware('role:admin,encargado')->group(function () {
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
});
