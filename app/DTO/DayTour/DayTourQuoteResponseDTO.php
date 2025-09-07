<?php

namespace App\DTO\DayTour;

class DayTourQuoteResponseDTO
{
    /**
     * @param string $date
     * @param bool $buffetActive
     * @param string $pmSnackPolicy
     * @param DayTourQuoteItemDTO[] $items
     * @param DayTourTotalsDTO $totals
     * @param string[] $notes
     */
    public function __construct(
        public string $date,
        public bool $buffetActive,
        public string $pmSnackPolicy,
        public array $items,
        public DayTourTotalsDTO $totals,
        public array $notes = []
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'buffet_active' => $this->buffetActive,
            'pm_snack_policy' => $this->pmSnackPolicy,
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'totals' => $this->totals->toArray(),
            'notes' => $this->notes,
        ];
    }
}
