<?php

namespace App\Actions\DayTour;

use App\Contracts\Services\DayTourServiceInterface;
use App\DTO\DayTour\DayTourQuoteRequestDTO;
use App\DTO\DayTour\DayTourQuoteResponseDTO;

class ComputeDayTourQuoteAction
{
    public function __construct(
        private DayTourServiceInterface $dayTourService
    ) {}

    public function execute(DayTourQuoteRequestDTO $request): DayTourQuoteResponseDTO
    {
        // Validate all rooms are Day Tour type
        $roomIds = array_map(fn($selection) => $selection->room_id, $request->selections);
        $this->dayTourService->validateDayTourRooms($roomIds);
        
        // Generate the quote
        return $this->dayTourService->quoteDayTour($request);
    }
}
