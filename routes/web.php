<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ConsoleManagementController;
use App\Http\Controllers\Admin\PricingPackageController;
use App\Http\Controllers\Admin\ProductInventoryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShiftAndUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RentalSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Route Kasir (Operasional POS)
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/sessions/start', [RentalSessionController::class, 'start'])->name('sessions.start');
Route::post('/sessions/{rentalSession}/extend', [RentalSessionController::class, 'extend'])->name('sessions.extend');
Route::post('/sessions/{rentalSession}/move', [RentalSessionController::class, 'move'])->name('sessions.move');
Route::post('/sessions/{rentalSession}/stop', [RentalSessionController::class, 'stop'])->name('sessions.stop');

// Route Bagian Admin
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['role:admin'])
    ->group(function () {
        // 1. Dashboard & Ringkasan Eksekutif
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // 2. Master Konsol & Tarif
        Route::get('/consoles', [ConsoleManagementController::class, 'index'])->name('consoles.index');
        Route::post('/consoles/categories', [ConsoleManagementController::class, 'storeCategory'])->name('consoles.categories.store');
        Route::put('/consoles/categories/{category}', [ConsoleManagementController::class, 'updateCategory'])->name('consoles.categories.update');
        Route::post('/consoles/units', [ConsoleManagementController::class, 'storeConsole'])->name('consoles.units.store');
        Route::patch('/consoles/units/{console}/toggle-maintenance', [ConsoleManagementController::class, 'toggleMaintenance'])->name('consoles.units.toggle');

        // 3. Paket Harga Main
        Route::get('/packages', [PricingPackageController::class, 'index'])->name('packages.index');
        Route::post('/packages', [PricingPackageController::class, 'store'])->name('packages.store');
        Route::patch('/packages/{package}/toggle', [PricingPackageController::class, 'toggleActive'])->name('packages.toggle');
        Route::delete('/packages/{package}', [PricingPackageController::class, 'destroy'])->name('packages.destroy');

        // 4. Inventori & Menu F&B
        Route::get('/products', [ProductInventoryController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductInventoryController::class, 'store'])->name('products.store');
        Route::post('/products/{product}/restock', [ProductInventoryController::class, 'restock'])->name('products.restock');

        // 5. Akun Kasir & Rekonsiliasi Shift
        Route::get('/shifts', [ShiftAndUserController::class, 'index'])->name('shifts.index');
        Route::post('/shifts/users', [ShiftAndUserController::class, 'storeUser'])->name('shifts.users.store');
        Route::patch('/shifts/users/{user}/toggle', [ShiftAndUserController::class, 'toggleUserActive'])->name('shifts.users.toggle');

        // 6. Laporan Keuangan & Audit Void
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export-csv', [ReportController::class, 'exportCsv'])->name('reports.export');
        Route::get('/api/consoles/live-status', [DashboardController::class, 'liveStatus'])->name('api.consoles.live');
    });
