<?php

namespace App\Services;

use App\Contracts\Repositories\RoomDailyRateRepositoryInterface;
use App\Contracts\Services\RoomPricingServiceInterface;
use App\DTO\RoomNightDTO;
use App\DTO\RoomNightRoomDTO;
use App\DTO\RoomQuoteDTO;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

class RoomPricingService implements RoomPricingServiceInterface
{
    private const WEEKDAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __construct(
        private readonly RoomDailyRateRepositoryInterface $dailyRateRepository,
    ) {}

    public function getRateForDate(Room $room, Carbon $date): float
    {
        $dailyRate = $this->dailyRateRepository->findForRoomAndDate($room->id, $date);

        if ($dailyRate) {
            return (float) $dailyRate->price_per_night;
        }

        return (float) $room->price_per_night;
    }

    public function buildQuoteForStay(array $roomsBySlug, array $bookingRoomArr, string $checkIn, string $checkOut): RoomQuoteDTO
    {
        $checkInDate = Carbon::parse($checkIn)->startOfDay();
        $checkOutDate = Carbon::parse($checkOut)->startOfDay();

        if ($checkOutDate->lte($checkInDate)) {
            throw new InvalidArgumentException('Check-out date must be after check-in date.');
        }

        $nights = [];
        $totalRoom = 0.0;

        $period = CarbonPeriod::create($checkInDate, $checkOutDate->copy()->subDay());

        foreach ($period as $date) {
            $nightRooms = [];

            foreach ($bookingRoomArr as $roomData) {
                $slug = is_object($roomData) ? $roomData->room_id : $roomData['room_id'];
                $room = $roomsBySlug[$slug] ?? null;

                if (! $room) {
                    continue;
                }

                $rate = $this->getRateForDate($room, $date);
                $nightRooms[] = new RoomNightRoomDTO($room->id, $room->slug, $rate);
                $totalRoom += $rate;
            }

            $nights[] = new RoomNightDTO($date->format('Y-m-d'), $nightRooms);
        }

        return new RoomQuoteDTO($nights, round($totalRoom, 2));
    }

    public function bulkUpsertFlat(
        int $roomId,
        string $from,
        string $to,
        float $price,
        bool $skipManualOverrides = true
    ): array {
        return $this->applyBulkChange($roomId, $from, $to, 'flat', $price, $skipManualOverrides);
    }

    public function bulkUpsertWeekdayPattern(
        int $roomId,
        string $from,
        string $to,
        array $weekdayPrices,
        bool $skipManualOverrides = true
    ): array {
        return $this->applyBulkChange($roomId, $from, $to, 'weekday_pattern', $weekdayPrices, $skipManualOverrides);
    }

    public function getCalendarMonth(int $roomId, string $month): array
    {
        $room = Room::findOrFail($roomId);
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rates = $this->dailyRateRepository
            ->getForRoomInRange($roomId, $start, $end)
            ->keyBy(fn ($rate) => $rate->date->format('Y-m-d'));

        $days = [];
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $dateKey = $date->format('Y-m-d');
            $rate = $rates->get($dateKey);

            if ($rate) {
                $days[] = [
                    'date' => $dateKey,
                    'price_per_night' => (float) $rate->price_per_night,
                    'is_manual_override' => (bool) $rate->is_manual_override,
                    'is_default' => false,
                ];
            } else {
                $days[] = [
                    'date' => $dateKey,
                    'price_per_night' => (float) $room->price_per_night,
                    'is_manual_override' => false,
                    'is_default' => true,
                ];
            }
        }

        return $days;
    }

    public function previewBulkChange(
        int $roomId,
        string $from,
        string $to,
        string $mode,
        float|array $payload,
        bool $skipManualOverrides = true
    ): array {
        $room = Room::findOrFail($roomId);
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->startOfDay();

        if ($toDate->lt($fromDate)) {
            throw new InvalidArgumentException('End date must be on or after start date.');
        }

        $existing = $this->dailyRateRepository
            ->getForRoomInRange($roomId, $fromDate, $toDate)
            ->keyBy(fn ($rate) => $rate->date->format('Y-m-d'));

        $wouldUpdate = 0;
        $wouldSkip = 0;
        $dates = [];

        foreach (CarbonPeriod::create($fromDate, $toDate) as $date) {
            $dateKey = $date->format('Y-m-d');
            $existingRate = $existing->get($dateKey);

            if ($skipManualOverrides && $existingRate?->is_manual_override) {
                $wouldSkip++;
                $dates[] = [
                    'date' => $dateKey,
                    'price' => (float) $existingRate->price_per_night,
                    'action' => 'skip',
                ];
                continue;
            }

            $price = $mode === 'flat'
                ? (float) $payload
                : $this->resolveWeekdayPrice($date, (array) $payload, (float) $room->price_per_night);

            $wouldUpdate++;
            $dates[] = [
                'date' => $dateKey,
                'price' => $price,
                'action' => 'update',
            ];
        }

        return [
            'would_update' => $wouldUpdate,
            'would_skip' => $wouldSkip,
            'dates' => $dates,
        ];
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    private function applyBulkChange(
        int $roomId,
        string $from,
        string $to,
        string $mode,
        float|array $payload,
        bool $skipManualOverrides
    ): array {
        $preview = $this->previewBulkChange($roomId, $from, $to, $mode, $payload, $skipManualOverrides);

        foreach ($preview['dates'] as $day) {
            if ($day['action'] !== 'update') {
                continue;
            }

            $this->dailyRateRepository->upsert(
                $roomId,
                Carbon::parse($day['date']),
                $day['price'],
                false
            );
        }

        return [
            'updated' => $preview['would_update'],
            'skipped' => $preview['would_skip'],
        ];
    }

    /**
     * @param array<string, float|null> $weekdayPrices
     */
    private function resolveWeekdayPrice(Carbon $date, array $weekdayPrices, float $fallback): float
    {
        $key = strtolower($date->format('D'));
        $key = match ($key) {
            'sun' => 'sun',
            'mon' => 'mon',
            'tue' => 'tue',
            'wed' => 'wed',
            'thu' => 'thu',
            'fri' => 'fri',
            'sat' => 'sat',
            default => 'mon',
        };

        if (isset($weekdayPrices[$key]) && $weekdayPrices[$key] !== null && $weekdayPrices[$key] !== '') {
            return (float) $weekdayPrices[$key];
        }

        return $fallback;
    }
}
