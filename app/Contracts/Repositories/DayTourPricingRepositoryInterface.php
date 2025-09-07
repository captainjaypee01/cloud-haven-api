<?php

namespace App\Contracts\Repositories;

use App\Models\DayTourPricing;
use Illuminate\Pagination\LengthAwarePaginator;

interface DayTourPricingRepositoryInterface
{
    /**
     * Get paginated Day Tour Pricing records with filters
     */
    public function get(array $filters, ?string $sort = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Find Day Tour Pricing by ID
     */
    public function findById(int $id): ?DayTourPricing;

    /**
     * Create a new Day Tour Pricing record
     */
    public function create(array $data): DayTourPricing;

    /**
     * Update Day Tour Pricing record
     */
    public function update(DayTourPricing $pricing, array $data): DayTourPricing;

    /**
     * Delete Day Tour Pricing record
     */
    public function delete(DayTourPricing $pricing): bool;

    /**
     * Toggle active status
     */
    public function toggleStatus(DayTourPricing $pricing): DayTourPricing;

    /**
     * Get active pricing for a specific date
     */
    public function getActivePricingForDate(string $date): ?DayTourPricing;

    /**
     * Get current active pricing
     */
    public function getCurrentActivePricing(): ?DayTourPricing;
}
