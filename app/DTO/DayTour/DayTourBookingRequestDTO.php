<?php

namespace App\DTO\DayTour;

use Spatie\LaravelData\Data;

class DayTourBookingRequestDTO extends Data
{
    /**
     * @param string $date
     * @param DayTourRoomSelectionDTO[] $selections
     * @param DayTourGuestDTO $guest
     * @param string|null $special_requests
     * @param array|null $totals
     */
    public function __construct(
        public string $date,
        public array $selections,
        public DayTourGuestDTO $guest,
        public ?string $special_requests = null,
        public ?array $totals = null,
        public ?int $promo_id = null
    ) {}

    public static function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.room_id' => ['required', 'string', 'exists:rooms,slug'],
            'selections.*.adults' => ['required', 'integer', 'min:1'],
            'selections.*.children' => ['required', 'integer', 'min:0'],
            'selections.*.include_lunch' => ['required', 'boolean'],
            'selections.*.include_pm_snack' => ['required', 'boolean'],
            'selections.*.lunch_cost' => ['required', 'numeric', 'min:0'],
            'selections.*.pm_snack_cost' => ['required', 'numeric', 'min:0'],
            'selections.*.meal_cost' => ['required', 'numeric', 'min:0'],
            'guest' => ['required', 'array'],
            'guest.name' => ['required', 'string', 'max:255'],
            'guest.email' => ['required', 'email', 'max:255'],
            'guest.phone' => ['nullable', 'string', 'max:20'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'totals' => ['nullable', 'array'],
            'totals.room_total' => ['nullable', 'numeric', 'min:0'],
            'totals.meal_total' => ['nullable', 'numeric', 'min:0'],
            'totals.grand_total' => ['nullable', 'numeric', 'min:0'],
            'promo_id' => ['nullable', 'integer', 'exists:promos,id'],
        ];
    }
}
