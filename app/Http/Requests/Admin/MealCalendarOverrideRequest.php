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
            'date' => 'required|date',
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
            'is_active.required' => 'Please specify whether the buffet should be active or inactive on this date.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}
