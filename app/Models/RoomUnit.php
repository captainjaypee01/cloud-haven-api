<?php

namespace App\Models;

use App\Enums\RoomUnitStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'unit_number',
        'status',
        'notes',
        'maintenance_start_at',
        'maintenance_end_at',
        'blocked_start_at',
        'blocked_end_at',
    ];

    protected $casts = [
        'status' => RoomUnitStatusEnum::class,
        'maintenance_start_at' => 'date',
        'maintenance_end_at' => 'date',
        'blocked_start_at' => 'date',
        'blocked_end_at' => 'date',
    ];

    /**
     * Customize the array representation to include properly formatted dates.
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Override date fields to ensure they're in Y-m-d format for HTML inputs
        $array['maintenance_start_at'] = $this->maintenance_start_at?->format('Y-m-d');
        $array['maintenance_end_at'] = $this->maintenance_end_at?->format('Y-m-d');
        $array['blocked_start_at'] = $this->blocked_start_at?->format('Y-m-d');
        $array['blocked_end_at'] = $this->blocked_end_at?->format('Y-m-d');
        
        return $array;
    }

    /**
     * Get the room type this unit belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get all booking rooms that were assigned to this unit.
     */
    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class);
    }

    /**
     * Check if this unit is available for booking on specific dates.
     */
    public function isAvailableForDates(string $checkInDate, string $checkOutDate): bool
    {
        // If unit is under maintenance or blocked, it's not available
        if (in_array($this->status, [RoomUnitStatusEnum::MAINTENANCE, RoomUnitStatusEnum::BLOCKED])) {
            return false;
        }

        // Check if unit has conflicting bookings for the given dates
        return !$this->bookingRooms()
            ->whereHas('booking', function ($query) use ($checkInDate, $checkOutDate) {
                $query->whereIn('status', ['paid', 'downpayment'])
                      ->where(function ($q) use ($checkInDate, $checkOutDate) {
                          $q->where('check_in_date', '<', $checkOutDate)
                            ->where('check_out_date', '>', $checkInDate);
                      });
            })
            ->exists();
    }

    /**
     * Check if this unit has current/upcoming bookings.
     */
    public function hasCurrentBookings(): bool
    {
        $today = now()->toDateString();
        
        return $this->bookingRooms()
            ->whereHas('booking', function ($query) use ($today) {
                $query->whereIn('status', ['paid', 'downpayment'])
                      ->where('check_out_date', '>=', $today);
            })
            ->exists();
    }

    /**
     * Scope to get units that are not under maintenance or blocked.
     */
    public function scopeBookable($query)
    {
        return $query->whereNotIn('status', [RoomUnitStatusEnum::MAINTENANCE, RoomUnitStatusEnum::BLOCKED]);
    }

    /**
     * Scope to get units for a specific room type.
     */
    public function scopeForRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    /**
     * Check if unit is in maintenance on a specific date.
     */
    public function isInMaintenanceOnDate(string $date): bool
    {
        if (!$this->maintenance_start_at || !$this->maintenance_end_at) {
            return false;
        }
        
        return $date >= $this->maintenance_start_at->format('Y-m-d') && 
               $date <= $this->maintenance_end_at->format('Y-m-d');
    }

    /**
     * Check if unit is blocked on a specific date.
     */
    public function isBlockedOnDate(string $date): bool
    {
        if (!$this->blocked_start_at || !$this->blocked_end_at) {
            return false;
        }
        
        return $date >= $this->blocked_start_at->format('Y-m-d') && 
               $date <= $this->blocked_end_at->format('Y-m-d');
    }

    /**
     * Get unit status for a specific date.
     * Returns: 'booked', 'pending', 'maintenance', 'blocked', or 'available'
     */
    public function getStatusForDate(string $date): string
    {
        // Check maintenance first (highest priority)
        if ($this->isInMaintenanceOnDate($date)) {
            return 'maintenance';
        }
        
        // Check blocked second
        if ($this->isBlockedOnDate($date)) {
            return 'blocked';
        }
        
        // Check if unit has bookings on this date
        $hasBooking = $this->bookingRooms()
            ->whereHas('booking', function ($query) use ($date) {
                $query->whereIn('status', ['paid', 'downpayment'])
                      ->where('check_in_date', '<=', $date)
                      ->where('check_out_date', '>', $date);
            })
            ->exists();
            
        if ($hasBooking) {
            return 'booked';
        }
        
        // Check for pending bookings
        $hasPendingBooking = $this->bookingRooms()
            ->whereHas('booking', function ($query) use ($date) {
                $query->where('status', 'pending')
                      ->where('check_in_date', '<=', $date)
                      ->where('check_out_date', '>', $date);
            })
            ->exists();
            
        if ($hasPendingBooking) {
            return 'pending';
        }
        
        return 'available';
    }

    /**
     * Get the display name for this unit (Room Name + Unit Number).
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->room?->name ?? 'Room') . ' - ' . $this->unit_number
        );
    }
}
