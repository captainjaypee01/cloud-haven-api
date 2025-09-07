<?php

namespace App\Contracts\Services;

use App\DTO\DayTourPricing\NewDayTourPricingDTO;
use App\DTO\DayTourPricing\UpdateDayTourPricingDTO;
use App\Models\DayTourPricing;
use Illuminate\Pagination\LengthAwarePaginator;

interface DayTourPricingServiceInterface
{
    /**
     * Get paginated Day Tour Pricing records
     */
    public function list(array $filters): LengthAwarePaginator;

    /**
     * Get Day Tour Pricing by ID
     */
    public function show(int $id): DayTourPricing;

    /**
     * Create new Day Tour Pricing
     */
    public function create(NewDayTourPricingDTO $dto): DayTourPricing;

    /**
     * Update Day Tour Pricing
     */
    public function update(int $id, UpdateDayTourPricingDTO $dto): DayTourPricing;

    /**
     * Delete Day Tour Pricing
     */
    public function delete(int $id): void;

    /**
     * Toggle active status
     */
    public function toggleStatus(int $id): DayTourPricing;

    /**
     * Get active pricing for a specific date
     */
    public function getActivePricingForDate(string $date): ?DayTourPricing;

    /**
     * Get current active pricing
     */
    public function getCurrentActivePricing(): ?DayTourPricing;
}
