<?php

namespace App\Actions\DayTourPricing;

use App\Contracts\Services\DayTourPricingServiceInterface;
use App\DTO\DayTourPricing\UpdateDayTourPricingDTO;
use App\Models\DayTourPricing;

class UpdateDayTourPricingAction
{
    public function __construct(
        private readonly DayTourPricingServiceInterface $dayTourPricingService
    ) {}

    public function execute(int $id, UpdateDayTourPricingDTO $dto): DayTourPricing
    {
        return $this->dayTourPricingService->update($id, $dto);
    }
}
