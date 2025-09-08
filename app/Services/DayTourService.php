<?php

namespace App\Services;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Contracts\Services\DayTourServiceInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
use App\DTO\DayTour\DayTourAvailabilityResponseDTO;
use App\DTO\DayTour\DayTourQuoteItemDTO;
use App\DTO\DayTour\DayTourQuoteRequestDTO;
use App\DTO\DayTour\DayTourQuoteResponseDTO;
use App\DTO\DayTour\DayTourRoomAvailabilityDTO;
use App\DTO\DayTour\DayTourTotalsDTO;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DayTourService implements DayTourServiceInterface
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private MealCalendarServiceInterface $calendarService,
        private MealPricingServiceInterface $mealPricingService
    ) {}

    public function getAvailabilityForDate(Carbon $date): DayTourAvailabilityResponseDTO
    {
        // Get property timezone
        $timezone = config('resort.timezone', 'Asia/Singapore');
        $localDate = $date->copy()->setTimezone($timezone)->startOfDay();
        
        // Check buffet active status
        $buffetActive = $this->calendarService->isBuffetActiveOn($localDate);
        
        // Get active meal program for this date and its PM snack policy
        $program = $this->calendarService->getActiveProgramForDate($localDate);
        $pmSnackPolicy = $program ? $program->pm_snack_policy : 'hidden';
        
        // Get lunch and snack prices
        $prices = $this->mealPricingService->getLunchAndSnackPrices($localDate);
        
        // Get Day Tour rooms with availability
        $dayTourRooms = $this->roomRepository->getDayTourRoomsWithAvailability($localDate);
        
        // Get active Day Tour pricing for the date
        $dayTourPricing = \App\Models\DayTourPricing::getActivePricingForDate($localDate);
        $pricePerPax = $dayTourPricing ? (float) $dayTourPricing->price_per_pax : 0.0;

        $roomDTOs = [];
        foreach ($dayTourRooms as $room) {
            // Get detailed availability information
            $availability = $this->roomRepository->getDetailedAvailability(
                $room->id, 
                $localDate->format('Y-m-d'), 
                $localDate->copy()->addDay()->format('Y-m-d')
            );
            
            $roomDTOs[] = new DayTourRoomAvailabilityDTO(
                roomId: $room->slug,
                name: $room->name,
                roomType: $room->room_type,
                maxGuests: $room->max_guests,
                extraGuests: $room->extra_guests,
                minGuests: $room->min_guests ?? 1,
                maxGuestsRange: $room->max_guests_range ?? $room->max_guests,
                pricePerPax: $pricePerPax,
                basePrice: $pricePerPax, // For backward compatibility
                availableUnits: $availability['available'],
                pending: $availability['pending'],
                confirmed: $availability['confirmed'],
                maintenance: $availability['maintenance'],
                totalUnits: $availability['total_units']
            );
        }
        
        return new DayTourAvailabilityResponseDTO(
            date: $localDate->format('Y-m-d'),
            buffetActive: $buffetActive,
            pmSnackPolicy: $pmSnackPolicy,
            lunchPrices: $prices['lunch'],
            pmSnackPrices: $prices['snack'],
            rooms: $roomDTOs
        );
    }

    public function quoteDayTour(DayTourQuoteRequestDTO $request): DayTourQuoteResponseDTO
    {
        // Validate all rooms are Day Tour type
        $roomSlugs = array_map(fn($selection) => $selection->room_id, $request->selections);
        $this->validateDayTourRooms($roomSlugs);
        
        // Get property timezone
        $timezone = config('resort.timezone', 'Asia/Singapore');
        $localDate = Carbon::parse($request->date)->setTimezone($timezone)->startOfDay();
        
        // Check buffet active status
        $buffetActive = $this->calendarService->isBuffetActiveOn($localDate);
        
        // Get active meal program for this date and its PM snack policy
        $program = $this->calendarService->getActiveProgramForDate($localDate);
        $pmSnackPolicy = $program ? $program->pm_snack_policy : 'hidden';
        
        // Get rooms data by slug
        $rooms = Room::whereIn('slug', $roomSlugs)->get();
        $roomsBySlug = $rooms->keyBy('slug');
        
        $items = [];
        $totalRoomsSubtotal = 0.0;
        $totalMealsSubtotal = 0.0;
        
        foreach ($request->selections as $selection) {
            $room = $roomsBySlug[$selection->room_id];
            
            // Calculate base room cost (one room per selection, following overnight pattern)
            $baseSubtotal = $room->price_per_night;
            
            // Calculate extra guest fees (simplified - using existing logic)
            $totalGuests = $selection->adults + $selection->children;
            $extraGuests = max(0, $totalGuests - $room->max_guests);
            $extraGuestFee = $extraGuests * ($room->extra_guest_fee ?? 0);
            
            // Calculate meal costs
            $mealBreakdown = $this->mealPricingService->quoteDayTourMeals(
                date: $localDate,
                adults: $selection->adults,
                children: $selection->children,
                includeLunch: $request->options->include_lunch,
                includePmSnack: $request->options->include_pm_snack,
                pmSnackPolicy: $pmSnackPolicy
            );
            
            // Calculate meal subtotal for this item
            $itemMealsSubtotal = 0.0;
            if ($mealBreakdown->lunch && $mealBreakdown->lunch->applied) {
                $itemMealsSubtotal += $mealBreakdown->lunch->total;
            }
            if ($mealBreakdown->pmSnack && $mealBreakdown->pmSnack->applied) {
                $itemMealsSubtotal += $mealBreakdown->pmSnack->total;
            }
            
            $itemTotal = $baseSubtotal + $extraGuestFee; // Note: meals are calculated separately in totals
            
            $items[] = new DayTourQuoteItemDTO(
                roomId: $selection->room_id,
                baseSubtotal: $baseSubtotal,
                extraGuestFee: $extraGuestFee,
                mealBreakdown: $mealBreakdown,
                itemTotal: $itemTotal
            );
            
            $totalRoomsSubtotal += $itemTotal;
            $totalMealsSubtotal += $itemMealsSubtotal;
        }
        
        $grandTotal = $totalRoomsSubtotal + $totalMealsSubtotal;
        
        $totals = new DayTourTotalsDTO(
            roomsSubtotal: $totalRoomsSubtotal,
            mealsSubtotal: $totalMealsSubtotal,
            grandTotal: $grandTotal
        );
        
        // Generate notes
        $notes = [];
        if ($buffetActive) {
            $notes[] = 'Buffet lunch optional for Day Tour.';
            
            if ($pmSnackPolicy === 'required') {
                $notes[] = 'PM snack included (required by meal program).';
            } elseif ($pmSnackPolicy === 'optional') {
                $notes[] = 'PM snack optional.';
            }
        } else {
            $notes[] = 'Buffet not active on selected date.';
        }
        
        return new DayTourQuoteResponseDTO(
            date: $localDate->format('Y-m-d'),
            buffetActive: $buffetActive,
            pmSnackPolicy: $pmSnackPolicy,
            items: $items,
            totals: $totals,
            notes: $notes
        );
    }

    public function validateDayTourRooms(array $roomSlugs): void
    {
        if (empty($roomSlugs)) {
            return;
        }
        
        $rooms = Room::whereIn('slug', $roomSlugs)->get(['id', 'slug', 'name', 'room_type']);
        
        foreach ($rooms as $room) {
            if ($room->room_type !== 'day_tour') {
                throw new InvalidArgumentException(
                    "Room '{$room->name}' (Slug: {$room->slug}) is not a Day Tour room. " .
                    "Only Day Tour rooms are allowed in Day Tour bookings."
                );
            }
        }
        
        // Check if all requested room slugs exist
        $foundSlugs = $rooms->pluck('slug')->toArray();
        $missingSlugs = array_diff($roomSlugs, $foundSlugs);
        
        if (!empty($missingSlugs)) {
            throw new InvalidArgumentException(
                "Room(s) not found: " . implode(', ', $missingSlugs)
            );
        }
    }
}
