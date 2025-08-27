<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'provider',
        'status',
        'amount',
        'error_code',
        'error_message',
        'transaction_id',
        'remarks',
        'response_data',
        'proof_image_path',
    ];

    protected $appends = ['local_created_at', 'proof_image_url'];

    public function getProofImageUrlAttribute()
    {
        if (!$this->proof_image_path) {
            return null;
        }
        // Use asset helper to avoid driver-specific URL method
        return asset('storage/' . ltrim($this->proof_image_path, '/'));
    }
    public function getLocalCreatedAtAttribute()
    {
        $userTimezone = "Asia/Singapore";
        return Carbon::parse($this->created_at)
            ->setTimezone($userTimezone)
            ->format('Y-m-d H:i:s');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
