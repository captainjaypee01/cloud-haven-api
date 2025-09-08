<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealCalendarOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_program_id',
        'override_type',
        'date',
        'month',
        'year',
        'is_active',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'month' => 'integer',
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function mealProgram(): BelongsTo
    {
        return $this->belongsTo(MealProgram::class);
    }
}
