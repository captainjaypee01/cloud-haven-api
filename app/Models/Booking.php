<?php

namespace App\Models;

use Carbon\Carbon;
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
        'meal_price',
        'discount_amount',
        'payment_option',
        'downpayment_amount',
        'final_price',
        'status',
        'failed_payment_attempts',
        'last_payment_failed_at',
        'reserved_until',
        'downpayment_at',
        'paid_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $appends = ['local_created_at', 'local_updated_at', 'local_downpayment_at', 'local_paid_at', 'local_reserved_until'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
        ];
    }
    public function bookingRooms()
    {
        return $this->hasMany(BookingRoom::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
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

    public function getLocalCreatedAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->created_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }

    public function getLocalUpdatedAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->updated_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }
    
    public function getLocalDownpaymentAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->downpayment_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }
    
    public function getLocalPaidAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->paid_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }
    
    public function getLocalReservedUntilAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->reserved_until)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }
}
