<?php

namespace App\Enums;

enum RoomUnitStatusEnum: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';
    case MAINTENANCE = 'maintenance';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::OCCUPIED => 'Currently Booked',
            self::MAINTENANCE => 'Under Maintenance',
            self::BLOCKED => 'Blocked',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match (strtolower($label)) {
            'available' => self::AVAILABLE,
            'occupied' => self::OCCUPIED,
            'under maintenance', 'maintenance' => self::MAINTENANCE,
            'blocked' => self::BLOCKED,
            default => throw new \ValueError("Invalid room unit status label: {$label}"),
        };
    }

    public function isBookable(): bool
    {
        return $this === self::AVAILABLE;
    }
}
