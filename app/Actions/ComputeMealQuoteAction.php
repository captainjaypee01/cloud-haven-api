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

    public function execute(string $checkIn, string $checkOut, int $adults, int $children): MealQuoteDTO
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        // Validate dates
        if ($checkOutDate->lte($checkInDate)) {
            throw new \InvalidArgumentException('Check-out date must be after check-in date');
        }

        // Validate party size
        if ($adults < 1) {
            throw new \InvalidArgumentException('At least one adult is required');
        }

        if ($children < 0) {
            throw new \InvalidArgumentException('Number of children cannot be negative');
        }

        return $this->mealPricingService->quoteForStay(
            $checkInDate,
            $checkOutDate,
            $adults,
            $children
        );
    }
}
