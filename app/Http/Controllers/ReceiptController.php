<?php

namespace App\Http\Controllers;

use App\Models\RentalSession;
use App\Services\BillingCalculatorService;
use Illuminate\Http\Request;

/**
 * Struk gabungan (Sewa PS + F&B) untuk printer thermal 58mm / 80mm lewat CSS Print.
 * Layout mengikuti Bab 6.2 dokumen: header toko, metadata, rincian sewa,
 * rincian F&B, dan ringkasan pembayaran.
 *
 *   GET /sessions/{id}/receipt             tampil di layar (ada tombol Cetak)
 *   GET /sessions/{id}/receipt?print=1     langsung membuka dialog cetak
 *   GET /sessions/{id}/receipt?width=80    kertas 80mm (default dari config/rental.php)
 *
 * Sesi yang belum dibayar tetap bisa dicetak sebagai PRATINJAU (ditandai jelas),
 * sesi void ditandai DIBATALKAN, hanya sesi lunas yang tercetak tanpa penanda.
 */
class ReceiptController extends Controller
{
    public function __construct(private BillingCalculatorService $billing)
    {
    }

    public function show(Request $request, RentalSession $rentalSession)
    {
        $s = $rentalSession->load(['console.category', 'user', 'package', 'payment', 'fnbOrders.items.product']);

        $width = (int) $request->query('width', config('rental.receipt_width'));
        $width = in_array($width, [58, 80], true) ? $width : 58;

        $now = now();
        $isPaid = $s->payment_status === RentalSession::PAID;
        $isVoid = $s->payment_status === RentalSession::VOID;
        $isPackage = $s->billing_mode === RentalSession::MODE_PACKAGE;

        $snap = $this->billing->snapshot($s, $now);

        // Yang sudah lunas memakai angka yang dulu ditagihkan; sisanya angka berjalan.
        $rental = $isPaid ? (float) $s->rental_amount : $snap['rental_amount'];
        $grandTotal = $isPaid ? (float) $s->grand_total : $snap['grand_total'];
        $billedMinutes = $isPaid ? (int) $s->total_minutes : $snap['total_minutes'];

        $end = $s->actual_end_time ?? ($isPackage ? $s->planned_end_time : $now);
        $endLabel = $s->actual_end_time || $isPackage ? $end?->format('H:i') : 'berjalan';

        $actualMinutes = (int) ceil(max(0, ($s->actual_end_time ?? $now)->getTimestamp() - $s->start_time->getTimestamp()) / 60);

        // Gabungkan item yang sama supaya struk tidak panjang.
        $fnbItems = $s->fnbOrders
            ->filter(fn ($o) => $o->order_status !== 'void')
            ->flatMap(fn ($o) => $o->items)
            ->groupBy(fn ($i) => $i->product_id . '|' . (float) $i->unit_price)
            ->map(fn ($g) => [
                'name' => $g->first()->product->name ?? 'Item',
                'qty' => (int) $g->sum('qty'),
                'unit_price' => (float) $g->first()->unit_price,
                'subtotal' => (float) $g->sum('subtotal'),
            ])
            ->values();

        return view('receipts.session', [
            's' => $s,
            'width' => $width,
            'autoPrint' => $request->boolean('print'),
            'store' => config('rental'),
            'printedAt' => $now,
            'isPaid' => $isPaid,
            'isVoid' => $isVoid,
            'isPackage' => $isPackage,
            'rental' => $rental,
            'fnb' => (float) $s->fnb_amount,
            'discount' => (float) $s->discount_amount,
            'grandTotal' => $grandTotal,
            'billedMinutes' => $billedMinutes,
            'actualMinutes' => $actualMinutes,
            'endLabel' => $endLabel,
            'fnbItems' => $fnbItems,
            'payment' => $s->payment,
        ]);
    }

    /**
     * Struk pesanan F&B Walk-in (tanpa rental PS).
     */
    public function showFnb(Request $request, \App\Models\FnbOrder $fnbOrder)
    {
        $order = $fnbOrder->load(['shift.user', 'payments', 'items.product']);

        $width = (int) $request->query('width', config('rental.receipt_width'));
        $width = in_array($width, [58, 80], true) ? $width : 58;

        $now = now();
        $payment = $order->payments()->latest('paid_at')->first();

        return view('receipts.walkin', [
            'order' => $order,
            'width' => $width,
            'autoPrint' => $request->boolean('print'),
            'store' => config('rental'),
            'printedAt' => $now,
            'payment' => $payment,
        ]);
    }
}

