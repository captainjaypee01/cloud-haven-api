<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BookingCancellationController extends Controller
{
    public function __construct(
        private BookingCancellationService $cancellationService
    ) {}

    /**
     * Cancel a booking manually
     */
    public function cancel(Request $request, Booking $booking)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'confirm_cancellation' => 'required|boolean|accepted'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Role check is handled by middleware, no need to check again here

        $result = $this->cancellationService->cancelBooking(
            $booking,
            $request->reason,
            Auth::id()
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error_code' => $result['error_code']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'booking' => $result['booking']
        ]);
    }

    /**
     * Get available cancellation reasons
     */
    public function getCancellationReasons()
    {
        $reasons = $this->cancellationService->getCancellationReasons();

        return response()->json([
            'success' => true,
            'reasons' => $reasons
        ]);
    }

    /**
     * Check if a booking can be cancelled
     */
    public function canCancel(Booking $booking)
    {
        $canCancel = $this->cancellationService->canCancel($booking);

        return response()->json([
            'success' => true,
            'can_cancel' => $canCancel,
            'booking_status' => $booking->status
        ]);
    }
}
