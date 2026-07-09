<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\RoomPricingServiceInterface;
use App\DTO\RoomQuoteDTO;
use App\Models\Room;
use InvalidArgumentException;

class ComputeOvernightQuoteAction
{
    public function __construct(
        private readonly RoomPricingServiceInterface $roomPricingService,
    ) {}

    public function execute(string $checkIn, string $checkOut, array $rooms): RoomQuoteDTO
    {
        if (empty($rooms)) {
            throw new InvalidArgumentException('At least one room is required.');
        }

        $slugs = array_unique(array_map(fn ($r) => $r['room_id'] ?? $r->room_id ?? null, $rooms));
        $roomModels = Room::whereIn('slug', $slugs)->get()->keyBy('slug');

        if ($roomModels->count() !== count($slugs)) {
            throw new InvalidArgumentException('One or more rooms were not found.');
        }

        $bookingRoomArr = array_map(function ($room) {
            if (is_object($room)) {
                return $room;
            }

            return (object) $room;
        }, $rooms);

        return $this->roomPricingService->buildQuoteForStay(
            $roomModels->all(),
            $bookingRoomArr,
            $checkIn,
            $checkOut
        );
    }
}
