<?php

namespace App\DTO;

use App\Models\MealCalendarOverride;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class MealOverrideDTO extends Data
{
    public function __construct(
        public ?int $id,
        #[MapInputName('meal_program_id')]
        public int $mealProgramId,
        #[MapInputName('override_type')]
        public string $overrideType,
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $date,
        public ?int $month,
        public ?int $year,
        #[MapInputName('is_active')]
        public bool $isActive,
        public ?string $note
    ) {}

    public static function fromModel(MealCalendarOverride $override): self
    {
        return new self(
            id: $override->id,
            mealProgramId: $override->meal_program_id,
            overrideType: $override->override_type ?? 'date',
            date: $override->date,
            month: $override->month,
            year: $override->year,
            isActive: $override->is_active,
            note: $override->note
        );
    }
}
