<?php

namespace App\Actions\DayTour;

use App\Contracts\Services\DayTourServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\DTO\DayTour\DayTourBookingModificationData;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\DayTourPricing;
use App\Services\RoomUnitService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ModifyDayTourBookingAction
{
    public function __construct(
        private DayTourServiceInterface $dayTourService,
        private MealPricingServiceInterface $mealPricingService,
        private MealCalendarServiceInterface $calendarService,
        private RoomUnitService $roomUnitService
    ) {}

    public function execute(Booking $booking, DayTourBookingModificationData $modificationData): Booking
    {
        return DB::transaction(function () use ($booking, $modificationData) {
            // Validate all rooms are Day Tour type
            $roomSlugs = array_map(fn($room) => $room['room_id'], $modificationData->rooms);
            $this->dayTourService->validateDayTourRooms($roomSlugs);

            // Validate room capacity for each room
            $this->validateRoomCapacity($modificationData->rooms);

            // Validate no duplicate room unit selections
            $this->validateNoDuplicateRoomUnits($modificationData->rooms);

            // Parse the booking date without timezone conversion
            $localDate = Carbon::parse($booking->check_in_date)->startOfDay();

            // Get current Day Tour pricing for the booking date
            $dayTourPricing = DayTourPricing::getActivePricingForDate($localDate);
            if (!$dayTourPricing) {
                throw new \InvalidArgumentException('No active Day Tour pricing found for the booking date.');
            }

            // Get current meal program and pricing
            $mealProgram = $this->calendarService->getActiveProgramForDate($localDate);
            if (!$mealProgram) {
                throw new \InvalidArgumentException('No active meal program found for the booking date.');
            }

            $mealPricingTier = $this->mealPricingService->getPricingTierForDate($mealProgram->id, $localDate);
            if (!$mealPricingTier) {
                throw new \InvalidArgumentException('Meal pricing not configured for the booking date.');
            }

            // Get rooms data
            $rooms = Room::whereIn('slug', $roomSlugs)->get()->keyBy('slug');

            // Calculate new totals
            $totalAdults = array_sum(array_map(fn($room) => $room['adults'], $modificationData->rooms));
            $totalChildren = array_sum(array_map(fn($room) => $room['children'], $modificationData->rooms));
            $totalGuests = $totalAdults + $totalChildren;

            $roomTotal = 0;
            $mealTotal = 0;
            $bookingRoomsData = [];

            foreach ($modificationData->rooms as $roomData) {
                $room = $rooms[$roomData['room_id']];
                $roomGuests = $roomData['adults'] + $roomData['children'];
                $basePrice = $dayTourPricing->price_per_pax * $roomGuests;

                // Calculate meal costs
                $lunchCost = 0;
                $pmSnackCost = 0;
                $dinnerCost = 0;

                if ($roomData['include_lunch']) {
                    $lunchCost = ($roomData['adults'] * $mealPricingTier->adult_lunch_price) +
                        ($roomData['children'] * $mealPricingTier->child_lunch_price);
                }

                if ($roomData['include_pm_snack']) {
                    $pmSnackCost = ($roomData['adults'] * $mealPricingTier->adult_pm_snack_price) +
                        ($roomData['children'] * $mealPricingTier->child_pm_snack_price);
                }

                $selectionMealCost = $lunchCost + $pmSnackCost + $dinnerCost;
                $selectionTotalPrice = $basePrice + $selectionMealCost;

                $roomTotal += $basePrice;
                $mealTotal += $selectionMealCost;

                $bookingRoomsData[] = [
                    'room_id' => $room->id,
                    'room_unit_id' => $roomData['room_unit_id'],
                    'price_per_night' => $dayTourPricing->price_per_pax,
                    'adults' => $roomData['adults'],
                    'children' => $roomData['children'],
                    'total_guests' => $roomGuests,
                    'include_lunch' => $roomData['include_lunch'],
                    'include_pm_snack' => $roomData['include_pm_snack'],
                    'include_dinner' => false,
                    'lunch_cost' => $lunchCost,
                    'pm_snack_cost' => $pmSnackCost,
                    'dinner_cost' => $dinnerCost,
                    'meal_cost' => $selectionMealCost,
                    'base_price' => $basePrice,
                    'total_price' => $selectionTotalPrice,
                ];
            }

            $grandTotal = $roomTotal + $mealTotal;

            // Apply existing promo discount if present
            $discount = $booking->discount_amount ?? 0;
            $actualFinalPrice = $grandTotal - $discount;

            // Calculate new downpayment amount
            $dpPercent = config('booking.downpayment_percent', 0.5);
            $downpaymentAmount = $actualFinalPrice * $dpPercent;

            // Update booking totals
            $booking->update([
                'adults' => $totalAdults,
                'children' => $totalChildren,
                'total_guests' => $totalGuests,
                'total_price' => $roomTotal,
                'meal_price' => $mealTotal,
                'final_price' => $actualFinalPrice,
                'downpayment_amount' => $downpaymentAmount,
                'modification_reason' => $modificationData->modification_reason,
                'meal_quote_data' => json_encode($this->extractDayTourMealData($modificationData, $dayTourPricing, $mealPricingTier)),
            ]);

            // Delete existing booking rooms
            $booking->bookingRooms()->delete();

            // Create new booking rooms with room unit assignment
            foreach ($bookingRoomsData as $roomData) {
                // Try to assign a room unit if not already assigned
                if (!$roomData['room_unit_id']) {
                    $assignedUnit = $this->roomUnitService->assignUnitToBooking(
                        $roomData['room_id'],
                        $booking->check_in_date,
                        $booking->check_out_date
                    );
                    $roomData['room_unit_id'] = $assignedUnit?->id;
                }

                $booking->bookingRooms()->create($roomData);
            }

            // Send modification email if requested
            if ($modificationData->send_email) {
                Mail::to($booking->guest_email)->queue(new \App\Mail\BookingModification($booking, $modificationData->modification_reason));
            }

            return $booking->fresh(['bookingRooms.room', 'bookingRooms.roomUnit']);
        });
    }

    private function extractDayTourMealData(
        DayTourBookingModificationData $modificationData,
        DayTourPricing $dayTourPricing,
        $mealPricingTier
    ): array {
        $mealBreakdown = [];

        foreach ($modificationData->rooms as $index => $roomData) {
            $room = Room::where('slug', $roomData['room_id'])->first();

            // Calculate costs using current pricing
            $lunchCost = 0;
            $pmSnackCost = 0;

            if ($roomData['include_lunch']) {
                $lunchCost = ($roomData['adults'] * $mealPricingTier->adult_lunch_price) +
                    ($roomData['children'] * $mealPricingTier->child_lunch_price);
            }

            if ($roomData['include_pm_snack']) {
                $pmSnackCost = ($roomData['adults'] * $mealPricingTier->adult_pm_snack_price) +
                    ($roomData['children'] * $mealPricingTier->child_pm_snack_price);
            }

            $mealBreakdown[] = [
                'room_name' => $room->name ?? "Room " . ($index + 1),
                'adults' => $roomData['adults'],
                'children' => $roomData['children'],
                'include_lunch' => $roomData['include_lunch'],
                'include_pm_snack' => $roomData['include_pm_snack'],
                'lunch_cost' => $lunchCost,
                'pm_snack_cost' => $pmSnackCost,
                'meal_cost' => $lunchCost + $pmSnackCost,
                'base_price' => $dayTourPricing->price_per_pax * ($roomData['adults'] + $roomData['children']),
                'pricing_details' => [
                    'price_per_pax' => $dayTourPricing->price_per_pax,
                    'adult_lunch_price' => $mealPricingTier->adult_lunch_price,
                    'child_lunch_price' => $mealPricingTier->child_lunch_price,
                    'adult_pm_snack_price' => $mealPricingTier->adult_pm_snack_price,
                    'child_pm_snack_price' => $mealPricingTier->child_pm_snack_price,
                ],
            ];
        }

        return [
            'type' => 'day_tour',
            'date' => $modificationData->rooms[0]['date'] ?? null,
            'day_tour_pricing_id' => $dayTourPricing->id,
            'meal_pricing_tier_id' => $mealPricingTier->id,
            'selections' => $mealBreakdown,
        ];
    }

    /**
     * Validate room capacity for each room in the modification
     */
    private function validateRoomCapacity(array $rooms): void
    {
        $roomSlugs = array_map(fn($room) => $room['room_id'], $rooms);
        $roomModels = Room::whereIn('slug', $roomSlugs)->get()->keyBy('slug');

        foreach ($rooms as $index => $roomData) {
            $room = $roomModels[$roomData['room_id']] ?? null;
            if (!$room) {
                throw new \InvalidArgumentException("Room '{$roomData['room_id']}' not found.");
            }

            $totalGuests = $roomData['adults'] + $roomData['children'];
            $maxCapacity = $room->max_guests + ($room->extra_guests ?? 0);

            if ($totalGuests > $maxCapacity) {
                $extraGuests = $room->extra_guests ?? 0;
                throw new \InvalidArgumentException(
                    "Room '{$room->name}' can accommodate maximum {$maxCapacity} guests (Max: {$room->max_guests}, Extra: {$extraGuests}). You have {$totalGuests} guests."
                );
            }

            if ($totalGuests < 1) {
                throw new \InvalidArgumentException("At least 1 guest is required per room.");
            }
        }
    }

    /**
     * Validate that no room units are selected multiple times
     */
    private function validateNoDuplicateRoomUnits(array $rooms): void
    {
        $selectedUnits = [];
        foreach ($rooms as $index => $roomData) {
            if (isset($roomData['room_unit_id'])) {
                if (in_array($roomData['room_unit_id'], $selectedUnits)) {
                    throw new \InvalidArgumentException("Room unit is already selected for another room.");
                }
                $selectedUnits[] = $roomData['room_unit_id'];
            }
        }
    }
}
