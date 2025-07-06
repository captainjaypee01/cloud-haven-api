<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
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
        return [
            'check_in_date' => 'required|date',
            'check_in_time' => 'sometimes',
            'check_out_date' => 'required|date|after:check_in_date',
            'check_out_time' => 'sometimes',
            'rooms' => 'required|array|min:1',
            'rooms.*.room_id' => 'required|string|exists:rooms,slug',
            'rooms.*.adults' => 'required|integer|min:0',
            'rooms.*.children' => 'required|integer|min:0',
            'guest_name' => 'required|string',
            'guest_email' => 'required|email',
            'guest_phone' => 'nullable|string',
            'special_requests' => 'nullable|string',
            'total_adults' => 'required|integer|min:0',
            'total_children' => 'required|integer|min:0',
            'promo_id' => 'nullable|integer|exists:promos,id',
        ];
    }
}
