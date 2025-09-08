<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MealProgram extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'status',
        'scope_type',
        'date_start',
        'date_end',
        'months',
        'weekdays',
        'weekend_definition',
        'inactive_label',
        'pm_snack_policy',
        'buffet_enabled',
        'notes',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'months' => 'array',
        'weekdays' => 'array',
        'buffet_enabled' => 'boolean',
    ];

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(MealPricingTier::class);
    }

    public function calendarOverrides(): HasMany
    {
        return $this->hasMany(MealCalendarOverride::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
