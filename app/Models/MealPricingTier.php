<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_program_id',
        'currency',
        'adult_price',
        'child_price',
        'adult_lunch_price',
        'child_lunch_price',
        'adult_pm_snack_price',
        'child_pm_snack_price',
        'adult_dinner_price',
        'child_dinner_price',
        'adult_breakfast_price',
        'child_breakfast_price',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'adult_price' => 'decimal:2',
        'child_price' => 'decimal:2',
        'adult_lunch_price' => 'decimal:2',
        'child_lunch_price' => 'decimal:2',
        'adult_pm_snack_price' => 'decimal:2',
        'child_pm_snack_price' => 'decimal:2',
        'adult_dinner_price' => 'decimal:2',
        'child_dinner_price' => 'decimal:2',
        'adult_breakfast_price' => 'decimal:2',
        'child_breakfast_price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function mealProgram(): BelongsTo
    {
        return $this->belongsTo(MealProgram::class);
    }

    public function isEffectiveOn($date): bool
    {
        $date = \Carbon\Carbon::parse($date);
        
        if ($this->effective_from && $date->lt($this->effective_from)) {
            return false;
        }
        
        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }
        
        return true;
    }
}
