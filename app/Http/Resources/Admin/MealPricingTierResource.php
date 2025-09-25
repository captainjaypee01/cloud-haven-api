<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPricingTierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meal_program_id' => $this->meal_program_id,
            'currency' => $this->currency,
            'adult_price' => (float) $this->adult_price,
            'child_price' => (float) $this->child_price,
            'adult_lunch_price' => $this->adult_lunch_price ? (float) $this->adult_lunch_price : null,
            'child_lunch_price' => $this->child_lunch_price ? (float) $this->child_lunch_price : null,
            'adult_pm_snack_price' => $this->adult_pm_snack_price ? (float) $this->adult_pm_snack_price : null,
            'child_pm_snack_price' => $this->child_pm_snack_price ? (float) $this->child_pm_snack_price : null,
            'adult_dinner_price' => $this->adult_dinner_price ? (float) $this->adult_dinner_price : null,
            'child_dinner_price' => $this->child_dinner_price ? (float) $this->child_dinner_price : null,
            'adult_breakfast_price' => $this->adult_breakfast_price ? (float) $this->adult_breakfast_price : null,
            'child_breakfast_price' => $this->child_breakfast_price ? (float) $this->child_breakfast_price : null,
            'extra_guest_fee' => $this->extra_guest_fee ? (float) $this->extra_guest_fee : null,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
