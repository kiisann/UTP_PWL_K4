<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\PricingPackage;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index()
    {
        $consoles = $this->loadConsoles();

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

       
        $serverNow = now()->getTimestamp() * 1000;
        $signature = $this->signature($consoles);

        return view('dashboard.index', compact(
            'consoles', 'summary', 'activeShift', 'packages', 'availableConsoles', 'serverNow', 'signature'
        ));
    }

    
    public function liveStatus()
    {
        $consoles = $this->loadConsoles();
        $now = now();

        return response()->json([
            'server_time' => $now->toIso8601String(),
            'signature' => $this->signature($consoles),
            'consoles' => $consoles->map(function ($console) use ($now) {
                $s = $console->activeSession;

                return [
                    'id' => $console->id,
                    'code' => $console->code,
                    'name' => $console->name,
                    'category' => $console->category->name,
                    'status' => $console->status,
                    'session' => $s ? [
                        'id' => $s->id,
                        'invoice_number' => $s->invoice_number,
                        'customer_name' => $s->customer_name,
                        'billing_mode' => $s->billing_mode,
                        'start_time' => $s->start_time->toIso8601String(),
                        'planned_end_time' => $s->planned_end_time?->toIso8601String(),
                        'actual_end_time' => $s->actual_end_time?->toIso8601String(),
                        'remaining_seconds' => ($s->planned_end_time && ! $s->actual_end_time)
                            ? max(0, $s->planned_end_time->getTimestamp() - $now->getTimestamp())
                            : null,
                        'elapsed_seconds' => max(
                            0,
                            ($s->actual_end_time ?? $now)->getTimestamp() - $s->start_time->getTimestamp()
                        ),
                        'rental_amount' => (float) $s->rental_amount,
                        'fnb_amount' => (float) $s->fnb_amount,
                        'grand_total' => (float) $s->grand_total,
                    ] : null,
                ];
            })->values(),
        ]);
    }

    private function loadConsoles(): Collection
    {
        return Console::with(['category', 'activeSession'])
            ->orderBy('code')
            ->get();
    }

    
    private function signature(Collection $consoles): string
    {
        return md5($consoles->map(fn ($c) => [
            $c->id,
            $c->status,
            $c->activeSession?->id,
            $c->activeSession?->updated_at?->getTimestamp(),
            $c->activeSession?->planned_end_time?->getTimestamp(),
            $c->activeSession?->actual_end_time?->getTimestamp(),
        ])->toJson());
    }
}
