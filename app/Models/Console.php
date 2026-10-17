<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Console extends Model
{
    public const AVAILABLE = 'available';
    public const OCCUPIED = 'occupied';
    public const MAINTENANCE = 'maintenance';

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ConsoleCategory::class, 'category_id');
    }

    public function rentalSessions(): HasMany
    {
        return $this->hasMany(RentalSession::class, 'console_id');
    }

    /** Sesi yang sedang berjalan di meja ini (kalau ada). */
    public function activeSession(): HasOne
    {
        return $this->hasOne(RentalSession::class, 'console_id')
            ->where('session_status', RentalSession::RUNNING);
    }
}
