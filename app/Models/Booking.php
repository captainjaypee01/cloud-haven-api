<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'reference_number',
        'check_in_date',
        'check_in_time',
        'check_out_date',
        'check_out_time',
        'guest_name',
        'guest_email',
        'guest_phone',
        'special_requests',
        'adults',
        'children',
        'total_guests',
        'promo_id',
        'total_price',
        'discount_amount',
        'final_price',
        'status',
        'reserved_until'
    ];

    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->reference_number) {
                // Example: NTDL-202507-8KD3QZ
                $prefix = 'NTDL';
                $date = now()->format('ymd');
                $rand = Str::upper(Str::random(6));
                $ref = "$prefix-$date-$rand";
                // Ensure uniqueness
                while (self::where('reference_number', $ref)->exists()) {
                    $rand = Str::upper(Str::random(6));
                    $ref = "$prefix-$date-$rand";
                }
                $model->reference_number = $ref;
            }
        });
    }
}
