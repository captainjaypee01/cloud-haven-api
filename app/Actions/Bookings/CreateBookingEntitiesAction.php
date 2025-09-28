<?php

namespace App\Actions\Bookings;

use App\DTO\Bookings\BookingData;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Promo;
use App\Models\Room;
use App\Services\RoomUnitService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateBookingEntitiesAction
{
    public function __construct(
        private readonly RoomUnitService $roomUnitService
    ) {}

    public function execute(BookingData $bookingData, array $roomDataArr, $userId, $totals): Booking
    {
        // Prevent mixing room types
        $this->validateRoomTypes($roomDataArr);
        
        // Extract discount from totals (calculated in CalculateBookingTotalAction)
        $discount = 0;
        $promoDiscountData = null;
        
        if (isset($totals['promo_discount']) && $totals['promo_discount']) {
            // Use the promo discount calculated by CalculateBookingTotalAction
            $promoDiscountData = $totals['promo_discount'];
            $discount = $promoDiscountData['discount_amount'];
            
            // Increment promo usage count
            if (!empty($bookingData->promo_id)) {
                $promo = Promo::find($bookingData->promo_id);
                if ($promo) {
                    $promo->increment('uses_count', 1);
                }
            }
        }
        
        // Calculate downpayment amount
        $actualFinalPrice = $totals['final_price'] - $discount;
        $dpPercent = config('booking.downpayment_percent', 0.5);
        $downpaymentAmount = $actualFinalPrice * $dpPercent;
        
        $booking = Booking::create([
            'user_id' => $userId,
            'booking_source' => 'online', // Default to online for all current bookings
            'check_in_date' => $bookingData->check_in_date,
            'check_in_time' => '06:00',
            'check_out_date' => $bookingData->check_out_date,
            'check_out_time' => '04:00',
            'guest_name' => $bookingData->guest_name,
            'guest_email' => $bookingData->guest_email,
            'guest_phone' => $bookingData->guest_phone,
            'special_requests' => $bookingData->special_requests,
            'adults' => $bookingData->total_adults,
            'children' => $bookingData->total_children,
            'total_guests' => $bookingData->total_adults + $bookingData->total_children,
            'promo_id' => $bookingData->promo_id,
            'total_price' => $totals['total_room'],
            'meal_price' => $totals['meal_total'],
            'extra_guest_fee' => $totals['extra_guest_fee'],
            'extra_guest_count' => $totals['extra_guest_count'],
            'discount_amount' => $discount,
            'final_price' => $totals['final_price'],
            'downpayment_amount' => $downpaymentAmount,
            'status' => 'pending',
            'reserved_until' => now()->addHours(config('booking.reservation_hold_duration_hours', 2)),
            'meal_quote_data' => isset($totals['meal_quote']) ? json_encode($totals['meal_quote']->toArray()) : null,
        ]);

        $roomIds = array_unique(array_map(fn($r) => $r->room_id, $roomDataArr));
        $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

        foreach ($roomDataArr as $roomData) {
            $room = $rooms[$roomData->room_id];
            
            // Try to assign a room unit immediately for both overnight and day tour bookings
            $assignedUnit = $this->roomUnitService->assignUnitToBooking(
                $room->id,
                $bookingData->check_in_date,
                $bookingData->check_out_date
            );

            $bookingRoom = BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'room_unit_id' => $assignedUnit?->id, // Assign unit immediately if available
                'price_per_night' => $room->price_per_night,
                'adults' => $roomData->adults,
                'children' => $roomData->children,
                'total_guests' => $roomData->adults + $roomData->children,
            ]);

            if ($assignedUnit) {
                Log::info("Assigned unit {$assignedUnit->unit_number} to booking room {$bookingRoom->id} for booking {$booking->reference_number}");
            } else {
                Log::warning("No available units found for room {$room->name} (ID: {$room->id}) during booking creation for booking {$booking->reference_number}");
            }
        }
        return $booking;
    }

    private function validateRoomTypes(array $roomDataArr): void
    {
        if (empty($roomDataArr)) {
            return;
        }

        // Get room IDs from room data array (they're slugs in this action)
        $roomSlugs = array_unique(array_map(fn($r) => $r->room_id, $roomDataArr));
        
        // Get rooms with their room_type
        $rooms = Room::whereIn('slug', $roomSlugs)->select('id', 'slug', 'name', 'room_type')->get();
        
        // Check if all rooms are the same type
        $roomTypes = $rooms->pluck('room_type')->unique();
        
        if ($roomTypes->count() > 1) {
            $dayTourRooms = $rooms->where('room_type', 'day_tour');
            $overnightRooms = $rooms->where('room_type', 'overnight');
            
            throw new \InvalidArgumentException(
                'Cannot mix Day Tour and Overnight accommodations in a single booking. ' .
                'Day Tour rooms: ' . $dayTourRooms->pluck('name')->implode(', ') . '. ' .
                'Overnight rooms: ' . $overnightRooms->pluck('name')->implode(', ') . '.'
            );
        }

        // Additional check: if all rooms are day_tour, this should be a Day Tour booking
        if ($roomTypes->first() === 'day_tour') {
            throw new \InvalidArgumentException(
                'Day Tour rooms cannot be booked through the regular booking flow. ' .
                'Please use the Day Tour booking page instead.'
            );
        }
    }
}
