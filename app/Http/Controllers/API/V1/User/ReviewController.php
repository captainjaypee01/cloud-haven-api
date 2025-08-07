<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'booking_id'         => 'required|exists:bookings,reference_number',
            'reviews'            => 'required|array|min:1',
            'reviews.*.type'     => 'required|string|in:room,resort',
            'reviews.*.room_id'  => 'nullable|integer',  // required if type == 'room'
            'reviews.*.rating'   => 'required|integer|min:1|max:5',
            'reviews.*.comment'  => 'nullable|string'
        ]);

        $booking = Booking::with('bookingRooms.room')->where('reference_number', $data['booking_id'])->firstOrFail();
        // Authorization: booking must belong to this user
        if ($booking->user_id !== $user->id) {
            return new ErrorResponse('Unauthorized to review this booking', 403);
        }
        // Ensure booking is completed (e.g., check-out date passed and fully paid)
        $today = now()->toDateString();
        if ($booking->check_out_date > $today || !in_array($booking->status, ['paid', 'downpayment'])) {
            return new ErrorResponse('Cannot review booking before stay is completed', 400);
        }

        $isBookingReviewDone = Review::where('booking_id', $booking->id)->exists();
        if($isBookingReviewDone){
            return new ErrorResponse('The booking has already been reviewed.', 400);
        }
        // Prevent duplicate reviews for same booking
        // (Could check if any review exists for this booking by this user)
        // ...

        // Process each review entry
        $createdReviews = [];
        foreach ($data['reviews'] as $rev) {
            // If it's a room review, ensure that room_id was in this booking
            if ($rev['type'] === 'room') {
                $roomId = $rev['room_id'] ?? null;
                $bookedRoomIds = $booking->bookingRooms->pluck('room_id')->unique();
                if (!$roomId || !$bookedRoomIds->contains($roomId)) {
                    return new ErrorResponse('Invalid room for this booking', 422);
                }
            }
            // Create review
            $createdReviews[] = Review::create([
                'booking_id' => $booking->id,
                'user_id'    => $user->id,
                'room_id'    => $rev['type'] === 'room' ? $rev['room_id'] : null,
                'type'       => $rev['type'],
                'rating'     => $rev['rating'],
                'comment'    => $rev['comment'] ?? ''
            ]);
        }

        $booking->update(['is_reviewed' => true]);
        return response()->json($createdReviews, 200);
    }

}
