<?php

namespace App\Actions\Bookings;

use App\Services\Bookings\BookingBalanceService;

class SyncBookingDownpaymentAction
{
    public function __construct(
        private readonly BookingBalanceService $bookingBalance,
    ) {}

    public function calculateFromTotals(array $totals): float
    {
        $discountAmount = (float) ($totals['promo_discount']['discount_amount'] ?? 0);
        $netStayTotal = max(0, round((float) $totals['final_price'] - $discountAmount, 2));

        return $this->bookingBalance->calculateDownpaymentAmount($netStayTotal);
    }
}
