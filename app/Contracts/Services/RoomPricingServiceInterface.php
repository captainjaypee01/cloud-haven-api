<?php

namespace App\Contracts\Services;

use App\DTO\RoomQuoteDTO;
use App\Models\Room;
use Carbon\Carbon;

interface RoomPricingServiceInterface
{
    public function getRateForDate(Room $room, Carbon $date): float;

    /**
     * @param array<int, Room> $roomsBySlug slug => Room
     * @param array<object> $bookingRoomArr items with room_id (slug), adults, children
     */
    public function buildQuoteForStay(array $roomsBySlug, array $bookingRoomArr, string $checkIn, string $checkOut): RoomQuoteDTO;

    /**
     * @return array{updated: int, skipped: int}
     */
    public function bulkUpsertFlat(
        int $roomId,
        string $from,
        string $to,
        float $price,
        bool $skipManualOverrides = true
    ): array;

    /**
     * @param array<string, float|null> $weekdayPrices mon,tue,wed,thu,fri,sat,sun
     * @return array{updated: int, skipped: int}
     */
    public function bulkUpsertWeekdayPattern(
        int $roomId,
        string $from,
        string $to,
        array $weekdayPrices,
        bool $skipManualOverrides = true
    ): array;

    /**
     * @return array<int, array{date: string, price_per_night: float, is_manual_override: bool, is_default: bool}>
     */
    public function getCalendarMonth(int $roomId, string $month): array;

    /**
     * Preview bulk changes without persisting.
     *
     * @return array{would_update: int, would_skip: int, dates: array<int, array{date: string, price: float, action: string}>}
     */
    public function previewBulkChange(
        int $roomId,
        string $from,
        string $to,
        string $mode,
        float|array $payload,
        bool $skipManualOverrides = true
    ): array;
}
