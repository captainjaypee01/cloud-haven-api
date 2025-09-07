<?php

namespace App\Contracts\Services;

use App\DTO\DayTour\DayTourAvailabilityResponseDTO;
use App\DTO\DayTour\DayTourQuoteRequestDTO;
use App\DTO\DayTour\DayTourQuoteResponseDTO;
use Carbon\Carbon;

interface DayTourServiceInterface
{
    /**
     * Get availability for Day Tour rooms on a specific date
     *
     * @param Carbon $date
     * @return DayTourAvailabilityResponseDTO
     */
    public function getAvailabilityForDate(Carbon $date): DayTourAvailabilityResponseDTO;

    /**
     * Generate a quote for Day Tour selections
     *
     * @param DayTourQuoteRequestDTO $request
     * @return DayTourQuoteResponseDTO
     */
    public function quoteDayTour(DayTourQuoteRequestDTO $request): DayTourQuoteResponseDTO;

    /**
     * Validate that all room IDs are Day Tour type
     *
     * @param array $roomIds
     * @throws \InvalidArgumentException if any room is not day_tour type
     */
    public function validateDayTourRooms(array $roomSlugs): void;
}
