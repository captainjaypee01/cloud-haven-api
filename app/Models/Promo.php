<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory;
    use SoftDeletes; // uncomment if using soft deletes (ensure `deleted_at` in migration)

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'scope',
        'title',
        'description',
        'image_url',
        'starts_at',
        'ends_at',
        'expires_at',
        'max_uses',
        'uses_count',
        'exclusive',
        'active',
        'excluded_days',
        'per_night_calculation'
    ];

    protected $casts = [
        'active'     => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'excluded_days' => 'array',
        'per_night_calculation' => 'boolean',
    ];

    /**
     * Mutator to ensure starts_at is saved with 00:00:00 time (no timezone conversion)
     */
    public function setStartsAtAttribute($value)
    {
        if ($value) {
            // If it's already in YYYY-MM-DD format, append 00:00:00
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->attributes['starts_at'] = $value . ' 00:00:00';
            } else {
                // If it's already a datetime, just use it as-is
                $this->attributes['starts_at'] = $value;
            }
        } else {
            $this->attributes['starts_at'] = null;
        }
    }

    /**
     * Mutator to ensure ends_at is saved with 00:00:00 time (no timezone conversion)
     */
    public function setEndsAtAttribute($value)
    {
        if ($value) {
            // If it's already in YYYY-MM-DD format, append 00:00:00
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->attributes['ends_at'] = $value . ' 00:00:00';
            } else {
                // If it's already a datetime, just use it as-is
                $this->attributes['ends_at'] = $value;
            }
        } else {
            $this->attributes['ends_at'] = null;
        }
    }


    /**
     * Relationship: Promo can be used in many bookings.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if a specific date is eligible for this promo (not in excluded days)
     *
     * @param \Carbon\Carbon $date
     * @return bool
     */
    public function isDateEligible(\Carbon\Carbon $date): bool
    {
        // If no excluded days are set, all dates are eligible
        if (empty($this->excluded_days)) {
            return true;
        }

        // Check if the day of week (0=Sunday, 1=Monday, ..., 6=Saturday) is in excluded days
        $dayOfWeek = $date->dayOfWeek;
        return !in_array($dayOfWeek, $this->excluded_days);
    }

    /**
     * Check if this promo uses per-night calculation
     *
     * @return bool
     */
    public function usesPerNightCalculation(): bool
    {
        return $this->per_night_calculation;
    }

    /**
     * Get the excluded days as day names for display purposes
     *
     * @return array
     */
    public function getExcludedDayNames(): array
    {
        if (empty($this->excluded_days)) {
            return [];
        }

        $dayNames = [
            0 => 'Sunday',
            1 => 'Monday', 
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday'
        ];

        return array_map(fn($day) => $dayNames[$day] ?? "Day {$day}", $this->excluded_days);
    }
}
