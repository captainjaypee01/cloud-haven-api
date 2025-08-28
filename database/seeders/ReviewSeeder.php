<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Review;

use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Production safety check
        if (app()->environment('production')) {
            $this->command->warn('Skipping ReviewSeeder in production environment');
            return;
        }

        $this->command->info('Starting ReviewSeeder for DEV/UAT environment...');

        $this->createReviews();

        $this->command->info('ReviewSeeder completed successfully!');
    }

    private function createReviews(): void
    {
        // Find eligible bookings: status='paid', check_out_date <= '2025-10-31', user_id NOT NULL
        $eligibleBookings = Booking::where('status', 'paid')
            ->where('check_out_date', '<=', '2025-10-31')
            ->whereNotNull('user_id')
            ->where('is_reviewed', false)
            ->with(['bookingRooms.room', 'user'])
            ->get();

        $this->command->info('Found ' . $eligibleBookings->count() . ' eligible bookings for reviews');

        $reviewsCreated = 0;

        foreach ($eligibleBookings as $booking) {
            $reviewsForBooking = 0;

            // Always create a resort review
            $resortReview = $this->createResortReview($booking);
            if ($resortReview) {
                $reviewsForBooking++;
                $reviewsCreated++;
            }

            // Optionally create a room review (40-60% chance)
            if (fake()->boolean(50)) {
                $roomReview = $this->createRoomReview($booking);
                if ($roomReview) {
                    $reviewsForBooking++;
                    $reviewsCreated++;
                }
            }

            // Mark booking as reviewed if we created any reviews
            if ($reviewsForBooking > 0) {
                $booking->update(['is_reviewed' => true]);
            }

            if ($reviewsCreated % 50 == 0 && $reviewsCreated > 0) {
                $this->command->info("Created $reviewsCreated reviews...");
            }
        }

        $this->command->info("Successfully created $reviewsCreated reviews for " . $eligibleBookings->count() . " bookings");
        $this->createTestimonials();
    }

    private function createResortReview(Booking $booking): ?Review
    {
        // Check if resort review already exists
        $existingReview = Review::where('booking_id', $booking->id)
            ->where('type', 'resort')
            ->where('room_id', null)
            ->first();

        if ($existingReview) {
            return null; // Already exists
        }

        $rating = $this->generateRating();
        $comment = $this->generateResortComment($rating);

        try {
            return Review::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'room_id' => null,
                'type' => 'resort',
                'rating' => $rating,
                'comment' => $comment,
                'is_testimonial' => false, // Will be updated later for some reviews
            ]);
        } catch (\Exception $e) {
            $this->command->warn('Failed to create resort review for booking ' . $booking->id . ': ' . $e->getMessage());
            return null;
        }
    }

    private function createRoomReview(Booking $booking): ?Review
    {
        // Get a random room from the booking's rooms
        $bookingRooms = $booking->bookingRooms;
        
        if ($bookingRooms->isEmpty()) {
            return null;
        }

        $selectedBookingRoom = $bookingRooms->random();
        $roomId = $selectedBookingRoom->room_id;

        // Check if room review already exists for this room
        $existingReview = Review::where('booking_id', $booking->id)
            ->where('type', 'room')
            ->where('room_id', $roomId)
            ->first();

        if ($existingReview) {
            return null; // Already exists
        }

        $rating = $this->generateRating();
        $comment = $this->generateRoomComment($rating, $selectedBookingRoom->room);

        try {
            return Review::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'room_id' => $roomId,
                'type' => 'room',
                'rating' => $rating,
                'comment' => $comment,
                'is_testimonial' => false, // Will be updated later for some reviews
            ]);
        } catch (\Exception $e) {
            $this->command->warn('Failed to create room review for booking ' . $booking->id . ', room ' . $roomId . ': ' . $e->getMessage());
            return null;
        }
    }

    private function generateRating(): int
    {
        // Ratings distribution: 5★ (55%), 4★ (30%), 3★ (10%), 2★ (4%), 1★ (1%)
        $rand = fake()->randomFloat(null, 0, 100);
        
        if ($rand <= 55) return 5;
        if ($rand <= 85) return 4;
        if ($rand <= 95) return 3;
        if ($rand <= 99) return 2;
        return 1;
    }

    private function generateResortComment(int $rating): string
    {
        $positiveComments = [
            "Amazing resort with beautiful facilities! The staff was incredibly friendly and the pool area was pristine.",
            "Had a wonderful stay at this resort. The location is perfect and the amenities exceeded our expectations.",
            "Fantastic experience! The resort is well-maintained and the atmosphere is very relaxing.",
            "Great place for a family vacation. Clean, comfortable, and the staff went above and beyond.",
            "Beautiful resort with excellent service. We enjoyed every moment of our stay.",
            "Perfect getaway destination! The resort has everything you need for a relaxing vacation.",
            "Outstanding resort with top-notch facilities. Highly recommend for anyone looking for a peaceful retreat.",
            "Lovely resort with breathtaking views. The staff was attentive and the food was delicious.",
            "Exceptional service and beautiful surroundings. This resort truly offers a premium experience.",
            "Wonderful resort that exceeded all our expectations. Can't wait to come back!",
        ];

        $neutralComments = [
            "Decent resort with good facilities. Some areas could use improvement but overall a pleasant stay.",
            "Nice place to stay with adequate amenities. The location is good and staff is helpful.",
            "Average resort experience. Clean rooms and friendly staff, though nothing particularly stands out.",
            "Good value for money. The resort meets basic expectations with room for improvement.",
            "Pleasant stay overall. Some minor issues but the staff was quick to address them.",
        ];

        $negativeComments = [
            "The resort needs significant improvements. Several facilities were not working properly.",
            "Disappointing experience. The room was not as advertised and service was lacking.",
            "Poor maintenance and outdated facilities. Expected much better for the price paid.",
            "Unsatisfactory stay. Multiple issues with room cleanliness and staff responsiveness.",
        ];

        return match ($rating) {
            5, 4 => fake()->randomElement($positiveComments),
            3 => fake()->randomElement($neutralComments),
            2, 1 => fake()->randomElement($negativeComments),
        };
    }

    private function generateRoomComment(int $rating, $room): string
    {
        $roomName = $room->name ?? 'Room';
        
        $positiveComments = [
            "The $roomName was spacious and beautifully decorated. Very comfortable beds and excellent amenities.",
            "Loved our $roomName! Great views and all the amenities we needed for a comfortable stay.",
            "Perfect room with excellent facilities. The $roomName exceeded our expectations in every way.",
            "Beautiful $roomName with modern amenities. The room was clean and very well-maintained.",
            "Outstanding room quality! The $roomName provided everything we needed for a perfect vacation.",
            "Fantastic $roomName with amazing views. Comfortable, clean, and well-equipped.",
            "The $roomName was absolutely perfect. Great space, comfortable furnishings, and excellent service.",
            "Wonderful room experience! The $roomName had all the amenities and more.",
        ];

        $neutralComments = [
            "The $roomName was decent with basic amenities. Clean and comfortable but nothing extraordinary.",
            "Good room overall. The $roomName met our expectations with adequate facilities.",
            "Nice $roomName with standard amenities. Some minor issues but generally satisfactory.",
            "Average room experience. The $roomName was clean but could use some updates.",
        ];

        $negativeComments = [
            "The $roomName was disappointing. Several amenities were not working and maintenance was poor.",
            "Poor room condition. The $roomName had cleanliness issues and outdated facilities.",
            "Unsatisfactory room experience. The $roomName did not meet basic expectations.",
        ];

        return match ($rating) {
            5, 4 => fake()->randomElement($positiveComments),
            3 => fake()->randomElement($neutralComments),
            2, 1 => fake()->randomElement($negativeComments),
        };
    }

    private function createTestimonials(): void
    {
        // Convert 10-15% of 5-star reviews to testimonials
        $highRatingReviews = Review::where('rating', 5)
            ->where('is_testimonial', false)
            ->inRandomOrder()
            ->limit(50) // Maximum 50 testimonials
            ->get();

        $testimonialsCreated = 0;
        
        foreach ($highRatingReviews as $review) {
            if (fake()->boolean(15)) { // 15% chance
                $review->update(['is_testimonial' => true]);
                $testimonialsCreated++;
            }
        }

        $this->command->info("Created $testimonialsCreated testimonials from high-rating reviews");
    }
}
