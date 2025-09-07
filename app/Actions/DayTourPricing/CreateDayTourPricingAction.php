<?php

namespace App\Actions\DayTourPricing;

use App\Contracts\Services\DayTourPricingServiceInterface;
use App\DTO\DayTourPricing\NewDayTourPricingDTO;
use App\Models\DayTourPricing;

class CreateDayTourPricingAction
{
    public function __construct(
        private readonly DayTourPricingServiceInterface $dayTourPricingService
    ) {}

    public function execute(NewDayTourPricingDTO $dto): DayTourPricing
    {
        return $this->dayTourPricingService->create($dto);
    }
}
