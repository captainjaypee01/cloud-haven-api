<?php

namespace App\Models;

use App\Enums\RoomStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Room extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = ['name', 'slug', 'short_description', 'description', 'max_guests', 'extra_guest_fee', 'extra_guests', 'quantity', 'allows_day_use', 'room_type', 'is_featured', 'base_weekend_rate', 'base_weekday_rate', 'price_per_night', 'min_guests', 'max_guests_range', 'status', 'updated_by', 'created_by'];

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn($value) => RoomStatusEnum::from($value)->label(),
            set: fn($value) => is_string($value)
                ? RoomStatusEnum::fromLabel($value)->value
                : $value
        );
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images()
    {
        return $this->belongsToMany(Image::class, 'room_image')->withPivot('order');
    }

    public function roomUnits()
    {
        return $this->hasMany(RoomUnit::class);
    }

    public function availableUnits()
    {
        return $this->roomUnits()->available();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Scope to filter by room type
     */
    public function scopeByRoomType($query, string $roomType)
    {
        return $query->where('room_type', $roomType);
    }

    /**
     * Scope to filter Day Tour rooms
     */
    public function scopeDayTour($query)
    {
        return $query->where('room_type', 'day_tour');
    }

    /**
     * Scope to filter Overnight rooms
     */
    public function scopeOvernight($query)
    {
        return $query->where('room_type', 'overnight');
    }

    /**
     * Check if this room is a Day Tour room
     */
    public function isDayTour(): bool
    {
        return $this->room_type === 'day_tour';
    }

    /**
     * Check if this room is an Overnight room
     */
    public function isOvernight(): bool
    {
        return $this->room_type === 'overnight';
    }

    /**
     * Check if a guest count is within the room's capacity range for Day Tour
     */
    public function canAccommodateDayTourGuests(int $guestCount): bool
    {
        if (!$this->isDayTour()) {
            return false;
        }

        $minGuests = $this->min_guests ?? 1;
        $maxGuests = $this->max_guests_range ?? $this->max_guests;

        return $guestCount >= $minGuests && $guestCount <= $maxGuests;
    }

    /**
     * Get the Day Tour price for a specific number of guests
     * This method will be updated to use the DayTourPricing model
     */
    public function getDayTourPrice(int $guestCount, ?Carbon $date = null): float
    {
        if (!$this->isDayTour()) {
            return 0.0;
        }

        // Get active pricing for the date (or current date if not provided)
        $pricing = \App\Models\DayTourPricing::getActivePricingForDate($date ?? Carbon::now());
        
        if (!$pricing) {
            return 0.0;
        }

        return (float) $pricing->price_per_pax * $guestCount;
    }

}
