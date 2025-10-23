<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRoom extends Model
{
    use HasFactory;
    protected $table = 'booking_rooms';
    protected $fillable = [
        'booking_id',
        'room_id',
        'room_unit_id',
        'price_per_night',
        'adults',
        'children',
        'total_guests',
        // Day Tour meal details
        'include_lunch',
        'include_pm_snack',
        'include_dinner',
        'lunch_cost',
        'pm_snack_cost',
        'dinner_cost',
        'meal_cost',
        'base_price',
        'total_price',
    ];
    
    protected $casts = [
        'include_lunch' => 'boolean',
        'include_pm_snack' => 'boolean',
        'include_dinner' => 'boolean',
        'lunch_cost' => 'decimal:2',
        'pm_snack_cost' => 'decimal:2',
        'dinner_cost' => 'decimal:2',
        'meal_cost' => 'decimal:2',
        'base_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(RoomUnit::class);
    }
}
