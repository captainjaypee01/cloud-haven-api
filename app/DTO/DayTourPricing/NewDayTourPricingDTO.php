<?php

namespace App\DTO\DayTourPricing;

use Spatie\LaravelData\Data;

class NewDayTourPricingDTO extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public float $price_per_pax,
        public string $effective_from,
        public ?string $effective_until,
        public bool $is_active = true
    ) {}

    public static function rules(): array
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
}
