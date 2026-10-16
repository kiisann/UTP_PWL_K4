<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\PricingPackage;

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

       
        $packages = PricingPackage::active()
            ->orderBy('duration_minutes')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($group) => $group->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'duration_minutes' => $p->duration_minutes,
                'price' => (float) $p->price,
            ])->values())
            ->toArray();

    
        $availableConsoles = $consoles
            ->where('status', Console::AVAILABLE)
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->toArray();

        return view('dashboard.index', compact('consoles', 'summary', 'activeShift', 'packages', 'availableConsoles'));
    }
}
