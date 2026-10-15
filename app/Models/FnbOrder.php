<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FnbOrder extends Model
{
    protected $fillable = [
        'order_number',
        'rental_session_id',
        'shift_id',
        'customer_name',
        'total_amount',
        'order_status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /** NULL kalau pembelian langsung (walk-in). */
    public function rentalSession(): BelongsTo
    {
        return $this->belongsTo(RentalSession::class, 'rental_session_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FnbOrderItem::class, 'fnb_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'fnb_order_id');
    }
}
