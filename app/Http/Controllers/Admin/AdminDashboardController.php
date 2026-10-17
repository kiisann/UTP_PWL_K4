<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Console;
use App\Models\ConsoleCategory;
use App\Models\Product;
use App\Models\RentalSession;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // 1. KPI Hari Ini
        $todaySessions = RentalSession::whereDate('start_time', $today)
            ->where('payment_status', RentalSession::PAID)
            ->get();

        $rentalRevenueToday = (float) $todaySessions->sum('rental_amount');
        $fnbRevenueToday    = (float) $todaySessions->sum('fnb_amount');
        $totalRevenueToday  = (float) $todaySessions->sum('grand_total');

        // 2. Status Bilik & Live Session
        $consoles = Console::with(['category', 'activeSession.package'])
            ->orderBy('code')
            ->get();

        $occupiedCount    = $consoles->where('status', Console::OCCUPIED)->count();
        $availableCount   = $consoles->where('status', Console::AVAILABLE)->count();
        $maintenanceCount = $consoles->where('status', Console::MAINTENANCE)->count();

        // 3. Shift Aktif
        $activeShift = CashierShift::open()
            ->with('user')
            ->latest('opened_at')
            ->first();

        // 4. Data Grafik 7 Hari Terakhir
        $chartLabels = [];
        $chartRental = [];
        $chartFnb    = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $chartLabels[] = $date->translatedFormat('D, d M');

            $daily = RentalSession::whereDate('start_time', $date)
                ->where('payment_status', RentalSession::PAID)
                ->selectRaw('COALESCE(SUM(rental_amount), 0) as rental, COALESCE(SUM(fnb_amount), 0) as fnb')
                ->first();

            $chartRental[] = (float) ($daily->rental ?? 0);
            $chartFnb[]    = (float) ($daily->fnb ?? 0);
        }

        // 5. Okupansi per Kategori Konsol
        $categoryUsage = ConsoleCategory::withCount('consoles')->orderBy('hourly_rate')->get();

        $lowStockCount = Product::where('stock', '<=', 10)->count();

        return view('admin.dashboard', compact(
            'rentalRevenueToday',
            'fnbRevenueToday',
            'totalRevenueToday',
            'consoles',
            'occupiedCount',
            'availableCount',
            'maintenanceCount',
            'activeShift',
            'chartLabels',
            'chartRental',
            'chartFnb',
            'categoryUsage',
            'lowStockCount'
        ));
    }
}
