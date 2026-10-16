<?php

namespace App\Http\Controllers;

use App\Models\RentalSession;
use App\Models\User;
use App\Services\SessionManagerService;
use DomainException;
use Illuminate\Http\Request;

class RentalSessionController extends Controller
{
    public function __construct(private SessionManagerService $sessions)
    {
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'console_id' => ['required', 'integer', 'exists:consoles,id'],
            'billing_mode' => ['required', 'in:package,open'],
            'package_id' => ['required_if:billing_mode,package', 'nullable', 'integer', 'exists:pricing_packages,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->run($request, 'Sesi dimulai.', fn () => $this->sessions->start(
            $this->operator($request),
            (int) $data['console_id'],
            $data['billing_mode'],
            isset($data['package_id']) ? (int) $data['package_id'] : null,
            $data['customer_name'] ?? null
        ));
    }

    public function extend(Request $request, RentalSession $rentalSession)
    {
        $data = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:pricing_packages,id', 'required_without:minutes'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600', 'required_without:package_id'],
        ]);

        return $this->run($request, 'Waktu ditambah.', fn () => $this->sessions->extend(
            $rentalSession,
            isset($data['package_id']) ? (int) $data['package_id'] : null,
            isset($data['minutes']) ? (int) $data['minutes'] : null
        ));
    }

    public function move(Request $request, RentalSession $rentalSession)
    {
        $data = $request->validate([
            'console_id' => ['required', 'integer', 'exists:consoles,id'],
            'mark_old_maintenance' => ['nullable', 'boolean'],
        ]);

        return $this->run($request, 'Sesi dipindahkan.', fn () => $this->sessions->move(
            $rentalSession,
            (int) $data['console_id'],
            $request->boolean('mark_old_maintenance')
        ));
    }

    public function stop(Request $request, RentalSession $rentalSession)
    {
        return $this->run($request, 'Sesi dihentikan, menunggu pembayaran.', fn () => $this->sessions->stop($rentalSession));
    }

    
    private function run(Request $request, string $successMessage, callable $action)
    {
        try {
            $session = $action();
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $successMessage, 'session' => $session]);
        }

        return redirect()->route('dashboard')->with('status', $successMessage);
    }

   
    private function operator(Request $request): User
    {
        return $request->user()
            ?? User::where('role', 'cashier')->where('is_active', true)->orderBy('id')->firstOrFail();
    }
}
