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
        'proof_upload_count',
        'proof_upload_generation',
        'proof_status',
        'proof_last_file_path',
        'proof_rejected_reason',
        'proof_rejected_by',
        'last_proof_notification_at',
        'proof_last_uploaded_at',
    ];

    protected $appends = ['local_created_at', 'proof_image_url'];

    public function getProofImageUrlAttribute()
    {
        // New system: use proof_last_file_path if available
        if ($this->proof_last_file_path) {
            return asset('storage/' . ltrim($this->proof_last_file_path, '/'));
        }
        
        // Legacy system: use proof_image_path
        if ($this->proof_image_path) {
            return asset('storage/' . ltrim($this->proof_image_path, '/'));
        }
        
        return null;
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

    public function rejectedByUser()
    {
        return $this->belongsTo(User::class, 'proof_rejected_by');
    }

    protected function casts(): array
    {
        return [
            'last_proof_notification_at' => 'datetime',
            'proof_last_uploaded_at' => 'datetime',
            'response_data' => 'array',
        ];
    }
}
