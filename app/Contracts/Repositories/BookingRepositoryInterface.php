<?php

namespace App\Contracts\Repositories;

use App\Models\Booking;
use Illuminate\Pagination\LengthAwarePaginator;

interface BookingRepositoryInterface
{
    public function getByReferenceNumber(string $referenceNumber): Booking;
    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator;
    public function getId(int $id): Booking;
}
