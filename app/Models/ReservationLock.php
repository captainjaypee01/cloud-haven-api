<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationLock extends Model
{
    protected $fillable = [
        'booking_id',
        'room_id',
        'quantity',
        'start_date',
        'end_date',
        'expires_at'
    ];

    protected $dates = ['expires_at'];

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
