<?php

namespace App\Http\Requests\DayTourPricing;

use Illuminate\Foundation\Http\FormRequest;

class StoreDayTourPricingRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_per_pax' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The pricing name is required.',
            'name.max' => 'The pricing name may not be greater than 255 characters.',
            'price_per_pax.required' => 'The price per person is required.',
            'price_per_pax.numeric' => 'The price per person must be a valid number.',
            'price_per_pax.min' => 'The price per person must be at least 0.',
            'effective_from.required' => 'The effective from date is required.',
            'effective_from.date' => 'The effective from date must be a valid date.',
            'effective_until.date' => 'The effective until date must be a valid date.',
            'effective_until.after' => 'The effective until date must be after the effective from date.',
        ];
    }
}