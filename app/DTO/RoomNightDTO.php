<?php

namespace App\DTO;

class RoomNightDTO
{
    /**
     * @param RoomNightRoomDTO[] $rooms
     */
    public function __construct(
        public string $date,
        public array $rooms,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'rooms' => array_map(fn ($room) => $room->toArray(), $this->rooms),
        ];
    }

    public function nightTotal(): float
    {
        return array_reduce(
            $this->rooms,
            fn (float $carry, RoomNightRoomDTO $room) => $carry + $room->rate,
            0.0
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            rooms: array_map(
                fn (array $room) => RoomNightRoomDTO::fromArray($room),
                $data['rooms'] ?? []
            ),
        );
    }
}
