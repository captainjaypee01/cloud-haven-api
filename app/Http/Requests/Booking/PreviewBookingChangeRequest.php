<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewBookingChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'change_type' => ['required', Rule::in(['adjust_nights', 'reschedule', 'modify'])],
            'new_check_out_date' => ['required_if:change_type,adjust_nights', 'date'],
            'check_in_date' => ['required_if:change_type,reschedule', 'date'],
            'check_out_date' => ['required_if:change_type,reschedule', 'date'],
            'rooms' => ['required_if:change_type,modify', 'array', 'min:1'],
            'rooms.*.room_id' => ['required_with:rooms', 'string', 'exists:rooms,slug'],
            'rooms.*.adults' => ['required_with:rooms', 'integer', 'min:1'],
            'rooms.*.children' => ['required_with:rooms', 'integer', 'min:0'],
            'rooms.*.total_guests' => ['required_with:rooms', 'integer', 'min:1'],
        ];
    }
}
