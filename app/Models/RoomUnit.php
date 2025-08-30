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
    ];

    protected $casts = [
        'status' => RoomUnitStatusEnum::class,
    ];

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
     * Get the display name for this unit (Room Name + Unit Number).
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->room?->name ?? 'Room') . ' - ' . $this->unit_number
        );
    }
}
