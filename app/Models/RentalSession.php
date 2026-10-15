<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalSession extends Model
{
    // billing_mode
    public const MODE_PACKAGE = 'package';
    public const MODE_OPEN = 'open';

    // session_status
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    // payment_status
    public const UNPAID = 'unpaid';
    public const PAID = 'paid';
    public const VOID = 'void';

    protected $fillable = [
        'invoice_number',
        'console_id',
        'shift_id',
        'user_id',
        'customer_name',
        'billing_mode',
        'package_id',
        'start_time',
        'planned_end_time',
        'actual_end_time',
        'total_minutes',
        'rental_amount',
        'fnb_amount',
        'discount_amount',
        'grand_total',
        'payment_status',
        'session_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'planned_end_time' => 'datetime',
            'actual_end_time' => 'datetime',
            'total_minutes' => 'integer',
            'rental_amount' => 'decimal:2',
            'fnb_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function console(): BelongsTo
    {
        return $this->belongsTo(Console::class, 'console_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(PricingPackage::class, 'package_id');
    }

    public function fnbOrders(): HasMany
    {
        return $this->hasMany(FnbOrder::class, 'rental_session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'rental_session_id');
    }
}
