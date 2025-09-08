<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MealPricingTierRequest extends FormRequest
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
            'currency' => 'required|string|size:3',
            'adult_price' => 'required|numeric|min:0|max:999999.99',
            'child_price' => 'required|numeric|min:0|max:999999.99',
            'adult_lunch_price' => 'nullable|numeric|min:0|max:999999.99',
            'child_lunch_price' => 'nullable|numeric|min:0|max:999999.99',
            'adult_pm_snack_price' => 'nullable|numeric|min:0|max:999999.99',
            'child_pm_snack_price' => 'nullable|numeric|min:0|max:999999.99',
            'adult_dinner_price' => 'nullable|numeric|min:0|max:999999.99',
            'child_dinner_price' => 'nullable|numeric|min:0|max:999999.99',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'currency.size' => 'Currency must be a 3-letter code (e.g., SGD, USD).',
            'effective_to.after' => 'Effective to date must be after effective from date.',
        ];
    }
}
