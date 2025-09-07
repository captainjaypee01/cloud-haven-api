<?php

namespace App\Actions\DayTourPricing;

use App\Contracts\Services\DayTourPricingServiceInterface;

class DeleteDayTourPricingAction
{
    public function __construct(
        private readonly DayTourPricingServiceInterface $dayTourPricingService
    ) {}

    public function execute(int $id): void
    {
        $this->dayTourPricingService->delete($id);
    }
}
