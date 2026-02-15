<?php

namespace App\Http\Requests\Booking;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class WalkInBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $maxNights = 5;

        return [
            'booking_type' => 'required|in:day_tour,overnight',
            'nights' => 'required_if:booking_type,overnight|integer|min:1|max:' . $maxNights,
            'local_date' => 'required|date',
            'rooms' => 'required|array|min:1',
            'rooms.*.room_id' => 'required|string|exists:rooms,slug',
            'rooms.*.quantity' => 'required|integer|min:1',
            'rooms.*.adults' => 'required|integer|min:1',
            'rooms.*.children' => 'required|integer|min:0',
            'rooms.*.include_lunch' => 'nullable|boolean',
            'rooms.*.include_pm_snack' => 'nullable|boolean',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'required|string|max:20',
            'special_requests' => 'nullable|string|max:1000',
            'promo_id' => 'nullable|integer|exists:promos,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'booking_type.required' => 'Please select a booking type (Day Tour or Overnight).',
            'booking_type.in' => 'Booking type must be either Day Tour or Overnight.',
            'nights.required_if' => 'Number of nights is required for overnight bookings.',
            'nights.max' => 'Overnight bookings are limited to a maximum of 5 nights.',
            'local_date.required' => 'Local date is required.',
            'local_date.date' => 'Please provide a valid date.',
            'rooms.required' => 'At least one room must be selected.',
            'rooms.*.room_id.required' => 'Room selection is required.',
            'rooms.*.room_id.exists' => 'Selected room does not exist.',
            'rooms.*.quantity.required' => 'Room quantity is required.',
            'rooms.*.quantity.min' => 'Room quantity must be at least 1.',
            'rooms.*.adults.required' => 'Number of adults is required.',
            'rooms.*.adults.min' => 'At least 1 adult is required per room.',
            'guest_name.required' => 'Guest name is required.',
            'guest_email.required' => 'Guest email is required.',
            'guest_email.email' => 'Please provide a valid email address.',
            'guest_phone.required' => 'Guest phone number is required.',
        ];
    }


    /**
     * Get the validated data with additional computed fields.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        
        // Use the local_date provided by the frontend instead of server's today()
        $localDate = $validated['local_date'];
        
        // Ensure dates are set correctly using the local date
        $validated['check_in_date'] = $localDate;
        
        if ($validated['booking_type'] === 'overnight' && isset($validated['nights'])) {
            $nights = (int) $validated['nights'];
            $validated['check_out_date'] = Carbon::parse($localDate)->addDays($nights)->format('Y-m-d');
        } else {
            $validated['check_out_date'] = $localDate;
        }

        // Calculate total guests for each room and overall totals
        $totalAdults = 0;
        $totalChildren = 0;
        
        foreach ($validated['rooms'] as $index => $room) {
            $adults = (int) ($room['adults'] ?? 0);
            $children = (int) ($room['children'] ?? 0);
            $quantity = (int) ($room['quantity'] ?? 1);
            
            $validated['rooms'][$index]['total_guests'] = $adults + $children;
            
            // Add to overall totals
            $totalAdults += $adults * $quantity;
            $totalChildren += $children * $quantity;
        }

        // Set booking-level totals
        $validated['total_adults'] = $totalAdults;
        $validated['total_children'] = $totalChildren;

        // Set booking source as walk-in
        $validated['booking_source'] = 'walkin';
        
        // Set user_id to null for walk-in bookings
        $validated['user_id'] = null;

        return $validated;
    }
}
