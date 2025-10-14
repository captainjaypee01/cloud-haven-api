<?php

namespace App\Actions\Bookings;

use App\Actions\Bookings\CalculateBookingTotalAction;
use App\Actions\Bookings\CheckRoomAvailabilityAction;
use App\DTO\Bookings\BookingModificationData;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Services\EmailTrackingService;
use App\Services\CacheInvalidationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModifyBookingAction
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkAvailability,
        private CalculateBookingTotalAction $calcTotal,
        private CacheInvalidationService $cacheInvalidation,
    ) {}

    public function execute(Booking $booking, BookingModificationData $modificationData): Booking
    {
        Log::info('Starting booking modification', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_number,
            'current_rooms_count' => $booking->bookingRooms()->count(),
            'new_rooms_count' => count($modificationData->rooms),
            'modification_reason' => $modificationData->modification_reason,
        ]);

        return DB::transaction(function () use ($booking, $modificationData) {
            // 1. Validate room availability for new configuration
            $this->validateRoomAvailability($booking, $modificationData);

            // 2. Get room data for calculations
            $roomsArray = $modificationData->rooms;
            $roomIds = array_unique(array_map(fn($r) => $r['room_id'], $roomsArray));
            $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

            // 3. Use rooms array directly for calculations
            $bookingRoomArr = $roomsArray;

            // 4. Recalculate totals using existing meal quote data
            $totals = $this->recalculateTotals($booking, $bookingRoomArr, $rooms);

            // 5. Update booking totals
            $booking->update([
                'total_price' => $totals['total_room'],
                'meal_price' => $totals['meal_total'],
                'extra_guest_fee' => $totals['extra_guest_fee'],
                'extra_guest_count' => $totals['extra_guest_count'],
                'final_price' => $totals['final_price'],
                'discount_amount' => $totals['promo_discount']['discount_amount'] ?? 0,
            ]);

            // 6. Update booking rooms
            $this->updateBookingRooms($booking, $modificationData, $rooms);

            // 7. Update booking totals (adults, children, total_guests)
            $this->updateBookingGuestCounts($booking, $modificationData);

            // 8. Send modification notification email (if requested)
            if ($modificationData->send_email) {
                $this->sendModificationEmail($booking, $modificationData);
            }

            Log::info('Booking modification completed successfully', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'new_total_price' => $totals['total_room'],
                'new_meal_price' => $totals['meal_total'],
                'new_extra_guest_fee' => $totals['extra_guest_fee'],
                'new_final_price' => $totals['final_price'],
                'new_discount_amount' => $totals['promo_discount']['discount_amount'] ?? 0,
            ]);

            // Clear cache for the booking date range to ensure fresh availability data
            $this->cacheInvalidation->clearCacheForDateRange(
                $booking->check_in_date,
                $booking->check_out_date
            );

            return $booking->refresh();
        });
    }

    private function validateRoomAvailability(Booking $booking, BookingModificationData $modificationData): void
    {
        // Convert arrays to objects for availability check (CheckRoomAvailabilityAction expects objects)
        $roomsArray = $modificationData->rooms;
        $bookingRoomArr = array_map(fn($rd) => (object) $rd, $roomsArray);

        // Check availability for the new room configuration
        $this->checkAvailability->execute(
            $bookingRoomArr,
            $booking->check_in_date,
            $booking->check_out_date,
            $booking->id // Exclude current booking from availability check
        );
    }

    private function recalculateTotals(Booking $booking, array $bookingRoomArr, $rooms): array
    {
        // Use existing meal quote data from booking
        $mealQuote = $booking->meal_quote_data;
        
        // If no meal quote data exists, create a new one
        if (!$mealQuote) {
            $computeMealQuoteAction = app(\App\Actions\ComputeMealQuoteAction::class);
            $mealQuote = $computeMealQuoteAction->execute($booking->check_in_date, $booking->check_out_date);
        }

        // Calculate room totals
        $totalRoom = 0;
        $nights = Carbon::parse($booking->check_in_date)->diffInDays($booking->check_out_date);
        
        foreach ($bookingRoomArr as $roomData) {
            $room = $rooms[$roomData['room_id']] ?? null;
            if (!$room) {
                continue;
            }
            $totalRoom += $room->price_per_night * $nights;
        }

        // Calculate meal total using existing meal quote data
        $mealTotal = $this->calculateMealTotalFromQuote($mealQuote, $bookingRoomArr, $rooms);

        // Calculate extra guest fees
        $extraGuestData = $this->calculateExtraGuestFeesFromQuote($mealQuote, $bookingRoomArr, $rooms);

        $finalTotal = $totalRoom + $mealTotal + $extraGuestData['total_fee'];

        $totals = [
            'total_room' => $totalRoom,
            'meal_total' => $mealTotal,
            'extra_guest_fee' => $extraGuestData['total_fee'],
            'extra_guest_count' => $extraGuestData['total_count'],
            'final_price' => $finalTotal,
        ];

        // Recalculate promo discount if promo exists
        if ($booking->promo_id) {
            $promo = \App\Models\Promo::find($booking->promo_id);
            if ($promo) {
                $promoCalculationService = app(\App\Services\PromoCalculationService::class);
                $promoResult = $promoCalculationService->calculateDiscount(
                    $promo,
                    $booking->check_in_date,
                    $booking->check_out_date,
                    $totals,
                    $bookingRoomArr,
                    $rooms->toArray(),
                    $mealQuote
                );
                $totals['promo_discount'] = $promoResult;
            }
        }

        return $totals;
    }

    private function calculateMealTotalFromQuote($mealQuote, array $bookingRoomArr, $rooms): float
    {
        $totalMealCost = 0;

        if (!$mealQuote || !isset($mealQuote['nights'])) {
            return 0;
        }

        foreach ($mealQuote['nights'] as $night) {
            $nightCost = 0;

            foreach ($bookingRoomArr as $roomData) {
                $room = $rooms[$roomData['room_id']] ?? null;
                if (!$room) {
                    continue;
                }
                
                $adults = $roomData['adults'] ?? 0;
                $children = $roomData['children'] ?? 0;

                if ($night['type'] === 'buffet') {
                    // Buffet: ALL guests pay the buffet meal price
                    $nightCost += ($adults * ($night['adult_price'] ?? 0)) + ($children * ($night['child_price'] ?? 0));
                } else {
                    // Free breakfast: only extra guests pay for breakfast
                    $totalGuests = $adults + $children;
                    $extraGuests = max(0, $totalGuests - $room->max_guests);
                    
                    // Use adult breakfast price for extra guests
                    $baseCost = $extraGuests * ($night['adult_breakfast_price'] ?? 0);
                    $nightCost += $baseCost;
                }
            }

            $totalMealCost += $nightCost;
        }

        return round($totalMealCost, 2);
    }

    private function calculateExtraGuestFeesFromQuote($mealQuote, array $bookingRoomArr, $rooms): array
    {
        $totalExtraGuestFee = 0;
        $totalExtraGuestCount = 0;

        if (!$mealQuote || !isset($mealQuote['nights'])) {
            return ['total_fee' => 0, 'total_count' => 0];
        }

        foreach ($mealQuote['nights'] as $night) {
            // Only calculate extra guest fees for buffet days
            if ($night['type'] === 'buffet' && ($night['extra_guest_fee'] ?? 0) > 0) {
                $nightExtraGuestCount = 0;
                $nightExtraGuestFee = 0;

                foreach ($bookingRoomArr as $roomData) {
                    $room = $rooms[$roomData['room_id']] ?? null;
                    if (!$room) {
                        continue;
                    }
                    
                    $adults = $roomData['adults'] ?? 0;
                    $children = $roomData['children'] ?? 0;
                    $totalGuests = $adults + $children;
                    
                    // Calculate extra guests for this room
                    $extraGuestsInRoom = max(0, $totalGuests - $room->max_guests);
                    
                    if ($extraGuestsInRoom > 0) {
                        $nightExtraGuestCount += $extraGuestsInRoom;
                        $nightExtraGuestFee += $extraGuestsInRoom * ($night['extra_guest_fee'] ?? 0);
                    }
                }

                $totalExtraGuestCount += $nightExtraGuestCount;
                $totalExtraGuestFee += $nightExtraGuestFee;
            }
        }

        return [
            'total_fee' => round($totalExtraGuestFee, 2),
            'total_count' => $totalExtraGuestCount
        ];
    }

    private function updateBookingRooms(Booking $booking, BookingModificationData $modificationData, $rooms): void
    {
        // Delete existing booking rooms
        $booking->bookingRooms()->delete();

        // Create new booking rooms
        foreach ($modificationData->rooms as $roomData) {
            $room = $rooms[$roomData['room_id']];
            $nights = Carbon::parse($booking->check_in_date)->diffInDays($booking->check_out_date);
            
            $bookingRoom = new BookingRoom([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'room_unit_id' => $roomData['room_unit_id'] ?? null,
                'price_per_night' => $room->price_per_night,
                'adults' => $roomData['adults'],
                'children' => $roomData['children'],
                'total_guests' => $roomData['total_guests'],
            ]);

            // For day tour bookings, preserve meal details
            if ($booking->booking_type === 'day_tour') {
                $bookingRoom->fill([
                    'include_lunch' => false, // Will be updated based on new configuration
                    'include_pm_snack' => false,
                    'include_dinner' => false,
                    'lunch_cost' => 0,
                    'pm_snack_cost' => 0,
                    'dinner_cost' => 0,
                    'meal_cost' => 0,
                    'base_price' => $room->price_per_night,
                    'total_price' => $room->price_per_night,
                ]);
            }

            $bookingRoom->save();
        }
    }

    private function updateBookingGuestCounts(Booking $booking, BookingModificationData $modificationData): void
    {
        $totalAdults = array_sum(array_map(fn($r) => $r['adults'], $modificationData->rooms));
        $totalChildren = array_sum(array_map(fn($r) => $r['children'], $modificationData->rooms));
        $totalGuests = array_sum(array_map(fn($r) => $r['total_guests'], $modificationData->rooms));

        $booking->update([
            'adults' => $totalAdults,
            'children' => $totalChildren,
            'total_guests' => $totalGuests,
            'modification_reason' => $modificationData->modification_reason,
        ]);
    }

    private function sendModificationEmail(Booking $booking, BookingModificationData $modificationData): void
    {
        try {
            EmailTrackingService::sendWithTracking(
                $booking->guest_email,
                new \App\Mail\BookingModification($booking, $modificationData->modification_reason),
                'booking_modification',
                [
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number,
                    'guest_name' => $booking->guest_name,
                    'modification_reason' => $modificationData->modification_reason,
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send booking modification email', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'error' => $e->getMessage()
            ]);
            // Don't fail the modification if email fails
        }
    }
}
