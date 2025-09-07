<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealProgramResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'scope_type' => $this->scope_type,
            'date_start' => $this->date_start?->format('Y-m-d'),
            'date_end' => $this->date_end?->format('Y-m-d'),
            'months' => $this->months,
            'weekdays' => $this->weekdays,
            'weekend_definition' => $this->weekend_definition,
            'pm_snack_policy' => $this->pm_snack_policy,
            'inactive_label' => $this->inactive_label,
            'notes' => $this->notes,
            'pricing_tiers' => MealPricingTierResource::collection($this->whenLoaded('pricingTiers')),
            'calendar_overrides' => MealCalendarOverrideResource::collection($this->whenLoaded('calendarOverrides')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
