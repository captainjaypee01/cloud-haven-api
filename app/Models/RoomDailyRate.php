<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomDailyRate extends Model
{
    protected $fillable = [
        'room_id',
        'date',
        'price_per_night',
        'is_manual_override',
    ];

    protected $casts = [
        'date' => 'date',
        'price_per_night' => 'decimal:2',
        'is_manual_override' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
