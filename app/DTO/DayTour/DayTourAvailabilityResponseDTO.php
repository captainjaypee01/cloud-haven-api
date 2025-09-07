<?php

namespace App\DTO\DayTour;

class DayTourAvailabilityResponseDTO
{
    /**
     * @param string $date
     * @param bool $buffetActive
     * @param string $pmSnackPolicy (hidden|optional|required)
     * @param array|null $lunchPrices ['adult' => float, 'child' => float]
     * @param array|null $pmSnackPrices ['adult' => float, 'child' => float]
     * @param DayTourRoomAvailabilityDTO[] $rooms
     */
    public function __construct(
        public string $date,
        public bool $buffetActive,
        public string $pmSnackPolicy,
        public ?array $lunchPrices = null,
        public ?array $pmSnackPrices = null,
        public array $rooms = []
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'buffet_active' => $this->buffetActive,
            'pm_snack_policy' => $this->pmSnackPolicy,
            'lunch_prices' => $this->lunchPrices,
            'pm_snack_prices' => $this->pmSnackPrices,
            'rooms' => array_map(fn($room) => $room->toArray(), $this->rooms),
        ];
    }
}
