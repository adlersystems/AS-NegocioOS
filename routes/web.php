<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);

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
        ->middleware('role:admin,vendedor');
    Route::get('sales/export/excel', [SaleController::class, 'exportExcel'])->name('sales.export.excel')
        ->middleware('role:admin,vendedor');
    Route::get('sales/{sale}/pdf', [SaleController::class, 'exportPdf'])->name('sales.invoice.pdf')
        ->middleware('role:admin,vendedor');
    Route::resource('sales', SaleController::class)->middleware('role:admin,vendedor');

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

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index')
        ->middleware('role:admin,encargado');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index')
        ->middleware('role:admin');

    Route::get('clients/export/pdf', [ClientController::class, 'exportPdf'])->name('clients.export.pdf');
    Route::get('clients/export/excel', [ClientController::class, 'exportExcel'])->name('clients.export.excel');
    Route::resource('clients', ClientController::class);

    Route::resource('products', ProductController::class);
});
