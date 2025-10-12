<?php

namespace App\Http\Requests\DayTour;

use App\DTO\DayTour\DayTourBookingModificationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Room;

class DayTourBookingModificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['required', 'string', 'exists:rooms,slug'],
            'rooms.*.room_unit_id' => ['required', 'integer', 'exists:room_units,id'],
            'rooms.*.adults' => ['required', 'integer', 'min:1', 'max:10'],
            'rooms.*.children' => ['required', 'integer', 'min:0', 'max:10'],
            'rooms.*.include_lunch' => ['required', 'boolean'],
            'rooms.*.include_pm_snack' => ['required', 'boolean'],
            'modification_reason' => ['required', 'string', 'max:1000'],
            'send_email' => ['boolean'],
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
            
            // Validate room capacity for each room
            foreach ($rooms as $index => $roomData) {
                if (isset($roomData['room_id']) && isset($roomData['adults']) && isset($roomData['children'])) {
                    $room = Room::where('slug', $roomData['room_id'])->first();
                    
                    if ($room) {
                        $totalGuests = $roomData['adults'] + $roomData['children'];
                        $maxCapacity = $room->max_guests + ($room->extra_guests ?? 0);
                        
                        if ($totalGuests > $maxCapacity) {
                            $extraGuests = $room->extra_guests ?? 0;
                            $validator->errors()->add(
                                "rooms.{$index}.adults",
                                "Room '{$room->name}' can accommodate maximum {$maxCapacity} guests (Max: {$room->max_guests}, Extra: {$extraGuests}). You have {$totalGuests} guests."
                            );
                        }

                        // Validate minimum guests
                        if ($totalGuests < 1) {
                            $validator->errors()->add(
                                "rooms.{$index}.adults",
                                "At least 1 guest is required per room."
                            );
                        }
                    }
                }
            }

            // Validate for duplicate room unit selections
            $selectedUnits = [];
            foreach ($rooms as $index => $roomData) {
                if (isset($roomData['room_unit_id'])) {
                    if (in_array($roomData['room_unit_id'], $selectedUnits)) {
                        $validator->errors()->add(
                            "rooms.{$index}.room_unit_id",
                            "Room unit is already selected for another room."
                        );
                    } else {
                        $selectedUnits[] = $roomData['room_unit_id'];
                    }
                }
            }

            // Validate that all rooms are Day Tour type
            foreach ($rooms as $index => $roomData) {
                if (isset($roomData['room_id'])) {
                    $room = Room::where('slug', $roomData['room_id'])->first();
                    if ($room && $room->room_type !== 'day_tour') {
                        $validator->errors()->add(
                            "rooms.{$index}.room_id",
                            "Room '{$room->name}' is not a Day Tour room."
                        );
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
            'rooms.*.room_unit_id.required' => 'Room unit selection is required.',
            'rooms.*.room_unit_id.exists' => 'Selected room unit is not valid.',
            'rooms.*.adults.required' => 'Number of adults is required.',
            'rooms.*.adults.min' => 'At least 1 adult is required.',
            'rooms.*.adults.max' => 'Maximum 10 adults allowed per room.',
            'rooms.*.children.min' => 'Number of children cannot be negative.',
            'rooms.*.children.max' => 'Maximum 10 children allowed per room.',
            'rooms.*.include_lunch.required' => 'Lunch selection is required.',
            'rooms.*.include_pm_snack.required' => 'PM snack selection is required.',
            'modification_reason.required' => 'Modification reason is required.',
            'modification_reason.max' => 'Modification reason cannot exceed 1000 characters.',
        ];
    }

    public function toDTO(): DayTourBookingModificationData
    {
        return DayTourBookingModificationData::from($this->validated());
    }
}
