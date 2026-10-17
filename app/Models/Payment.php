<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const CASH = 'cash';
    public const QRIS = 'qris';

    protected $fillable = [
        'rental_session_id',
        'fnb_order_id',
        'shift_id',
        'payment_method',
        'bill_amount',
        'paid_amount',
        'change_amount',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'bill_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function rentalSession(): BelongsTo
    {
        return $this->belongsTo(RentalSession::class, 'rental_session_id');
    }

    public function fnbOrder(): BelongsTo
    {
        return $this->belongsTo(FnbOrder::class, 'fnb_order_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }
}
