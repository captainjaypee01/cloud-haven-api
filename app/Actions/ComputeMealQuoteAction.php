<?php

namespace App\Actions;

use App\Contracts\Services\MealPricingServiceInterface;
use App\DTO\MealQuoteDTO;
use Carbon\Carbon;

class ComputeMealQuoteAction
{
    public function __construct(
        private MealPricingServiceInterface $mealPricingService
    ) {}

    public function execute(string $checkIn, string $checkOut): MealQuoteDTO
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        // Validate dates
        if ($checkOutDate->lte($checkInDate)) {
            throw new \InvalidArgumentException('Check-out date must be after check-in date');
        }

        return $this->mealPricingService->getMealProgramInfoForStay(
            $checkInDate,
            $checkOutDate
        );
    }
}
