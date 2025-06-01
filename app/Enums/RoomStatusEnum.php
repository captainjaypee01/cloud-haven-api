<?php

namespace App\Enums;

enum RoomStatusEnum: int
{
    case UNAVAILABLE = 0;
    case AVAILABLE = 1;
    case ARCHIVED = 2;
    
    // Convert enum to human-readable label
    public function label(): string
    {
        return match($this) {
            self::UNAVAILABLE => 'unavailable',
            self::AVAILABLE => 'available',
            self::ARCHIVED => 'archived',
        };
    }
    
    // Convert string to enum instance
    public static function fromLabel(string $label): self
    {
        return match(strtolower($label)) {
            'unavailable' => self::UNAVAILABLE,
            'available' => self::AVAILABLE,
            'archived' => self::ARCHIVED,
            default => throw new \InvalidArgumentException("Invalid status: $label"),
        };
    }
    
    // Get all valid string labels
    public static function labels(): array
    {
        return array_map(fn($case) => $case->label(), self::cases());
    }
}
