<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Room;

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
            'send_email' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();
            $rooms = $data['rooms'] ?? [];
            
            foreach ($rooms as $index => $roomData) {
                if (isset($roomData['room_id']) && isset($roomData['total_guests'])) {
                    $room = Room::where('slug', $roomData['room_id'])->first();
                    
                    if ($room) {
                        $maxCapacity = $room->max_guests + ($room->extra_guests ?? 0);
                        
                        if ($roomData['total_guests'] > $maxCapacity) {
                            $validator->errors()->add(
                                "rooms.{$index}.total_guests",
                                "Room '{$room->name}' can accommodate maximum {$maxCapacity} guests (Max: {$room->max_guests}, Extra: {$room->extra_guests}). You have {$roomData['total_guests']} guests."
                            );
                        }
                    }
                }
            }
        });
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
