<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\MealCalendarServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
use App\Models\Booking;
use App\Models\DayTourPricing;
use App\Models\Promo;
use App\Services\Bookings\BookingRoomUnitReassignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RescheduleBookingAction
{
    public function __construct(
        private BookingRoomUnitReassignmentService $roomUnitReassignment,
        private CalculateBookingTotalAction $calculateBookingTotal,
        private MealCalendarServiceInterface $calendarService,
        private MealPricingServiceInterface $mealPricingService,
    ) {}

    /**
     * Reschedule a booking: update dates, recalculate pricing and meal quote for the new dates,
     * then reassign room units if needed.
     */
    public function execute(Booking $booking, string $newCheckIn, string $newCheckOut): Booking
    {
        DB::beginTransaction();

        try {
            $oldCheckIn = $booking->check_in_date;
            $oldCheckOut = $booking->check_out_date;

            $booking->update([
                'check_in_date' => $newCheckIn,
                'check_out_date' => $newCheckOut,
            ]);

            $booking->refresh();
            $booking->load('bookingRooms.room');

            if ($booking->booking_type === 'day_tour') {
                $this->recalculateDayTourPricing($booking);
            } else {
                $this->recalculateOvernightPricing($booking);
            }

            $booking->refresh();
            $booking->load('bookingRooms.room');

            $this->roomUnitReassignment->reassignRoomUnitsForBooking($booking, $newCheckIn, $newCheckOut);

            DB::commit();

            Log::info('Booking rescheduled with pricing recalculation and room unit reassignment', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'booking_type' => $booking->booking_type,
                'old_check_in' => $oldCheckIn,
                'old_check_out' => $oldCheckOut,
                'new_check_in' => $newCheckIn,
                'new_check_out' => $newCheckOut,
            ]);

            return $booking->refresh();

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Same pipeline as adjust-nights: meal program for the new date range, room totals, extra guest fees, promo.
     */
    private function recalculateOvernightPricing(Booking $booking): void
    {
        $bookingRoomArr = [];
        foreach ($booking->bookingRooms as $br) {
            $slug = $br->room?->slug;
            if (! $slug) {
                continue;
            }
            $bookingRoomArr[] = (object) [
                'room_id' => $slug,
                'adults' => $br->adults,
                'children' => $br->children,
            ];
        }

        if ($bookingRoomArr === []) {
            return;
        }

        $checkIn = Carbon::parse($booking->check_in_date)->format('Y-m-d');
        $checkOut = Carbon::parse($booking->check_out_date)->format('Y-m-d');

        $promo = $booking->promo_id ? Promo::find($booking->promo_id) : null;

        $totals = $this->calculateBookingTotal->execute(
            $bookingRoomArr,
            $checkIn,
            $checkOut,
            (int) $booking->adults,
            (int) $booking->children,
            $promo
        );

        $discountAmount = isset($totals['promo_discount']) ? ($totals['promo_discount']['discount_amount'] ?? 0) : 0;
        $mealQuote = $totals['meal_quote'];

        $booking->update([
            'total_price' => $totals['total_room'],
            'meal_price' => $totals['meal_total'],
            'extra_guest_fee' => $totals['extra_guest_fee'],
            'extra_guest_count' => $totals['extra_guest_count'],
            'final_price' => $totals['final_price'],
            'discount_amount' => $discountAmount,
            'meal_quote_data' => $mealQuote ? json_encode($mealQuote->toArray()) : null,
        ]);
    }

    /**
     * Day tour: new tour date may change day-tour pricing tier and meal prices — align with ModifyDayTourBookingAction.
     */
    private function recalculateDayTourPricing(Booking $booking): void
    {
        $localDate = Carbon::parse($booking->check_in_date)->startOfDay();

        $dayTourPricing = DayTourPricing::getActivePricingForDate($localDate);
        if (! $dayTourPricing) {
            throw new \InvalidArgumentException('No active Day Tour pricing for the selected date.');
        }

        $mealProgram = $this->calendarService->getActiveProgramForDate($localDate);
        if (! $mealProgram) {
            throw new \InvalidArgumentException('No active meal program for the selected date.');
        }

        $mealPricingTier = $this->mealPricingService->getPricingTierForDate($mealProgram->id, $localDate);
        if (! $mealPricingTier) {
            throw new \InvalidArgumentException('Meal pricing not configured for the selected date.');
        }

        $roomTotal = 0;
        $mealTotal = 0;
        $mealBreakdown = [];

        foreach ($booking->bookingRooms as $index => $br) {
            $room = $br->room;
            if (! $room) {
                continue;
            }

            $roomGuests = $br->adults + $br->children;
            $basePrice = $dayTourPricing->price_per_pax * $roomGuests;

            $lunchCost = 0;
            $pmSnackCost = 0;
            if ($br->include_lunch) {
                $lunchCost = ($br->adults * $mealPricingTier->adult_lunch_price)
                    + ($br->children * $mealPricingTier->child_lunch_price);
            }
            if ($br->include_pm_snack) {
                $pmSnackCost = ($br->adults * $mealPricingTier->adult_pm_snack_price)
                    + ($br->children * $mealPricingTier->child_pm_snack_price);
            }

            $selectionMealCost = $lunchCost + $pmSnackCost;
            $selectionTotalPrice = $basePrice + $selectionMealCost;

            $roomTotal += $basePrice;
            $mealTotal += $selectionMealCost;

            $br->update([
                'price_per_night' => $dayTourPricing->price_per_pax,
                'lunch_cost' => $lunchCost,
                'pm_snack_cost' => $pmSnackCost,
                'meal_cost' => $selectionMealCost,
                'base_price' => $basePrice,
                'total_price' => $selectionTotalPrice,
            ]);

            $mealBreakdown[] = [
                'room_name' => $room->name ?? 'Room '.($index + 1),
                'adults' => $br->adults,
                'children' => $br->children,
                'include_lunch' => $br->include_lunch,
                'include_pm_snack' => $br->include_pm_snack,
                'lunch_cost' => $lunchCost,
                'pm_snack_cost' => $pmSnackCost,
                'meal_cost' => $selectionMealCost,
                'base_price' => $basePrice,
                'pricing_details' => [
                    'price_per_pax' => $dayTourPricing->price_per_pax,
                    'adult_lunch_price' => $mealPricingTier->adult_lunch_price,
                    'child_lunch_price' => $mealPricingTier->child_lunch_price,
                    'adult_pm_snack_price' => $mealPricingTier->adult_pm_snack_price,
                    'child_pm_snack_price' => $mealPricingTier->child_pm_snack_price,
                ],
            ];
        }

        $grandTotal = $roomTotal + $mealTotal;
        $discount = $booking->discount_amount ?? 0;
        $actualFinalPrice = $grandTotal - $discount;
        $dpPercent = config('booking.downpayment_percent', 0.5);
        $downpaymentAmount = $actualFinalPrice * $dpPercent;

        $booking->update([
            'total_price' => $roomTotal,
            'meal_price' => $mealTotal,
            'final_price' => $actualFinalPrice,
            'downpayment_amount' => $downpaymentAmount,
            'meal_quote_data' => json_encode([
                'type' => 'day_tour',
                'date' => Carbon::parse($booking->check_in_date)->format('Y-m-d'),
                'day_tour_pricing_id' => $dayTourPricing->id,
                'meal_pricing_tier_id' => $mealPricingTier->id,
                'selections' => $mealBreakdown,
            ]),
        ]);
    }
}
