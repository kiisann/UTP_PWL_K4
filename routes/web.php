
<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FnbOrderController;
use App\Models\Console;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

// Daftar produk F&B yang aktif
Route::get('/fnb-products', function () {
    return response()->json(
        Product::active()
            ->orderBy('name')
            ->get(['id', 'name', 'sell_price', 'stock'])
    );
})->name('fnb-products.index');

// Mengambil sesi rental aktif berdasarkan ID konsol
Route::get('/console-active-session/{consoleId}', function ($consoleId) {
    $console = Console::with('activeSession')->findOrFail($consoleId);
    $session = $console->activeSession;

    return response()->json([
        'session' => $session ? [
            'id' => $session->id,
            'customer_name' => $session->customer_name,
        ] : null,
    ]);
})->name('console-active-session.show');

// Menyimpan pesanan F&B
Route::post('/fnb-orders', [FnbOrderController::class, 'store'])
    ->name('fnb-orders.store');

// Memproses checkout sesi rental
Route::post('/checkout/{sessionId}', [CheckoutController::class, 'store'])
    ->name('checkout.store');
