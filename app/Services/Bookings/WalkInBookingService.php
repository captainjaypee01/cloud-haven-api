<?php

namespace App\Services\Bookings;

use App\Actions\DayTour\CreateDayTourBookingAction;
use App\Contracts\Services\BookingServiceInterface;
use App\DTO\Bookings\BookingData;
use App\DTO\DayTour\DayTourBookingRequestDTO;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalkInBookingService
{
    public function __construct(
        private BookingServiceInterface $bookingService,
        private CreateDayTourBookingAction $createDayTourBookingAction
    ) {}

    /**
     * Create a walk-in booking (overnight or day tour)
     */
    public function createWalkInBooking(BookingData $bookingData): Booking
    {
        $adminUserId = Auth::id();
        
        Log::info('Starting walk-in booking creation process', [
            'guest_email' => $bookingData->guest_email,
            'guest_name' => $bookingData->guest_name,
            'booking_type' => $bookingData->booking_type,
            'check_in_date' => $bookingData->check_in_date,
            'check_out_date' => $bookingData->check_out_date,
            'total_adults' => $bookingData->total_adults,
            'total_children' => $bookingData->total_children,
            'admin_user_id' => $adminUserId,
            'room_count' => count($bookingData->rooms)
        ]);

        // Validate walk-in booking constraints
        $this->validateWalkInBookingConstraints($bookingData);

        return DB::transaction(function () use ($bookingData, $adminUserId) {
            if ($bookingData->booking_type === 'day_tour') {
                return $this->createDayTourWalkInBooking($bookingData, $adminUserId);
            } else {
                return $this->createOvernightWalkInBooking($bookingData, $adminUserId);
            }
        });
    }

    /**
     * Validate walk-in booking constraints
     */
    private function validateWalkInBookingConstraints(BookingData $bookingData): void
    {
        $today = Carbon::today()->format('Y-m-d');
        
        if ($bookingData->booking_type === 'day_tour') {
            // Day tour: date must be today
            if ($bookingData->check_in_date !== $today) {
                throw new \InvalidArgumentException('Day tour walk-in bookings can only be made for today.');
            }
        } else {
            // Overnight: check-in must be today, maximum 5 nights
            if ($bookingData->check_in_date !== $today) {
                throw new \InvalidArgumentException('Overnight walk-in bookings can only start today.');
            }
            
            $checkInDate = Carbon::parse($bookingData->check_in_date);
            $checkOutDate = Carbon::parse($bookingData->check_out_date);
            $nights = $checkInDate->diffInDays($checkOutDate);
            
            if ($nights > 5) {
                throw new \InvalidArgumentException('Overnight walk-in bookings cannot exceed 5 nights.');
            }
        }
    }

    /**
     * Create overnight walk-in booking using existing BookingService
     */
    private function createOvernightWalkInBooking(BookingData $bookingData, ?int $adminUserId = null): Booking
    {
        Log::info('Creating overnight walk-in booking', [
            'guest_name' => $bookingData->guest_name,
            'check_in_date' => $bookingData->check_in_date,
            'check_out_date' => $bookingData->check_out_date,
            'admin_user_id' => $adminUserId
        ]);

        // Use existing BookingService but pass null for user_id (walk-in booking)
        $booking = $this->bookingService->createBooking($bookingData, null);
        
        // Update booking source to 'walkin' after creation
        $booking->update(['booking_source' => 'walkin']);
        
        Log::info('Overnight walk-in booking created successfully', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'booking_source' => 'walkin'
        ]);

        return $booking;
    }

    /**
     * Create day tour walk-in booking using existing CreateDayTourBookingAction
     */
    private function createDayTourWalkInBooking(BookingData $bookingData, ?int $adminUserId = null): Booking
    {
        Log::info('Creating day tour walk-in booking', [
            'guest_name' => $bookingData->guest_name,
            'date' => $bookingData->check_in_date,
            'admin_user_id' => $adminUserId
        ]);

        // Convert BookingData to DayTourBookingRequestDTO format
        $dayTourRequest = $this->convertToDayTourRequest($bookingData);
        
        // Use existing CreateDayTourBookingAction but pass null for user_id (walk-in booking)
        $booking = $this->createDayTourBookingAction->execute($dayTourRequest, null);
        
        // Update booking source to 'walkin' after creation
        $booking->update(['booking_source' => 'walkin']);
        
        Log::info('Day tour walk-in booking created successfully', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'booking_source' => 'walkin'
        ]);

        return $booking;
    }

    /**
     * Convert BookingData to DayTourBookingRequestDTO format
     */
    private function convertToDayTourRequest(BookingData $bookingData): DayTourBookingRequestDTO
    {
        // Convert rooms array to selections format expected by DayTourBookingRequestDTO
        $selections = [];
        foreach ($bookingData->rooms as $room) {
            $selections[] = [
                'room_id' => $room['room_id'],
                'adults' => $room['adults'],
                'children' => $room['children'],
                'include_lunch' => $room['include_lunch'] ?? false,
                'include_pm_snack' => $room['include_pm_snack'] ?? false,
                'lunch_cost' => $room['lunch_cost'] ?? 0,
                'pm_snack_cost' => $room['pm_snack_cost'] ?? 0,
                'meal_cost' => $room['meal_cost'] ?? 0,
            ];
        }

        // Create guest object
        $guest = [
            'name' => $bookingData->guest_name,
            'email' => $bookingData->guest_email,
            'phone' => $bookingData->guest_phone,
        ];

        // Create totals object (if needed)
        $totals = [
            'room_total' => 0, // Will be calculated by the action
            'meal_total' => 0, // Will be calculated by the action
            'grand_total' => 0, // Will be calculated by the action
        ];

        return DayTourBookingRequestDTO::from([
            'date' => $bookingData->check_in_date,
            'selections' => $selections,
            'guest' => $guest,
            'special_requests' => $bookingData->special_requests,
            'totals' => $totals,
            'promo_id' => $bookingData->promo_id,
        ]);
    }
}
