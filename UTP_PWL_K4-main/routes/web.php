<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FnbOrderController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/fnb/order', [FnbOrderController::class, 'store'])->name('fnb.order');
Route::get('/rental-session/{id}/billing', [CheckoutController::class, 'getBillingData'])->name('rental.billing');
Route::post('/rental-session/{id}/checkout', [CheckoutController::class, 'process'])->name('rental.checkout');