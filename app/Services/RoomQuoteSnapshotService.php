<?php

namespace App\Services;

use App\Contracts\Services\RoomPricingServiceInterface;
use App\DTO\RoomNightDTO;
use App\DTO\RoomNightRoomDTO;
use App\DTO\RoomQuoteDTO;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class RoomQuoteSnapshotService
{
    public function __construct(
        private readonly RoomPricingServiceInterface $roomPricingService,
    ) {}

    public function parseSnapshot(Booking|array|null $data): ?RoomQuoteDTO
    {
        if ($data instanceof Booking) {
            $raw = $data->room_quote_data;
        } else {
            $raw = $data;
        }

        if (empty($raw)) {
            return null;
        }

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw) || empty($raw['nights'])) {
            return null;
        }

        return RoomQuoteDTO::fromArray($raw);
    }

    /**
     * Rebuild quote for a modified stay: snapshot rates for nights in the original quote,
     * live calendar rates for any new nights outside the snapshot.
     *
     * @param array<int, Room> $roomsBySlug
     * @param array<object|array> $bookingRoomArr
     */
    public function rebuildFromSnapshot(
        RoomQuoteDTO $snapshot,
        array $roomsBySlug,
        array $bookingRoomArr,
        string $checkIn,
        string $checkOut
    ): RoomQuoteDTO {
        $snapshotRatesByDate = [];
        foreach ($snapshot->nights as $night) {
            foreach ($night->rooms as $room) {
                $snapshotRatesByDate[$night->date][$room->slug] = $room->rate;
            }
        }

        $nights = [];
        $totalRoom = 0.0;
        $period = CarbonPeriod::create(
            Carbon::parse($checkIn)->startOfDay(),
            Carbon::parse($checkOut)->startOfDay()->subDay()
        );

        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $nightRooms = [];

            foreach ($bookingRoomArr as $roomData) {
                $slug = is_object($roomData) ? $roomData->room_id : $roomData['room_id'];
                $room = $roomsBySlug[$slug] ?? null;

                if (! $room) {
                    continue;
                }

                if (isset($snapshotRatesByDate[$dateKey][$slug])) {
                    $rate = (float) $snapshotRatesByDate[$dateKey][$slug];
                } else {
                    $rate = $this->roomPricingService->getRateForDate($room, $date);
                }

                $nightRooms[] = new RoomNightRoomDTO($room->id, $room->slug, $rate);
                $totalRoom += $rate;
            }

            $nights[] = new RoomNightDTO($dateKey, $nightRooms);
        }

        return new RoomQuoteDTO($nights, round($totalRoom, 2), $snapshot->lockedAt);
    }

    /**
     * Locked snapshot rates for dates present in the original quote; live calendar for new dates.
     *
     * @param array<int, Room> $roomsBySlug
     * @param array<object|array> $bookingRoomArr
     */
    public function buildForStay(
        ?RoomQuoteDTO $snapshot,
        array $roomsBySlug,
        array $bookingRoomArr,
        string $checkIn,
        string $checkOut,
    ): RoomQuoteDTO {
        if ($snapshot) {
            return $this->rebuildFromSnapshot($snapshot, $roomsBySlug, $bookingRoomArr, $checkIn, $checkOut);
        }

        return $this->roomPricingService->buildQuoteForStay($roomsBySlug, $bookingRoomArr, $checkIn, $checkOut);
    }
}
