<?php

namespace App\DTO\DayTour;

class DayTourRoomAvailabilityDTO
{
    public function __construct(
        public string $roomId, // Changed to string (slug) to match overnight pattern
        public string $name,
        public string $roomType,
        public int $maxGuests,
        public int $extraGuests,
        public int $minGuests,
        public int $maxGuestsRange,
        public float $pricePerPax,
        public float $basePrice, // For backward compatibility
        public int $availableUnits,
        public int $pending = 0,
        public int $confirmed = 0,
        public int $maintenance = 0,
        public int $totalUnits = 0,
        public array $amenities = [],
        public array $images = []
    ) {}

    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'name' => $this->name,
            'room_type' => $this->roomType,
            'max_guests' => $this->maxGuests,
            'extra_guests' => $this->extraGuests,
            'min_guests' => $this->minGuests,
            'max_guests_range' => $this->maxGuestsRange,
            'price_per_pax' => round($this->pricePerPax, 2),
            'base_price' => round($this->basePrice, 2), // For backward compatibility
            'available_units' => $this->availableUnits,
            'pending' => $this->pending,
            'confirmed' => $this->confirmed,
            'maintenance' => $this->maintenance,
            'total_units' => $this->totalUnits,
            'amenities' => $this->amenities,
            'images' => $this->images,
        ];
    }
}
