<?php

namespace App\DTO\Promos;

use Spatie\LaravelData\Data;

class UpdatePromo extends Data
{
    public function __construct(
        public string  $code,
        public string  $title,
        public ?string $description,
        public ?string $scope,
        public string  $discount_type,
        public float   $discount_value,
        public ?string $starts_at,            // nullable datetime string (Y-m-d H:i:s)
        public ?string $ends_at,              // nullable datetime string (Y-m-d H:i:s)
        public ?string $expires_at,
        public ?int    $max_uses,
        public ?string $image_url,
        public bool    $exclusive = false,
        public bool    $active = false,
        public ?array  $excluded_days = null, // array of day numbers to exclude (0=Sunday, 1=Monday, ..., 6=Saturday)
        public bool    $per_night_calculation = false, // whether to apply discount per night vs entire booking
        // Note: We exclude 'uses_count' and 'active' here to manage usage count internally and handle activation via separate endpoint.
    ) {}
}
