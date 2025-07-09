<?php

namespace App\DTO\Rooms;

use Spatie\LaravelData\Data;

class NewRoom extends Data
{
    public function __construct(
        public string   $name,
        public ?string  $description,
        public int      $quantity,
        public int      $max_guests,
        public int      $extra_guests,
        public float    $extra_guest_fee,
        public bool     $allows_day_use,
        public string   $status,       // available|unavailable|archived
        public float    $base_weekday_rate,
        public float    $base_weekend_rate,
        public float    $price_per_night,
        public ?int     $is_featured = 0,
    ) {}
}
