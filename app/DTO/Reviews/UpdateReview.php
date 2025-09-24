<?php

namespace App\DTO\Reviews;

use Spatie\LaravelData\Data;

class UpdateReview extends Data
{
    public function __construct(
        public ?int $booking_id,
        public ?int $user_id,
        public ?int $room_id,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $type,
        public ?int $rating,
        public ?string $comment,
        public ?bool $is_testimonial
    ) {}
}
