<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\Payment;
use App\Models\RentalSession;
use App\Services\BillingCalculatorService;
use App\Services\SessionManagerService;
use App\Services\ShiftReconciliationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private ShiftReconciliationService $shifts,
        private SessionManagerService $sessionManager,
        private BillingCalculatorService $billing
    ) {
    }

    /**
     * Memproses pembayaran (checkout) untuk sesi rental PlayStation.
     */
    public function checkoutSession(Request $request, RentalSession $rentalSession)
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,qris'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $payment = DB::transaction(function () use ($rentalSession, $data) {
                $session = RentalSession::whereKey($rentalSession->id)->lockForUpdate()->firstOrFail();

                if ($session->payment_status === RentalSession::PAID) {
                    throw new DomainException('Sesi ini sudah dibayar (lunas).');
                }

                if ($session->payment_status === RentalSession::VOID || $session->session_status === RentalSession::CANCELLED) {
                    throw new DomainException('Sesi ini telah dibatalkan (void) dan tidak dapat dibayar.');
                }

                // Jika sesi belum di-stop, otomatis stop terlebih dahulu untuk menghitung sewa akhir
                if ($session->actual_end_time === null) {
                    $this->sessionManager->stop($session);
                    $session->refresh();
                }

                $shift = CashierShift::open()->lockForUpdate()->latest('opened_at')->first();
                if (! $shift) {
                    throw new DomainException('Tidak ada shift kasir yang sedang aktif. Buka shift terlebih dahulu.');
                }

                $billAmount = round((float) $session->grand_total, 2);
                $paidAmount = round((float) $data['paid_amount'], 2);

                if ($data['payment_method'] === Payment::QRIS) {
                    $paidAmount = $billAmount;
                    $changeAmount = 0.0;
                } else {
                    if ($paidAmount < $billAmount) {
                        throw new DomainException('Jumlah uang tunai yang dibayarkan kurang dari total tagihan.');
                    }
                    $changeAmount = round($paidAmount - $billAmount, 2);
                }

                $payment = Payment::create([
                    'rental_session_id' => $session->id,
                    'fnb_order_id' => null,
                    'shift_id' => $shift->id,
                    'payment_method' => $data['payment_method'],
                    'bill_amount' => $billAmount,
                    'paid_amount' => $paidAmount,
                    'change_amount' => $changeAmount,
                    'paid_at' => now(),
                ]);

                $session->update([
                    'payment_status' => RentalSession::PAID,
                    'session_status' => RentalSession::COMPLETED,
                ]);

                // Bebaskan meja konsol kembali ke AVAILABLE
                $console = Console::whereKey($session->console_id)->lockForUpdate()->first();
                if ($console && $console->status !== Console::MAINTENANCE) {
                    $console->update(['status' => Console::AVAILABLE]);
                }

                // Refresh saldo kas & QRIS di shift
                $this->shifts->refresh($shift);

                return $payment;
            });
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }

        $receiptUrl = route('sessions.receipt', [$rentalSession, 'print' => 1]);
        $message = "Pembayaran sesi {$rentalSession->invoice_number} berhasil.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'payment' => $payment,
                'change_amount' => $payment->change_amount,
                'receipt_url' => $receiptUrl,
            ], 200);
        }

        return redirect()->route('dashboard')
            ->with('status', $message)
            ->with('print_receipt_url', $receiptUrl);
    }
}
