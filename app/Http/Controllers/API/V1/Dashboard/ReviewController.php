<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Review\ReviewResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function testimonials()  // for fetching reviews to display
    {
        // Example: fetch recent resort reviews for homepage
        $reviews = Review::with('user')
            ->where('type', 'resort')
            ->where('is_testimonial', true)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();
        return new CollectionResponse(ReviewResource::collection($reviews));
    }

    
    /**
     * Get booking details for review (public access with token)
     */
    public function getBookingForReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64'
        ]);

        if ($validator->fails()) {
            return new ErrorResponse('Invalid token format', 400);
        }

        $token = $request->input('token');
        
        $booking = Booking::with(['bookingRooms.room'])
            ->where('review_token', $token)
            ->first();

        if (!$booking) {
            return new ErrorResponse('Invalid or expired review link', 404);
        }

        // Check if booking already has reviews first
        $alreadyReviewed = Review::where('booking_id', $booking->id)->exists();
        
        // If already reviewed, we still want to return booking details for success page
        if ($alreadyReviewed) {
            // For already reviewed bookings, we only need basic validation
            if ($booking->review_token !== $token) {
                return new ErrorResponse('Invalid review link', 404);
            }
        } else {
            // For new reviews, do full validation
            if (!$booking->isReviewTokenValid($token)) {
                return new ErrorResponse('Review link has expired or has already been used', 410);
            }

            if (!$booking->isEligibleForReview()) {
                return new ErrorResponse('This booking is not eligible for review', 400);
            }
        }

        // Get unique rooms for review with room units
        $uniqueRooms = [];
        $booking->bookingRooms->each(function ($bookingRoom) use (&$uniqueRooms) {
            $roomKey = $bookingRoom->room->slug;
            $unitNumber = $bookingRoom->roomUnit?->unit_number;
            
            if (!isset($uniqueRooms[$roomKey])) {
                $uniqueRooms[$roomKey] = [
                    'slug' => $bookingRoom->room->slug,
                    'name' => $bookingRoom->room->name,
                    'units' => []
                ];
            }
            
            // Add unit information if available
            if ($unitNumber) {
                $uniqueRooms[$roomKey]['units'][] = $unitNumber;
            }
        });
        
        // Convert to array and sort units
        $uniqueRooms = collect($uniqueRooms)->map(function ($room) {
            $room['units'] = collect($room['units'])->unique()->sort()->values()->toArray();
            return $room;
        })->values()->toArray();

        return response()->json([
            'data' => [
                'booking' => [
                    'reference_number' => $booking->reference_number,
                    'guest_name' => $booking->guest_name,
                    'check_in_date' => $booking->check_in_date,
                    'check_out_date' => $booking->check_out_date,
                    'total_guests' => $booking->total_guests,
                ],
                'rooms' => $uniqueRooms,
                'already_reviewed' => $alreadyReviewed,
            ]
        ]);
    }

    /**
     * Submit review (public access with token)
     */
    public function submitReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:64',
            'reviews' => 'required|array|min:1',
            'reviews.*.type' => 'required|string|in:room,resort',
            'reviews.*.room_slug' => 'nullable|string',
            'reviews.*.rating' => 'required|integer|min:1|max:5',
            'reviews.*.comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return new ErrorResponse('Validation failed', 422, $validator->errors());
        }

        $token = $request->input('token');
        
        $booking = Booking::with(['bookingRooms.room'])
            ->where('review_token', $token)
            ->first();

        if (!$booking) {
            return new ErrorResponse('Invalid or expired review link', 404);
        }

        if (!$booking->isReviewTokenValid($token)) {
            return new ErrorResponse('Review link has expired or has already been used', 410);
        }

        if (!$booking->isEligibleForReview()) {
            return new ErrorResponse('This booking is not eligible for review', 400);
        }

        // Check if booking already has reviews
        if (Review::where('booking_id', $booking->id)->exists()) {
            return new ErrorResponse('This booking has already been reviewed', 400);
        }

        $reviews = $request->input('reviews');

        try {
            foreach ($reviews as $reviewData) {
                $roomId = null;
                
                // Validate room_slug for room reviews
                if ($reviewData['type'] === 'room') {
                    $roomSlug = $reviewData['room_slug'] ?? null;
                    if (!$roomSlug) {
                        return new ErrorResponse('Room slug is required for room reviews', 422);
                    }
                    
                    // Find the room by slug from booked rooms
                    $bookedRoom = $booking->bookingRooms->first(function ($bookingRoom) use ($roomSlug) {
                        return $bookingRoom->room->slug === $roomSlug;
                    });
                    
                    if (!$bookedRoom) {
                        return new ErrorResponse('Invalid room for this booking', 422);
                    }
                    
                    $roomId = $bookedRoom->room_id;
                }

                // Create review
                Review::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id, // May be null for guest bookings
                    'room_id' => $roomId,
                    'first_name' => $booking->guest_name ? explode(' ', $booking->guest_name)[0] : null,
                    'last_name' => $booking->guest_name ? implode(' ', array_slice(explode(' ', $booking->guest_name), 1)) : null,
                    'type' => $reviewData['type'],
                    'rating' => $reviewData['rating'],
                    'comment' => $reviewData['comment'] ?? '',
                    'is_testimonial' => false,
                ]);
            }

            // Mark booking as reviewed and token as used
            $booking->update(['is_reviewed' => true]);
            $booking->markReviewTokenAsUsed();

            return response()->json([
                'message' => 'Thank you for your review!',
            ], 201);

        } catch (\Exception $e) {
            return new ErrorResponse('Failed to submit review. Please try again.', 500);
        }
    }
}
