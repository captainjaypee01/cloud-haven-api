<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class BookingModificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['required', 'string', 'exists:rooms,slug'],
            'rooms.*.adults' => ['required', 'integer', 'min:1', 'max:10'],
            'rooms.*.children' => ['required', 'integer', 'min:0', 'max:10'],
            'rooms.*.total_guests' => ['required', 'integer', 'min:1', 'max:12'],
            'rooms.*.room_unit_id' => ['nullable', 'integer', 'exists:room_units,id'],
            'modification_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rooms.required' => 'At least one room is required.',
            'rooms.min' => 'At least one room must be selected.',
            'rooms.*.room_id.required' => 'Room selection is required.',
            'rooms.*.room_id.exists' => 'Selected room is not available.',
            'rooms.*.adults.required' => 'Number of adults is required.',
            'rooms.*.adults.min' => 'At least 1 adult is required.',
            'rooms.*.adults.max' => 'Maximum 10 adults allowed per room.',
            'rooms.*.children.min' => 'Number of children cannot be negative.',
            'rooms.*.children.max' => 'Maximum 10 children allowed per room.',
            'rooms.*.total_guests.required' => 'Total guests count is required.',
            'rooms.*.total_guests.min' => 'At least 1 guest is required.',
            'rooms.*.total_guests.max' => 'Maximum 12 guests allowed per room.',
            'rooms.*.room_unit_id.exists' => 'Selected room unit is not valid.',
            'modification_reason.max' => 'Modification reason cannot exceed 500 characters.',
        ];
    }
}
