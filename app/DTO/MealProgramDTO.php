<?php

namespace App\DTO;

use App\Models\MealProgram;
use Carbon\Carbon;

class MealProgramDTO
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $status,
        public string $scopeType,
        public ?Carbon $dateStart,
        public ?Carbon $dateEnd,
        public ?array $months,
        public ?array $weekdays,
        public string $weekendDefinition,
        public string $inactiveLabel,
        public ?string $notes,
        public ?array $pricingTiers = [],
        public ?array $calendarOverrides = []
    ) {}

    public static function fromModel(MealProgram $program): self
    {
        return new self(
            id: $program->id,
            name: $program->name,
            status: $program->status,
            scopeType: $program->scope_type,
            dateStart: $program->date_start,
            dateEnd: $program->date_end,
            months: $program->months,
            weekdays: $program->weekdays,
            weekendDefinition: $program->weekend_definition,
            inactiveLabel: $program->inactive_label,
            notes: $program->notes,
            pricingTiers: $program->relationLoaded('pricingTiers') 
                ? $program->pricingTiers->map(fn($tier) => MealPricingTierDTO::fromModel($tier))->toArray()
                : [],
            calendarOverrides: $program->relationLoaded('calendarOverrides')
                ? $program->calendarOverrides->map(fn($override) => MealOverrideDTO::fromModel($override))->toArray()
                : []
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'scope_type' => $this->scopeType,
            'date_start' => $this->dateStart?->format('Y-m-d'),
            'date_end' => $this->dateEnd?->format('Y-m-d'),
            'months' => $this->months,
            'weekdays' => $this->weekdays,
            'weekend_definition' => $this->weekendDefinition,
            'inactive_label' => $this->inactiveLabel,
            'notes' => $this->notes,
            'pricing_tiers' => array_map(fn($tier) => $tier->toArray(), $this->pricingTiers),
            'calendar_overrides' => array_map(fn($override) => $override->toArray(), $this->calendarOverrides),
        ];
    }
}
