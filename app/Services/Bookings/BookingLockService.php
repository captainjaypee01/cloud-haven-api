<?php
namespace App\Services\Bookings;

use App\Contracts\Services\BookingLockServiceInterface;
use Illuminate\Support\Facades\Redis;

class BookingLockService implements BookingLockServiceInterface
{
    protected $ttl;

    public function __construct()
    {
        // Convert hours to seconds, default to 2 hours
        $hours = config('booking.reservation_hold_duration_hours', 2);
        $this->ttl = $hours * 3600; // Convert hours to seconds
    }

    public function lock(string $bookingId, array $data): void
    {
        Redis::setex($this->key($bookingId), $this->ttl, json_encode($data));
    }

    public function get(string $bookingId): ?array
    {
        $value = Redis::get($this->key($bookingId));
        return $value ? json_decode($value, true) : null;
    }

    public function delete(string $bookingId): void
    {
        Redis::del($this->key($bookingId));
    }

    public function getTTL(string $bookingId): int
    {
        return Redis::ttl($this->key($bookingId));
    }

    protected function key(string $bookingId): string
    {
        return "booking_lock:{$bookingId}";
    }
}
