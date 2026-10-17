<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsoleCategory extends Model
{
    protected $fillable = [
        'name',
        'hourly_rate',
        'min_charge_minutes',
        'rounding_minutes',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'min_charge_minutes' => 'integer',
            'rounding_minutes' => 'integer',
        ];
    }

    public function consoles(): HasMany
    {
        return $this->hasMany(Console::class, 'category_id');
    }

    public function pricingPackages(): HasMany
    {
        return $this->hasMany(PricingPackage::class, 'category_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(PricingPackage::class, 'category_id');
    }
}
