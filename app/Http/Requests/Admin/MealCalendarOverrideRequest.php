<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MealCalendarOverrideRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'override_type' => 'required|in:date,month',
            'date' => 'required_if:override_type,date|nullable|date',
            'month' => 'required_if:override_type,month|nullable|integer|min:1|max:12',
            'year' => 'required_if:override_type,month|nullable|integer|min:2020|max:2030',
            'is_active' => 'required|boolean',
            'note' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'override_type.required' => 'Please specify the override type (date or month).',
            'override_type.in' => 'Override type must be either "date" or "month".',
            'date.required_if' => 'Date is required for date-specific overrides.',
            'month.required_if' => 'Month is required for month-wide overrides.',
            'year.required_if' => 'Year is required for month-wide overrides.',
            'month.min' => 'Month must be between 1 and 12.',
            'month.max' => 'Month must be between 1 and 12.',
            'year.min' => 'Year must be between 2020 and 2030.',
            'year.max' => 'Year must be between 2020 and 2030.',
            'is_active.required' => 'Please specify whether the buffet should be active or inactive.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}
