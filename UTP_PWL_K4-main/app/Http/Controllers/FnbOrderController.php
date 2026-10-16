<?php

namespace App\Http\Controllers;

use App\Models\FnbOrder;
use App\Models\FnbOrderItem;
use App\Models\Product;
use App\Models\RentalSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FnbOrderController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rental_session_id'  => 'nullable|exists:rental_sessions,id',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $totalAmount = 0;
            $itemsToCreate = [];

            foreach ($validated['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                if ($product->stock < $itemData['qty']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok {$product->name} tidak mencukupi."
                    ], 422);
                }

               
                $product->decrement('stock', $itemData['qty']);

                $subtotal = $product->price * $itemData['qty'];
                $totalAmount += $subtotal;

                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'qty'        => $itemData['qty'],
                    'price'      => $product->price,
                    'subtotal'   => $subtotal,
                ];
            }

            
            $fnbOrder = FnbOrder::create([
                'rental_session_id' => $validated['rental_session_id'] ?? null,
                'total_amount'      => $totalAmount,
                'user_id'           => $request->user()->id ?? null,
            ]);

            
            foreach ($itemsToCreate as $item) {
                FnbOrderItem::create([
                    'fnb_order_id' => $fnbOrder->id,
                    'product_id'   => $item['product_id'],
                    'qty'          => $item['qty'],
                    'price'        => $item['price'],
                    'subtotal'     => $item['subtotal'],
                ]);
            }

        
            if (!empty($validated['rental_session_id'])) {
                $session = RentalSession::findOrFail($validated['rental_session_id']);
                $session->increment('fnb_amount', $totalAmount);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pesanan F&B berhasil ditambahkan & stok telah dipotong.',
                'data'    => $fnbOrder
            ]);
        });
    }
}