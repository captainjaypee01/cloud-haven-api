<?php

namespace App\DTO\Reviews;

class ReviewDtoFactory
{
    public function newReview(array $data): NewReview
    {
        return new NewReview(
            booking_id: $data['booking_id'] ?? null,
            user_id: $data['user_id'] ?? null,
            room_id: $data['room_id'] ?? null,
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
            type: $data['type'],
            rating: $data['rating'],
            comment: $data['comment'],
            is_testimonial: $data['is_testimonial'] ?? false
        );
    }

    public function updateReview(array $data): UpdateReview
    {
        return new UpdateReview(
            booking_id: $data['booking_id'] ?? null,
            user_id: $data['user_id'] ?? null,
            room_id: $data['room_id'] ?? null,
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
            type: $data['type'] ?? null,
            rating: $data['rating'] ?? null,
            comment: $data['comment'] ?? null,
            is_testimonial: $data['is_testimonial'] ?? null
        );
    }
}
