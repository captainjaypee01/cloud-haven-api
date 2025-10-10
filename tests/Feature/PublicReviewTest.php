<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\BookingRoom;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_booking_for_review_with_valid_token()
    {
        // Create a booking with review token
        $booking = Booking::factory()->create([
            'check_out_date' => now()->subDay()->toDateString(),
            'status' => 'paid',
            'is_reviewed' => false,
            'review_token' => 'test_token_123',
            'review_token_expires_at' => now()->addDays(30),
        ]);

        $room = Room::factory()->create();
        BookingRoom::factory()->create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
        ]);

        $response = $this->getJson('/api/v1/reviews/booking?token=test_token_123');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'booking' => [
                        'id',
                        'reference_number',
                        'guest_name',
                        'check_in_date',
                        'check_out_date',
                        'total_guests',
                    ],
                    'rooms' => [
                        '*' => [
                            'id',
                            'name',
                        ]
                    ]
                ]
            ]);
    }

    public function test_cannot_get_booking_with_invalid_token()
    {
        $response = $this->getJson('/api/v1/reviews/booking?token=invalid_token');

        $response->assertStatus(404);
    }

    public function test_cannot_get_booking_with_expired_token()
    {
        $booking = Booking::factory()->create([
            'review_token' => 'expired_token',
            'review_token_expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/reviews/booking?token=expired_token');

        $response->assertStatus(410);
    }

    public function test_can_submit_review_with_valid_token()
    {
        $booking = Booking::factory()->create([
            'check_out_date' => now()->subDay()->toDateString(),
            'status' => 'paid',
            'is_reviewed' => false,
            'review_token' => 'test_token_456',
            'review_token_expires_at' => now()->addDays(30),
        ]);

        $room = Room::factory()->create();
        BookingRoom::factory()->create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
        ]);

        $reviewData = [
            'token' => 'test_token_456',
            'reviews' => [
                [
                    'type' => 'resort',
                    'rating' => 5,
                    'comment' => 'Great stay!',
                ],
                [
                    'type' => 'room',
                    'room_id' => $room->id,
                    'rating' => 4,
                    'comment' => 'Nice room!',
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/reviews/submit', $reviewData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'reviews' => [
                    '*' => [
                        'id',
                        'booking_id',
                        'type',
                        'rating',
                        'comment',
                    ]
                ]
            ]);

        // Verify reviews were created
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'type' => 'resort',
            'rating' => 5,
        ]);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'type' => 'room',
            'room_id' => $room->id,
            'rating' => 4,
        ]);

        // Verify booking is marked as reviewed
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'is_reviewed' => true,
        ]);
    }

    public function test_cannot_submit_review_twice()
    {
        $booking = Booking::factory()->create([
            'check_out_date' => now()->subDay()->toDateString(),
            'status' => 'paid',
            'is_reviewed' => true, // Already reviewed
            'review_token' => 'test_token_789',
            'review_token_expires_at' => now()->addDays(30),
        ]);

        $reviewData = [
            'token' => 'test_token_789',
            'reviews' => [
                [
                    'type' => 'resort',
                    'rating' => 5,
                    'comment' => 'Great stay!',
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/reviews/submit', $reviewData);

        $response->assertStatus(400);
    }
}
