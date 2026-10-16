<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\RentalSession;
use App\Services\BillingCalculatorService;
use App\Services\SessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    protected $sessionManager;

    public function __construct(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;
    }

    public function process(Request $request, $sessionId)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,qris',
            'amount_paid'    => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $sessionId) {
            $session = RentalSession::findOrFail($sessionId);

            
            if ($session->status !== 'completed' && $session->status !== 'stopped') {
                $this->sessionManager->stop($session);
                $session->refresh();
            }

            
            $billing = BillingCalculatorService::forSession($session);
            $grandTotal = $billing['grand_total'] ?? ($session->rental_amount + $session->fnb_amount);

            if ($validated['amount_paid'] < $grandTotal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah uang pembayaran kurang.'
                ], 422);
            }

            $changeAmount = $validated['amount_paid'] - $grandTotal;

            
            $payment = Payment::create([
                'rental_session_id' => $session->id,
                'payment_method'    => $validated['payment_method'],
                'total_amount'      => $grandTotal,
                'amount_paid'       => $validated['amount_paid'],
                'change_amount'     => $changeAmount,
                'status'            => 'paid',
            ]);

            
            $session->update([
                'payment_status' => 'paid',
                'status'         => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil.',
                'data'    => [
                    'payment' => $payment,
                    'change'  => $changeAmount
                ]
            ]);
        });
    }

    
    public function getBillingData($sessionId)
    {
        $session = RentalSession::with(['fnbOrders.items.product'])->findOrFail($sessionId);
        $billing = BillingCalculatorService::forSession($session);

        return response()->json([
            'session' => $session,
            'billing' => $billing
        ]);
    }
}