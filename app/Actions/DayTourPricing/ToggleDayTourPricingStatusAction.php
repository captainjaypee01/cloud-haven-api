<?php

namespace App\Actions\DayTourPricing;

use App\Contracts\Services\DayTourPricingServiceInterface;
use App\Models\DayTourPricing;

class ToggleDayTourPricingStatusAction
{
    public function __construct(
        private readonly DayTourPricingServiceInterface $dayTourPricingService
    ) {}

    public function execute(int $id): DayTourPricing
    {
        return $this->dayTourPricingService->toggleStatus($id);
    }
}
