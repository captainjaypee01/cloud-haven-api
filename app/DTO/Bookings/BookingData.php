<?php
namespace App\Dto\Bookings;

use Spatie\LaravelData\Data;

class BookingData extends Data
{
    public function __construct(
        public string $check_in_date,
        public string $check_in_time,
        public string $check_out_date,
        public string $check_out_time,
        public array $rooms, // array of BookingRoomData
        public string $guest_name,
        public string $guest_email,
        public ?string $guest_phone,
        public ?string $special_requests,
        public int $total_adults,
        public int $total_children,
        public ?int $promo_id = null,
    ) {}
}