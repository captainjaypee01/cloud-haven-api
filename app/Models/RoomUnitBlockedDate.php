<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RoomUnitBlockedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_unit_id',
        'start_date',
        'end_date',
        'expiry_date',
        'active',
        'notes',
    ];

    /**
     * Customize the array representation to include properly formatted dates.
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Override date fields to ensure they're in Y-m-d format for HTML inputs
        $array['start_date'] = $this->start_date?->format('Y-m-d');
        $array['end_date'] = $this->end_date?->format('Y-m-d');
        $array['expiry_date'] = $this->expiry_date?->format('Y-m-d');
        
        return $array;
    }

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expiry_date' => 'date',
        'active' => 'boolean',
    ];

    /**
     * Get the room unit this blocked date belongs to.
     */
    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(RoomUnit::class);
    }

    /**
     * Scope to get only active blocked dates.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get blocked dates that haven't expired yet.
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expiry_date', '>=', now()->toDateString());
    }

    /**
     * Scope to get blocked dates that are currently active and not expired.
     */
    public function scopeCurrentlyActive($query)
    {
        return $query->active()->notExpired();
    }

    /**
     * Check if this blocked date overlaps with given date range.
     */
    public function overlapsWith(string $checkInDate, string $checkOutDate): bool
    {
        return $this->start_date <= $checkOutDate && $this->end_date >= $checkInDate;
    }

    /**
     * Check if this blocked date is active on a specific date.
     */
    public function isActiveOnDate(string $date): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->expiry_date < $date) {
            return false;
        }

        return $date >= $this->start_date->format('Y-m-d') && 
               $date <= $this->end_date->format('Y-m-d');
    }

    /**
     * Check if this blocked date has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date < now()->toDateString();
    }

    /**
     * Automatically deactivate if expired.
     */
    public function checkAndDeactivateIfExpired(): bool
    {
        if ($this->isExpired() && $this->active) {
            $this->update(['active' => false]);
            return true;
        }
        return false;
    }

    /**
     * Get the duration of the blocked period in days.
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Get days remaining until expiry.
     */
    public function getDaysUntilExpiry(): int
    {
        $today = now()->toDateString();
        if ($this->expiry_date < $today) {
            return 0;
        }
        return Carbon::parse($today)->diffInDays($this->expiry_date);
    }
}