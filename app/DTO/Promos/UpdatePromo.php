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
        public ?string $expires_at,
        public ?int    $max_uses,
        public ?string $image_url,
        public bool    $exclusive = false,
        public bool    $active = false,
        // Note: We exclude 'uses_count' and 'active' here to manage usage count internally and handle activation via separate endpoint.
    ) {}
}
