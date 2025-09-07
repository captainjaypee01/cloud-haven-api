<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DayTourPricing extends Model
{
    use HasFactory;

    protected $table = 'day_tour_pricing';

    protected $fillable = [
        'name',
        'description',
        'price_per_pax',
        'effective_from',
        'effective_until',
        'is_active'
    ];

    protected $casts = [
        'price_per_pax' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Get the active pricing for a specific date
     */
    public static function getActivePricingForDate(Carbon $date): ?self
    {
        return self::where('is_active', true)
            ->where('effective_from', '<=', $date->format('Y-m-d'))
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date->format('Y-m-d'));
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Get the current active pricing
     */
    public static function getCurrentActivePricing(): ?self
    {
        return self::getActivePricingForDate(Carbon::now());
    }

    /**
     * Check if this pricing is effective for a given date
     */
    public function isEffectiveForDate(Carbon $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->effective_from > $date) {
            return false;
        }

        if ($this->effective_until && $this->effective_until < $date) {
            return false;
        }

        return true;
    }
}