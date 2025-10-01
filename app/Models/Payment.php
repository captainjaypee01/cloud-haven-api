<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    use HasFactory;
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
        'proof_rejected_at',
        'last_proof_notification_at',
        'proof_last_uploaded_at',
        'proof_uploaded_by',
        'downpayment_status',
    ];

    protected $appends = ['local_created_at', 'proof_image_url'];

    public function getProofImageUrlAttribute()
    {
        // New system: use proof_last_file_path if available
        if ($this->proof_last_file_path) {
            $url = $this->buildStorageUrl($this->proof_last_file_path);
            return $this->ensureHttps($url);
        }
        
        // Legacy system: use proof_image_path
        if ($this->proof_image_path) {
            $url = $this->buildStorageUrl($this->proof_image_path);
            return $this->ensureHttps($url);
        }
        
        return null;
    }
    
    /**
     * Build storage URL with proper protocol
     */
    private function buildStorageUrl(string $path): string
    {
        // Use the asset() helper which will work correctly once storage:link is created
        return asset('storage/' . ltrim($path, '/'));
    }
    
    /**
     * Ensure URL uses HTTPS in production environments
     */
    private function ensureHttps(string $url): string
    {
        // Check if we should force HTTPS based on multiple conditions
        $shouldForceHttps = config('app.force_https', false) || 
                           config('app.env') === 'production' || 
                           config('app.env') === 'uat' ||
                           (request() && request()->isSecure());
        
        if ($shouldForceHttps && str_starts_with($url, 'http://')) {
            return str_replace('http://', 'https://', $url);
        }
        
        return $url;
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

    /**
     * Check if proof was uploaded by guest
     */
    public function isProofUploadedByGuest(): bool
    {
        return $this->proof_uploaded_by === 'guest';
    }

    /**
     * Check if proof was uploaded by staff
     */
    public function isProofUploadedByStaff(): bool
    {
        return $this->proof_uploaded_by === 'staff';
    }

    /**
     * Check if proof can be modified by staff
     */
    public function canModifyProof(): bool
    {
        // Can modify if:
        // 1. No proof uploaded yet
        // 2. Proof uploaded by staff and not yet approved
        // 3. Walk-in booking and proof not yet approved
        if (!$this->proof_last_file_path) {
            return true;
        }

        if ($this->isProofUploadedByStaff() && $this->proof_status !== 'accepted') {
            return true;
        }

        if ($this->booking && $this->booking->isWalkIn() && $this->proof_status !== 'accepted') {
            return true;
        }

        return false;
    }
}
