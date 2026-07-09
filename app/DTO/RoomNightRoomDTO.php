<?php

namespace App\DTO;

class RoomNightRoomDTO
{
    public function __construct(
        public int $roomId,
        public string $slug,
        public float $rate,
    ) {}

    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'slug' => $this->slug,
            'rate' => round($this->rate, 2),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            roomId: (int) $data['room_id'],
            slug: $data['slug'],
            rate: (float) $data['rate'],
        );
    }
}
