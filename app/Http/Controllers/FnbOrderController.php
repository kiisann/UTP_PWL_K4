<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\FnbOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RentalSession;
use App\Services\BillingCalculatorService;
use App\Services\ShiftReconciliationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FnbOrderController extends Controller
{
    public function __construct(
        private ShiftReconciliationService $shifts,
        private BillingCalculatorService $billing
    ) {
    }

    /**
     * Mengambil daftar produk aktif dengan stok untuk kasir.
     */
    public function products()
    {
        $products = Product::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'category', 'sell_price', 'stock']);

        return response()->json($products);
    }

    /**
     * Menambahkan pesanan F&B ke sesi rental yang sedang berjalan atau menunggu bayar.
     */
    public function orderForSession(Request $request, RentalSession $rentalSession)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = DB::transaction(function () use ($rentalSession, $data) {
                $session = RentalSession::whereKey($rentalSession->id)->lockForUpdate()->firstOrFail();

                if ($session->payment_status === RentalSession::PAID) {
                    throw new DomainException('Sesi ini sudah lunas, tidak dapat menambah pesanan F&B.');
                }
                if ($session->payment_status === RentalSession::VOID || $session->session_status === RentalSession::CANCELLED) {
                    throw new DomainException('Sesi ini telah dibatalkan (void).');
                }

                $shift = CashierShift::open()->latest('opened_at')->first();
                if (! $shift) {
                    throw new DomainException('Belum ada shift kasir yang aktif.');
                }

                $order = FnbOrder::create([
                    'order_number' => 'FNB-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'rental_session_id' => $session->id,
                    'shift_id' => $shift->id,
                    'customer_name' => $session->customer_name,
                    'total_amount' => 0,
                    'order_status' => 'open',
                ]);

                $total = 0.0;

                foreach ($data['items'] as $itemData) {
                    $qty = (int) $itemData['qty'];
                    $product = Product::whereKey($itemData['product_id'])->lockForUpdate()->firstOrFail();

                    if (! $product->is_active) {
                        throw new DomainException("Produk {$product->name} sedang nonaktif.");
                    }

                    if ($product->stock < $qty) {
                        throw new DomainException("Stok {$product->name} tidak cukup (sisa {$product->stock}).");
                    }

                    $subtotal = round((float) $product->sell_price * $qty, 2);

                    $order->items()->create([
                        'product_id' => $product->id,
                        'qty' => $qty,
                        'unit_price' => $product->sell_price,
                        'subtotal' => $subtotal,
                    ]);

                    $product->decrement('stock', $qty);
                    $total += $subtotal;
                }

                $order->update(['total_amount' => $total]);

                // Update fnb_amount & grand_total pada sesi rental
                $session->fnb_amount = round((float) $session->fnb_amount + $total, 2);
                $session->grand_total = $this->billing->grandTotal(
                    (float) $session->rental_amount,
                    (float) $session->fnb_amount,
                    (float) $session->discount_amount
                );
                $session->save();

                return $order->load('items.product');
            });
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }

        $message = 'Pesanan F&B berhasil ditambahkan ke ' . ($rentalSession->console->name ?? 'meja') . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'order' => $order,
                'fnb_amount' => (float) $rentalSession->fresh()->fnb_amount,
                'grand_total' => (float) $rentalSession->fresh()->grand_total,
            ], 201);
        }

        return redirect()->route('dashboard')->with('status', $message);
    }

    /**
     * Transaksi F&B Walk-in (beli langsung tanpa menyewa konsol PS) dan langsung bayar.
     */
    public function walkIn(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,qris'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $result = DB::transaction(function () use ($data) {
                $shift = CashierShift::open()->lockForUpdate()->latest('opened_at')->first();
                if (! $shift) {
                    throw new DomainException('Belum ada shift kasir yang aktif. Buka shift terlebih dahulu.');
                }

                $order = FnbOrder::create([
                    'order_number' => 'WIK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'rental_session_id' => null,
                    'shift_id' => $shift->id,
                    'customer_name' => $data['customer_name'] ?? 'Umum',
                    'total_amount' => 0,
                    'order_status' => 'paid',
                ]);

                $orderTotal = 0.0;

                foreach ($data['items'] as $itemData) {
                    $qty = (int) $itemData['qty'];
                    $product = Product::whereKey($itemData['product_id'])->lockForUpdate()->firstOrFail();

                    if (! $product->is_active) {
                        throw new DomainException("Produk {$product->name} sedang nonaktif.");
                    }

                    if ($product->stock < $qty) {
                        throw new DomainException("Stok {$product->name} tidak cukup (sisa {$product->stock}).");
                    }

                    $subtotal = round((float) $product->sell_price * $qty, 2);

                    $order->items()->create([
                        'product_id' => $product->id,
                        'qty' => $qty,
                        'unit_price' => $product->sell_price,
                        'subtotal' => $subtotal,
                    ]);

                    $product->decrement('stock', $qty);
                    $orderTotal += $subtotal;
                }

                $order->update(['total_amount' => $orderTotal]);

                $paidAmount = round((float) $data['paid_amount'], 2);

                if ($data['payment_method'] === Payment::QRIS) {
                    $paidAmount = $orderTotal;
                    $changeAmount = 0.0;
                } else {
                    if ($paidAmount < $orderTotal) {
                        throw new DomainException('Jumlah uang tunai yang dibayarkan kurang dari total pesanan.');
                    }
                    $changeAmount = round($paidAmount - $orderTotal, 2);
                }

                $payment = Payment::create([
                    'rental_session_id' => null,
                    'fnb_order_id' => $order->id,
                    'shift_id' => $shift->id,
                    'payment_method' => $data['payment_method'],
                    'bill_amount' => $orderTotal,
                    'paid_amount' => $paidAmount,
                    'change_amount' => $changeAmount,
                    'paid_at' => now(),
                ]);

                $this->shifts->refresh($shift);

                return [
                    'order' => $order->load('items.product'),
                    'payment' => $payment,
                    'change_amount' => $changeAmount,
                ];
            });
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }

        $receiptUrl = route('fnb-orders.receipt', [$result['order'], 'print' => 1]);
        $message = "Pesanan Walk-in {$result['order']->order_number} berhasil dibayar.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'order' => $result['order'],
                'payment' => $result['payment'],
                'change_amount' => $result['change_amount'],
                'receipt_url' => $receiptUrl,
            ], 201);
        }

        return redirect()->route('dashboard')
            ->with('status', $message)
            ->with('print_receipt_url', $receiptUrl);
    }
}
