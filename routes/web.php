<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RentalSessionController;

Route::post('/sessions/start', [RentalSessionController::class, 'start'])->name('sessions.start');
Route::post('/sessions/{rentalSession}/extend', [RentalSessionController::class, 'extend'])->name('sessions.extend');
Route::post('/sessions/{rentalSession}/move', [RentalSessionController::class, 'move'])->name('sessions.move');
Route::post('/sessions/{rentalSession}/stop', [RentalSessionController::class, 'stop'])->name('sessions.stop');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');