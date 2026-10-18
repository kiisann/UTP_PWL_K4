<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\FnbOrder;
use App\Models\Payment;
use App\Models\RentalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * Checkout sesi rental yang sudah dihentikan.
     */
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

            if ($session->session_status !== RentalSession::COMPLETED) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi harus dihentikan terlebih dahulu sebelum checkout.',
                ]);
            }

            if ($session->payment_status === RentalSession::PAID) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi ini sudah dibayar.',
                ]);
            }

            $shift = CashierShift::open()
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if (!$shift) {
                throw ValidationException::withMessages([
                    'shift' => 'Tidak ada shift kasir yang aktif.',
                ]);
            }

            $billAmount = round((float) $session->grand_total, 2);
            $paidAmount = round((float) $validated['paid_amount'], 2);

            if ($billAmount < 0) {
                throw ValidationException::withMessages([
                    'session' => 'Total tagihan tidak valid.',
                ]);
            }

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

            $session->update([
                'payment_status' => RentalSession::PAID,
            ]);

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

    /**
     * Checkout pesanan F&B Walk-in tanpa sesi rental.
     */
    public function checkoutWalkIn(Request $request, int $orderId)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,qris'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $payment = DB::transaction(function () use ($validated, $orderId) {
            $order = FnbOrder::query()
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->rental_session_id !== null) {
                throw ValidationException::withMessages([
                    'order' => 'Pesanan ini terhubung ke sesi rental, bukan Walk-in.',
                ]);
            }

            if ($order->order_status !== 'open') {
                throw ValidationException::withMessages([
                    'order' => 'Pesanan Walk-in ini sudah diproses atau dibayar.',
                ]);
            }

            if ($order->payments()->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'Pembayaran pesanan ini sudah tercatat.',
                ]);
            }

            $shift = CashierShift::open()
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if (!$shift) {
                throw ValidationException::withMessages([
                    'shift' => 'Tidak ada shift kasir yang aktif.',
                ]);
            }

            $billAmount = round((float) $order->total_amount, 2);
            $paidAmount = round((float) $validated['paid_amount'], 2);

            if ($billAmount < 0) {
                throw ValidationException::withMessages([
                    'order' => 'Total pesanan tidak valid.',
                ]);
            }

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

            $payment = Payment::create([
                'rental_session_id' => null,
                'fnb_order_id' => $order->id,
                'shift_id' => $shift->id,
                'payment_method' => $validated['payment_method'],
                'bill_amount' => $billAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'paid_at' => now(),
            ]);

            $order->update([
                'order_status' => 'paid',
            ]);

            return $payment;
        });

        return response()->json([
            'message' => 'Pembayaran Walk-in berhasil.',
            'data' => $payment,
        ], 201);
    }
}
