<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRoom extends Model
{
    protected $table = 'booking_rooms';
    protected $fillable = [
        'booking_id',
        'room_id',
        'room_unit_id',
        'price_per_night',
        'adults',
        'children',
        'total_guests',
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
