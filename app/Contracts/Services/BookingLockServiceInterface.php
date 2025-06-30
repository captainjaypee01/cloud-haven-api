<?php

namespace App\Contracts\Services;

interface BookingLockServiceInterface
{
    public function lock(string $bookingId, array $data): void;
    public function get(string $bookingId): ?array;
    public function delete(string $bookingId): void;
    public function getTTL(string $bookingId): int;
}
