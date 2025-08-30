<?php

namespace App\DTO\RoomUnits;

use Spatie\LaravelData\Data;

class GenerateUnitsData extends Data
{
    public function __construct(
        /** @var array<array{prefix?: string, start: int, end: int}>|null */
        public ?array $ranges = null,
        
        /** @var string[]|null */
        public ?array $numbers = null,
        
        public bool $skip_existing = false,
    ) {}

    public static function rules(): array
    {
        return [
            'ranges' => ['nullable', 'array'],
            'ranges.*.prefix' => ['nullable', 'string', 'max:10'],
            'ranges.*.start' => ['required_with:ranges', 'integer', 'min:1'],
            'ranges.*.end' => ['required_with:ranges', 'integer', 'min:1'],
            'numbers' => ['nullable', 'array'],
            'numbers.*' => ['string', 'max:50'],
            'skip_existing' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'ranges.*.start.required_with' => 'Range start is required when ranges are provided.',
            'ranges.*.end.required_with' => 'Range end is required when ranges are provided.',
            'ranges.*.start.min' => 'Range start must be at least 1.',
            'ranges.*.end.min' => 'Range end must be at least 1.',
        ];
    }
}
