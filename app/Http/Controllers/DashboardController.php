<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;

class DashboardController extends Controller
{
    public function index()
    {
        $consoles = Console::with(['category', 'activeSession'])
            ->orderBy('code')
            ->get();

        $summary = [
            'available'   => $consoles->where('status', Console::AVAILABLE)->count(),
            'occupied'    => $consoles->where('status', Console::OCCUPIED)->count(),
            'maintenance' => $consoles->where('status', Console::MAINTENANCE)->count(),
        ];

        
        $activeShift = CashierShift::open()->with('user')->latest('opened_at')->first();

        return view('dashboard.index', compact('consoles', 'summary', 'activeShift'));
    }
}
