<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ShiftReconciliationService;
use DomainException;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private ShiftReconciliationService $shifts)
    {
    }

    /** POST /shifts/open */
    public function open(Request $request)
    {
        $data = $request->validate([
            'starting_cash' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'operator_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        try {
            $shift = $this->shifts->open(
                $this->operator($request, $data['operator_id'] ?? null),
                (float) $data['starting_cash']
            );
        } catch (DomainException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $message = "Shift dibuka oleh {$shift->user->name}, modal awal " . $this->rupiah($shift->starting_cash) . '.';

        return $this->ok($request, $message, ['shift' => $shift]);
    }

    /** POST /shifts/close */
    public function close(Request $request)
    {
        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ]);

        $shift = $this->shifts->current();
        if (! $shift) {
            return $this->fail($request, 'Tidak ada shift yang sedang berjalan.');
        }

        // Kasir hanya boleh menutup shift miliknya sendiri; admin boleh menutup shift siapa pun.
        // (Saat login belum ada, $request->user() kosong dan pengecekan ini dilewati.)
        $user = $request->user();
        if ($user && $user->role !== 'admin' && $shift->user_id !== $user->id) {
            return $this->fail($request, "Shift ini milik {$shift->user->name}. Hanya pemiliknya atau admin yang boleh menutup.", 403);
        }

        try {
            $result = $this->shifts->close($shift, (float) $data['actual_cash']);
        } catch (DomainException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $diff = $result['difference'];
        $selisih = abs($diff) < 0.005
            ? 'pas, tidak ada selisih'
            : ($diff < 0 ? 'KURANG ' : 'LEBIH ') . $this->rupiah(abs($diff));

        $message = 'Shift ditutup. Kas seharusnya ' . $this->rupiah($result['expected_cash'])
            . ', di laci ' . $this->rupiah($result['actual_cash'])
            . ' (' . $selisih . '). QRIS ' . $this->rupiah($result['qris_total']) . '.';

        return $this->ok($request, $message, $result);
    }

    /** GET /shifts/summary: ringkasan shift berjalan untuk jendela Tutup Shift. */
    public function summary()
    {
        $shift = $this->shifts->current();

        if (! $shift) {
            return response()->json(['message' => 'Tidak ada shift yang sedang berjalan.'], 404);
        }

        return response()->json($this->shifts->summary($shift) + [
            'shift_id' => $shift->id,
            'operator' => $shift->user->name,
            'opened_at' => $shift->opened_at->format('d/m/Y H:i'),
        ]);
    }

    /**
     * SEMENTARA: sebelum login Fiki jadi, operator dipilih dari form (operator_id)
     * atau memakai kasir aktif pertama. Setelah login ada, user yang login otomatis dipakai.
     */
    private function operator(Request $request, ?int $operatorId): User
    {
        if ($request->user()) {
            return $request->user();
        }

        if ($operatorId) {
            $chosen = User::where('is_active', true)->find($operatorId);
            if ($chosen) {
                return $chosen;
            }
        }

        return User::where('role', 'cashier')->where('is_active', true)->orderBy('id')->firstOrFail();
    }

    private function ok(Request $request, string $message, array $payload = [])
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $payload);
        }

        return redirect()->route('dashboard')->with('status', $message);
    }

    private function fail(Request $request, string $message, int $status = 422)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }

    private function rupiah(float|string|null $n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }
}
