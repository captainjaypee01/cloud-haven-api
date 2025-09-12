<?php

namespace App\Actions\DayTour;

use App\Actions\Bookings\CheckRoomAvailabilityAction;
use App\Contracts\Services\DayTourServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Contracts\Services\BookingLockServiceInterface;
use App\DTO\DayTour\DayTourBookingRequestDTO;
use App\DTO\DayTour\DayTourQuoteRequestDTO;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\DayTourPricing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateDayTourBookingAction
{
    public function __construct(
        private DayTourServiceInterface $dayTourService,
        private MealPricingServiceInterface $mealPricingService,
        private MealCalendarServiceInterface $calendarService,
        private BookingLockServiceInterface $lockService,
        private CheckRoomAvailabilityAction $checkAvailability,
        private ComputeDayTourQuoteAction $computeQuoteAction
    ) {}

    public function execute(DayTourBookingRequestDTO $request, ?int $userId = null): Booking
    {
        return DB::transaction(function () use ($request, $userId) {
            // Validate all rooms are Day Tour type
            $roomIds = array_map(fn($selection) => $selection->room_id, $request->selections);
            $this->dayTourService->validateDayTourRooms($roomIds);

            // 1. Check room availability BEFORE creating booking (same as overnight bookings)
            $this->checkDayTourRoomAvailability($request);

            // Parse the date without timezone conversion since meal programs are date-based, not time-based
            // This prevents edge cases around month boundaries due to timezone conversion
            $localDate = Carbon::parse($request->date)->startOfDay();

            // Get Day Tour pricing from database (NOT from frontend)
            $dayTourPricing = DayTourPricing::where('is_active', true)
                ->where('effective_from', '<=', $localDate->format('Y-m-d'))
                ->where(function ($query) use ($localDate) {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $localDate->format('Y-m-d'));
                })
                ->orderBy('effective_from', 'desc')
                ->first();

            if (!$dayTourPricing) {
                throw new \InvalidArgumentException('No active Day Tour pricing found for the selected date.');
            }

            // Get meal program and pricing using the same logic as DayTourService
            // Use calendarService to get the program active for the specific date
            $mealProgram = $this->calendarService->getActiveProgramForDate($localDate);
            if (!$mealProgram) {
                throw new \InvalidArgumentException('No active meal program found for the selected date.');
            }

            // Get pricing tier for the specific date (same as DayTourService)
            $mealPricingTier = $this->mealPricingService->getPricingTierForDate($mealProgram->id, $localDate);
            if (!$mealPricingTier) {
                throw new \InvalidArgumentException('Meal pricing not configured for the selected date.');
            }

            $bookingDate = $localDate;

            // Set Day Tour times (8:00 AM to 5:00 PM)
            $checkInTime = $bookingDate->copy()->setTime(8, 0)->utc()->format('H:i');
            $checkOutTime = $bookingDate->copy()->setTime(17, 0)->utc()->format('H:i');

            // Calculate totals using DATABASE pricing (ignore frontend values)
            $totalAdults = array_sum(array_map(fn($s) => $s->adults, $request->selections));
            $totalChildren = array_sum(array_map(fn($s) => $s->children, $request->selections));
            $totalGuests = $totalAdults + $totalChildren;

            // Get rooms data first (before the loop)
            $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

            // Pre-calculate all totals and prepare booking room data in single loop
            $roomTotal = $dayTourPricing->price_per_pax * $totalGuests;
            $mealTotal = 0;
            $bookingRoomsData = [];

            // Calculate totals and prepare final booking room data in single loop
            foreach ($request->selections as $selection) {
                $room = $rooms[$selection->room_id];
                $selectionGuests = $selection->adults + $selection->children;
                $basePrice = $dayTourPricing->price_per_pax * $selectionGuests;

                // Calculate meal costs using correct column names
                $lunchCost = 0;
                $pmSnackCost = 0;
                $dinnerCost = 0; // For future use

                if ($selection->include_lunch) {
                    $lunchCost = ($selection->adults * $mealPricingTier->adult_lunch_price) +
                        ($selection->children * $mealPricingTier->child_lunch_price);
                }

                if ($selection->include_pm_snack) {
                    $pmSnackCost = ($selection->adults * $mealPricingTier->adult_pm_snack_price) +
                        ($selection->children * $mealPricingTier->child_pm_snack_price);
                }

                $selectionMealCost = $lunchCost + $pmSnackCost + $dinnerCost;
                $selectionTotalPrice = $basePrice + $selectionMealCost;

                // Add to running totals
                $mealTotal += $selectionMealCost;

                // Prepare final booking room data (createMany handles timestamps)
                $bookingRoomsData[] = [
                    'room_id' => $room->id,
                    'price_per_night' => $dayTourPricing->price_per_pax,
                    'adults' => $selection->adults,
                    'children' => $selection->children,
                    'total_guests' => $selectionGuests,
                    'include_lunch' => $selection->include_lunch,
                    'include_pm_snack' => $selection->include_pm_snack,
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

            // Create booking with correct calculated totals
            $booking = Booking::create([
                'user_id' => $userId,
                'booking_type' => 'day_tour',
                'check_in_date' => $request->date,
                'check_in_time' => $checkInTime,
                'check_out_date' => $request->date, // Same day for Day Tour
                'check_out_time' => $checkOutTime,
                'guest_name' => $request->guest->name,
                'guest_email' => $request->guest->email,
                'guest_phone' => $request->guest->phone,
                'special_requests' => $request->specialRequests,
                'adults' => $totalAdults,
                'children' => $totalChildren,
                'total_guests' => $totalAdults + $totalChildren,
                'total_price' => $roomTotal,
                'meal_price' => $mealTotal,
                'discount_amount' => 0, // No promo support for Day Tour initially
                'final_price' => $grandTotal,
                'status' => 'pending',
                'reserved_until' => now()->addHours(config('booking.reservation_hold_duration_hours', 2)),
                'meal_quote_data' => json_encode($this->extractDayTourMealData($request, $dayTourPricing, $mealPricingTier)),
            ]);

            // Use createMany for cleaner Laravel approach
            $booking->bookingRooms()->createMany($bookingRoomsData);
            
            // Create Redis lock for the booking (same as overnight bookings)
            $this->createBookingLock($booking, $request->selections, $request->date);
            
            Mail::to($request->guest->email)->queue(new \App\Mail\BookingReservation($booking));

            return $booking;
        });
    }

    private function extractDayTourMealData(
        DayTourBookingRequestDTO $request,
        DayTourPricing $dayTourPricing,
        $mealPricingTier
    ): array {
        $mealBreakdown = [];

        foreach ($request->selections as $index => $selection) {
            $room = Room::where('slug', $selection->room_id)->first();

            // Calculate CORRECT costs using database pricing
            $lunchCost = 0;
            $pmSnackCost = 0;

            if ($selection->include_lunch) {
                $lunchCost = ($selection->adults * $mealPricingTier->adult_lunch_price) +
                    ($selection->children * $mealPricingTier->child_lunch_price);
            }

            if ($selection->include_pm_snack) {
                $pmSnackCost = ($selection->adults * $mealPricingTier->adult_pm_snack_price) +
                    ($selection->children * $mealPricingTier->child_pm_snack_price);
            }

            $mealBreakdown[] = [
                'room_name' => $room->name ?? "Room " . ($index + 1),
                'adults' => $selection->adults,
                'children' => $selection->children,
                'include_lunch' => $selection->include_lunch,
                'include_pm_snack' => $selection->include_pm_snack,
                'lunch_cost' => $lunchCost,
                'pm_snack_cost' => $pmSnackCost,
                'meal_cost' => $lunchCost + $pmSnackCost,
                'base_price' => $dayTourPricing->price_per_pax * ($selection->adults + $selection->children),
                // Include pricing details for audit trail
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
            'date' => $request->date,
            'day_tour_pricing_id' => $dayTourPricing->id,
            'meal_pricing_tier_id' => $mealPricingTier->id,
            'selections' => $mealBreakdown,
        ];
    }

    /**
     * Create Redis lock for Day Tour booking (similar to overnight bookings)
     */
    private function createBookingLock(Booking $booking, array $selections, string $date): void
    {
        $holdHours = config('booking.reservation_hold_duration_hours', 2);
        
        // Convert selections to room data format expected by lock service
        $roomData = [];
        foreach ($selections as $selection) {
            $roomData[] = [
                'room_id' => $selection->room_id, // This is the room slug
                'adults' => $selection->adults,
                'children' => $selection->children,
                'total_guests' => $selection->adults + $selection->children,
            ];
        }
        
        $lockData = [
            'rooms' => $roomData,
            'check_in_date' => $date,
            'check_out_date' => $date, // Same date for Day Tour
            'expires_at' => now()->addHours($holdHours)->timestamp
        ];
        
        $this->lockService->lock($booking->id, $lockData);
    }

    /**
     * Check room availability for Day Tour bookings (same logic as overnight bookings)
     */
    private function checkDayTourRoomAvailability(DayTourBookingRequestDTO $request): void
    {
        // Convert Day Tour selections to BookingRoomData format for availability check
        $bookingRoomData = [];
        foreach ($request->selections as $selection) {
            $bookingRoomData[] = (object) [
                'room_id' => $selection->room_id, // This is the room slug
                'adults' => $selection->adults,
                'children' => $selection->children,
            ];
        }

        // Use the same availability check as overnight bookings
        // For Day Tour: check_in_date = check_out_date (same day)
        $this->checkAvailability->execute(
            $bookingRoomData,
            $request->date,
            $request->date // Same date for Day Tour
        );
    }
}
