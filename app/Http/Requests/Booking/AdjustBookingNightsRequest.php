<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class AdjustBookingNightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_check_out_date' => ['required', 'date'],
            'modification_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_check_out_date.required' => 'New check-out date is required.',
            'new_check_out_date.date' => 'New check-out date must be a valid date.',
        ];
    }
}
