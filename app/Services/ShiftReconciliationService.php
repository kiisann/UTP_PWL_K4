<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\RentalSession;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Buka/tutup shift kasir dan rekonsiliasi laci (Bab 2 dan 5 dokumen).
 *
 * Sumber kebenaran uang adalah tabel `payments`: kas dan QRIS dihitung ulang
 * dari pembayaran yang shift_id-nya = shift ini. Jadi tidak ada angka yang
 * perlu "ditambah-tambah" manual, dan tidak bisa meleset.
 *
 *   kas seharusnya di laci = modal awal + total tunai bersih (dibayar - kembalian)
 *   QRIS                   = total non-tunai (tidak masuk laci)
 *
 * UNTUK NIKO (checkout): saat menyimpan Payment, isi shift_id dengan
 *   app(ShiftReconciliationService::class)->current()->id
 * lalu panggil refresh($shift) supaya angka shift ikut ter-update.
 */
class ShiftReconciliationService
{
    /** Shift yang sedang berjalan (belum ditutup), atau null. */
    public function current(): ?CashierShift
    {
        return CashierShift::open()->with('user')->latest('opened_at')->first();
    }

    /** Buka shift baru dengan modal awal laci. Hanya satu shift boleh berjalan. */
    public function open(User $operator, float $startingCash): CashierShift
    {
        if ($startingCash < 0) {
            throw new DomainException('Modal awal tidak boleh negatif.');
        }

        return DB::transaction(function () use ($operator, $startingCash) {
            $running = CashierShift::open()->with('user')->lockForUpdate()->first();

            if ($running) {
                throw new DomainException(
                    "Shift {$running->user->name} (sejak {$running->opened_at->format('d/m H:i')}) masih berjalan. Tutup dulu sebelum membuka shift baru."
                );
            }

            return CashierShift::create([
                'user_id' => $operator->id,
                'opened_at' => now(),
                'starting_cash' => $startingCash,
                'expected_cash' => $startingCash,
                'qris_total' => 0,
            ])->load('user');
        });
    }

    /**
     * Ringkasan uang shift (dihitung langsung dari tabel payments).
     *
     * @return array{starting_cash: float, cash_sales: float, qris_total: float, expected_cash: float, payment_count: int, running_sessions: int}
     */
    public function summary(CashierShift $shift): array
    {
        $starting = (float) $shift->starting_cash;
        $cashSales = $this->netByMethod($shift, Payment::CASH);
        $qris = $this->netByMethod($shift, Payment::QRIS);

        return [
            'starting_cash' => $starting,
            'cash_sales' => $cashSales,
            'qris_total' => $qris,
            'expected_cash' => round($starting + $cashSales, 2),
            'payment_count' => Payment::where('shift_id', $shift->id)->count(),
            // sesi yang masih jalan / belum dibayar (di semua shift)
            'running_sessions' => RentalSession::where('session_status', RentalSession::RUNNING)->count(),
        ];
    }

    /** Hitung ulang expected_cash dan qris_total shift dari tabel payments. */
    public function refresh(CashierShift $shift): CashierShift
    {
        $summary = $this->summary($shift);

        $shift->update([
            'expected_cash' => $summary['expected_cash'],
            'qris_total' => $summary['qris_total'],
        ]);

        return $shift;
    }

    /**
     * Tutup shift: cocokkan uang tunai fisik di laci dengan perhitungan sistem.
     *
     * @return array{shift: CashierShift, expected_cash: float, actual_cash: float, difference: float, qris_total: float}
     *         difference = fisik - sistem (negatif = kurang, positif = lebih)
     */
    public function close(CashierShift $shift, float $actualCash): array
    {
        if ($actualCash < 0) {
            throw new DomainException('Uang tunai fisik tidak boleh negatif.');
        }

        return DB::transaction(function () use ($shift, $actualCash) {
            $locked = CashierShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->closed_at !== null) {
                throw new DomainException('Shift ini sudah ditutup.');
            }

            $this->refresh($locked);

            $locked->update([
                'actual_cash' => $actualCash,
                'closed_at' => now(),
            ]);

            $expected = (float) $locked->expected_cash;

            return [
                'shift' => $locked->fresh('user'),
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'difference' => round($actualCash - $expected, 2),
                'qris_total' => (float) $locked->qris_total,
            ];
        });
    }

    /** Total bersih (dibayar - kembalian) untuk satu metode pembayaran di shift ini. */
    private function netByMethod(CashierShift $shift, string $method): float
    {
        return round((float) Payment::where('shift_id', $shift->id)
            ->where('payment_method', $method)
            ->sum(DB::raw('paid_amount - change_amount')), 2);
    }
}
