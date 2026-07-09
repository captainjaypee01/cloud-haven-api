<?php

namespace App\DTO;

class RoomQuoteDTO
{
    /**
     * @param RoomNightDTO[] $nights
     */
    public function __construct(
        public array $nights,
        public float $totalRoom,
        public ?string $lockedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'nights' => array_map(fn ($night) => $night->toArray(), $this->nights),
            'total_room' => round($this->totalRoom, 2),
            'locked_at' => $this->lockedAt ?? now()->toIso8601String(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nights: array_map(
                fn (array $night) => RoomNightDTO::fromArray($night),
                $data['nights'] ?? []
            ),
            totalRoom: (float) ($data['total_room'] ?? 0),
            lockedAt: $data['locked_at'] ?? null,
        );
    }

    /**
     * @return array<string, float> slug => rate for a given date
     */
    public function getRatesForDate(string $date): array
    {
        foreach ($this->nights as $night) {
            if ($night->date === $date) {
                $rates = [];
                foreach ($night->rooms as $room) {
                    $rates[$room->slug] = $room->rate;
                }

                return $rates;
            }
        }

        return [];
    }

    /**
     * Per booking line totals aligned with bookingRoomArr / night->rooms order.
     *
     * @return array<int, float>
     */
    public function getLineTotalsByIndex(): array
    {
        $totals = [];

        foreach ($this->nights as $night) {
            foreach ($night->rooms as $index => $room) {
                $totals[$index] = ($totals[$index] ?? 0) + $room->rate;
            }
        }

        return $totals;
    }
}
