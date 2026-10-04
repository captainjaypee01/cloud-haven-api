<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Bookings\ReactivateBookingAction;
use App\Actions\Bookings\ResendBookingEmailAction;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\BookingResource;
use App\Models\Booking;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BookingCancellationController extends Controller
{
    public function __construct(
        private BookingCancellationService $cancellationService,
        private ReactivateBookingAction $reactivateBookingAction,
        private ResendBookingEmailAction $resendBookingEmailAction,
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
            Log::warning('Booking cancellation validation failed', [
                'admin_user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'validation_errors' => $validator->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        Log::info('Admin cancelling booking', [
            'admin_user_id' => Auth::id(),
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_number,
            'booking_status' => $booking->status,
            'cancellation_reason' => $request->reason,
            'guest_email' => $booking->guest_email
        ]);

        // Role check is handled by middleware, no need to check again here

        $result = $this->cancellationService->cancelBooking(
            $booking,
            $request->reason,
            Auth::id()
        );

        if (!$result['success']) {
            Log::error('Booking cancellation failed', [
                'admin_user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'error_code' => $result['error_code'],
                'error_message' => $result['message']
            ]);
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error_code' => $result['error_code']
            ], 400);
        }

        Log::info('Booking cancelled successfully', [
            'admin_user_id' => Auth::id(),
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_number,
            'cancellation_reason' => $request->reason,
            'guest_email' => $booking->guest_email
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'booking' => $result['booking']
        ]);
    }

    /**
     * Delete a booking (soft delete + cancel status)
     */
    public function delete(Request $request, Booking $booking)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'confirm_deletion' => 'required|boolean|accepted'
        ]);

        if ($validator->fails()) {
            Log::warning('Booking deletion validation failed', [
                'admin_user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'validation_errors' => $validator->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        Log::info('Admin deleting booking', [
            'admin_user_id' => Auth::id(),
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_number,
            'booking_status' => $booking->status,
            'deletion_reason' => $request->reason,
            'guest_email' => $booking->guest_email
        ]);

        // Role check is handled by middleware, no need to check again here

        $result = $this->cancellationService->deleteBooking(
            $booking,
            $request->reason,
            Auth::id()
        );

        if (!$result['success']) {
            Log::error('Booking deletion failed', [
                'admin_user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'error_code' => $result['error_code'],
                'error_message' => $result['message']
            ]);
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error_code' => $result['error_code']
            ], 400);
        }

        Log::info('Booking deleted successfully', [
            'admin_user_id' => Auth::id(),
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_number,
            'deletion_reason' => $request->reason,
            'guest_email' => $booking->guest_email
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'booking' => $result['booking']
        ]);
    }

    /**
     * Reactivate an expired booking (or extend a pending booking's hold) instead of re-keying it
     */
    public function reactivate(Request $request, Booking $booking)
    {
        $maxHoldHours = config('booking.reactivation_max_hold_hours', 72);

        $validator = Validator::make($request->all(), [
            'hold_hours' => "sometimes|integer|min:1|max:{$maxHoldHours}",
            'notify_guest' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $holdHours = (int) $request->input('hold_hours', config('booking.reservation_hold_duration_hours', 2));

        Log::info('Admin reactivating booking', [
            'admin_user_id' => Auth::id(),
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_number,
            'booking_status' => $booking->status,
            'hold_hours' => $holdHours,
        ]);

        try {
            $booking = $this->reactivateBookingAction->execute($booking, $holdHours, Auth::id());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'cannot_reactivate'
            ], 422);
        } catch (RoomNotAvailableException $e) {
            Log::warning('Booking reactivation rejected - room not available', [
                'admin_user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'One or more rooms are no longer available for these dates. Please reschedule or create a new booking.',
                'error_code' => 'room_not_available'
            ], 422);
        }

        if ($request->boolean('notify_guest')) {
            try {
                $this->resendBookingEmailAction->execute($booking, 'reservation');
            } catch (\Exception $e) {
                // Don't fail the reactivation if the email cannot be sent
                Log::warning('Failed to send reservation email after reactivation', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Booking reactivated. Hold extended until {$booking->local_reserved_until}.",
            'booking' => new BookingResource($booking)
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
