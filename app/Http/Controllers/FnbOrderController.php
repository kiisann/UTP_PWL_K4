<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Console;
use App\Models\FnbOrder;
use App\Models\Product;
use App\Models\RentalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FnbOrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rental_session_id' => ['nullable', 'integer', 'exists:rental_sessions,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($validated) {
            $shift = CashierShift::open()
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if (!$shift) {
                throw ValidationException::withMessages([
                    'shift' => 'Tidak ada shift kasir yang sedang aktif.',
                ]);
            }

            $session = null;

            if (!empty($validated['rental_session_id'])) {
                $session = RentalSession::query()
                    ->lockForUpdate()
                    ->findOrFail($validated['rental_session_id']);

                if ($session->session_status !== RentalSession::RUNNING) {
                    throw ValidationException::withMessages([
                        'rental_session_id' => 'F&B hanya dapat ditambahkan ke sesi yang sedang berjalan.',
                    ]);
                }
            }

            $order = FnbOrder::create([
                'order_number' => 'FNB-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5)),
                'rental_session_id' => $session?->id,
                'shift_id' => $shift->id,
                'customer_name' => $validated['customer_name'] ?? $session?->customer_name,
                'total_amount' => 0,
                'order_status' => 'open',
            ]);

            $total = 0;

            foreach ($validated['items'] as $item) {
                $product = Product::query()
                    ->whereKey($item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $item['qty'];

                if (!$product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => "Produk {$product->name} sedang tidak aktif.",
                    ]);
                }

                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}.",
                    ]);
                }

                $unitPrice = (float) $product->sell_price;
                $subtotal = round($unitPrice * $qty, 2);

                $order->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $product->decrement('stock', $qty);
                $total += $subtotal;
            }

            $order->update(['total_amount' => $total]);

            if ($session) {
                $session->fnb_amount = (float) $session->fnb_amount + $total;

               
                $session->grand_total =
                    (float) $session->rental_amount
                    + (float) $session->fnb_amount
                    - (float) $session->discount_amount;

                $session->save();
            }

            return $order;
        });

        return response()->json([
            'message' => 'Pesanan F&B berhasil disimpan.',
            'data' => $order->load('items.product'),
        ], 201);
    }
}
