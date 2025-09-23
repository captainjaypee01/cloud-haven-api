<?php

namespace App\Actions\Bookings;

use App\DTO\Bookings\BookingData;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Promo;
use App\Models\Room;
use Illuminate\Support\Str;

class CreateBookingEntitiesAction
{
    public function execute(BookingData $bookingData, array $roomDataArr, $userId, $totals): Booking
    {
        // Prevent mixing room types
        $this->validateRoomTypes($roomDataArr);
        
        // Before creating the Booking, compute totals and apply promo if present
        $discount = 0;
        if (!empty($bookingData->promo_id)) {
            $promo = Promo::find($bookingData->promo_id);
            
            // Use booking check-in date for promo validation instead of current date
            $validationDate = \Carbon\Carbon::parse($bookingData->check_in_date);
            
            if (
                $promo && $promo->active
                && (!$promo->starts_at || $validationDate->gte($promo->starts_at))
                && (!$promo->ends_at || $validationDate->lte($promo->ends_at))
                && (!$promo->expires_at || $validationDate->lte($promo->expires_at))
                && (!$promo->max_uses || $promo->uses_count < $promo->max_uses)
            ) {
                // Determine base amount for discount
                $baseAmount = $totals['final_price'];
                if ($promo->scope === 'room') {
                    $baseAmount = $totals['total_room'];
                } elseif ($promo->scope === 'meal') {
                    $baseAmount = $totals['meal_total'];
                }
                // Calculate discount
                if ($promo->discount_type === 'percentage') {
                    $discount = $baseAmount * ($promo->discount_value / 100);
                } else if ($promo->discount_type === 'fixed') {
                    $discount = min($promo->discount_value, $baseAmount);
                }
                $discount = round($discount, 2);
                $promo->increment('uses_count', 1);
                // Mark one usage (will finalize usage count update after payment maybe)
                // $promo->increment('uses_count');
                // Adjust totals
            }
        }
        $booking = Booking::create([
            'user_id' => $userId,
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
            'discount_amount' => $discount,
            'final_price' => $totals['final_price'],
            'status' => 'pending',
            'reserved_until' => now()->addHours(config('booking.reservation_hold_duration_hours', 2)),
            'meal_quote_data' => isset($totals['meal_quote']) ? json_encode($totals['meal_quote']->toArray()) : null,
        ]);

        $roomIds = array_unique(array_map(fn($r) => $r->room_id, $roomDataArr));
        $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

        foreach ($roomDataArr as $roomData) {
            $room = $rooms[$roomData->room_id];
            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'price_per_night' => $room->price_per_night,
                'adults' => $roomData->adults,
                'children' => $roomData->children,
                'total_guests' => $roomData->adults + $roomData->children,
            ]);
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
