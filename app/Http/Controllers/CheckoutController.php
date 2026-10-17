<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\Payment;
use App\Models\RentalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(Request $request, int $sessionId)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,qris'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $payment = DB::transaction(function () use ($validated, $sessionId) {
            $session = RentalSession::query()
                ->lockForUpdate()
                ->findOrFail($sessionId);

            // Sesi harus sudah di-Stop sebelum pembayaran.
            if ($session->session_status !== RentalSession::COMPLETED) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi harus dihentikan terlebih dahulu sebelum checkout.',
                ]);
            }

            // Cegah pembayaran ganda.
            if ($session->payment_status === RentalSession::PAID) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi ini sudah dibayar.',
                ]);
            }

            // Pastikan ada shift kasir aktif.
            $shift = CashierShift::open()
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if (!$shift) {
                throw ValidationException::withMessages([
                    'shift' => 'Tidak ada shift kasir yang aktif.',
                ]);
            }

            // Gunakan grand_total yang sudah dihitung saat sesi dihentikan.
            $billAmount = round((float) $session->grand_total, 2);
            $paidAmount = round((float) $validated['paid_amount'], 2);

            if ($billAmount < 0) {
                throw ValidationException::withMessages([
                    'session' => 'Total tagihan tidak valid.',
                ]);
            }

            // QRIS harus sesuai nominal tagihan.
            // Tunai boleh lebih besar dan menghasilkan kembalian.
            if ($validated['payment_method'] === Payment::QRIS) {
                if ($paidAmount != $billAmount) {
                    throw ValidationException::withMessages([
                        'paid_amount' => 'Nominal QRIS harus sama dengan total tagihan.',
                    ]);
                }

                $changeAmount = 0;
            } else {
                if ($paidAmount < $billAmount) {
                    throw ValidationException::withMessages([
                        'paid_amount' => 'Uang yang dibayarkan kurang dari total tagihan.',
                    ]);
                }

                $changeAmount = round($paidAmount - $billAmount, 2);
            }

            // Simpan pembayaran.
            $payment = Payment::create([
                'rental_session_id' => $session->id,
                'fnb_order_id' => null,
                'shift_id' => $shift->id,
                'payment_method' => $validated['payment_method'],
                'bill_amount' => $billAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'paid_at' => now(),
            ]);

            // Tandai sesi sudah dibayar.
            $session->update([
                'payment_status' => RentalSession::PAID,
            ]);

            // Kembalikan meja menjadi available.
            $console = Console::query()
                ->lockForUpdate()
                ->findOrFail($session->console_id);

            $console->update([
                'status' => Console::AVAILABLE,
            ]);

            return $payment;
        });

        return response()->json([
            'message' => 'Checkout berhasil.',
            'data' => $payment,
        ], 201);
    }
}
