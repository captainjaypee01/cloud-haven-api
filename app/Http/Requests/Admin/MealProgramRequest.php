<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealProgramRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'scope_type' => ['required', Rule::in(['always', 'date_range', 'months', 'weekly', 'composite'])],
            'date_start' => 'nullable|date|required_if:scope_type,date_range',
            'date_end' => 'nullable|date|after:date_start|required_if:scope_type,date_range',
            'months' => 'nullable|array|required_if:scope_type,months',
            'months.*' => 'integer|min:1|max:12',
            'weekdays' => 'nullable|array',
            'weekdays.*' => Rule::in(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']),
            'weekend_definition' => ['nullable', Rule::in(['SAT_SUN', 'FRI_SUN', 'CUSTOM'])],
            'inactive_label' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'date_start.required_if' => 'Start date is required for date range programs.',
            'date_end.required_if' => 'End date is required for date range programs.',
            'months.required_if' => 'At least one month must be selected for month-based programs.',
            'date_end.after' => 'End date must be after start date.',
        ];
    }
}
