<?php

namespace App\Services\Bookings;

use App\Models\Booking;

class BookingBalanceService
{
    public function getDownpaymentPercent(): float
    {
        return (float) config('booking.downpayment_percent', 0.5);
    }

    public function getAmountPaid(Booking $booking): float
    {
        $booking->loadMissing('payments');

        return (float) $booking->payments->where('status', 'paid')->sum('amount');
    }

    public function getOtherChargesTotal(Booking $booking): float
    {
        $booking->loadMissing('otherCharges');

        return (float) $booking->otherCharges->sum('amount');
    }

    /**
     * Guest-facing amount due before other charges (matches email / checkout logic).
     */
    public function getNetStayTotal(Booking $booking, ?float $finalPrice = null, ?float $discountAmount = null): float
    {
        $final = $finalPrice ?? (float) $booking->final_price;
        $discount = $discountAmount ?? (float) ($booking->discount_amount ?? 0);
        $pwd = (float) ($booking->pwd_senior_discount ?? 0);
        $special = (float) ($booking->special_discount ?? 0);

        return max(0, round($final - $discount - $pwd - $special, 2));
    }

    public function calculateDownpaymentAmount(float $netStayTotal): float
    {
        return round($netStayTotal * $this->getDownpaymentPercent(), 2);
    }

    public function getTotalPayable(Booking $booking, ?float $netStayTotal = null): float
    {
        $net = $netStayTotal ?? $this->getNetStayTotal($booking);

        return round($net + $this->getOtherChargesTotal($booking), 2);
    }

    public function getRemainingBalance(Booking $booking, ?float $totalPayable = null): float
    {
        $payable = $totalPayable ?? $this->getTotalPayable($booking);

        return max(0, round($payable - $this->getAmountPaid($booking), 2));
    }

    /**
     * @param  array{final_price: float, discount_amount?: float}  $pricing
     */
    public function buildBalanceSnapshot(Booking $booking, array $pricing): array
    {
        $finalPrice = (float) $pricing['final_price'];
        $discountAmount = (float) ($pricing['discount_amount'] ?? 0);
        $netStayTotal = $this->getNetStayTotal($booking, $finalPrice, $discountAmount);
        $downpaymentRequired = $this->calculateDownpaymentAmount($netStayTotal);
        $amountPaid = $this->getAmountPaid($booking);
        $totalPayable = $this->getTotalPayable($booking, $netStayTotal);
        $remainingBalance = max(0, round($totalPayable - $amountPaid, 2));
        $downpaymentMet = $amountPaid >= ($downpaymentRequired - 0.01);
        $shortfallAmount = $downpaymentMet ? 0.0 : round(max(0, $downpaymentRequired - $amountPaid), 2);

        return [
            'final_price' => round($finalPrice, 2),
            'discount_amount' => round($discountAmount, 2),
            'net_stay_total' => $netStayTotal,
            'total_payable' => $totalPayable,
            'amount_paid' => round($amountPaid, 2),
            'remaining_balance' => $remainingBalance,
            'downpayment_required' => $downpaymentRequired,
            'downpayment_met' => $downpaymentMet,
            'downpayment_shortfall' => $shortfallAmount,
            'other_charges_total' => $this->getOtherChargesTotal($booking),
        ];
    }

    /**
     * @param  array{final_price: float, discount_amount?: float, total_room?: float, meal_total?: float, extra_guest_fee?: float}  $proposedTotals
     */
    public function compareCurrentAndProposed(Booking $booking, array $proposedTotals): array
    {
        $current = $this->buildBalanceSnapshot($booking, [
            'final_price' => (float) $booking->final_price,
            'discount_amount' => (float) ($booking->discount_amount ?? 0),
        ]);

        $proposedDiscount = (float) ($proposedTotals['promo_discount']['discount_amount']
            ?? $proposedTotals['discount_amount']
            ?? $booking->discount_amount
            ?? 0);

        $proposed = $this->buildBalanceSnapshot($booking, [
            'final_price' => (float) $proposedTotals['final_price'],
            'discount_amount' => $proposedDiscount,
        ]);

        return [
            'current' => $current,
            'proposed' => array_merge($proposed, [
                'total_room' => round((float) ($proposedTotals['total_room'] ?? $booking->total_price), 2),
                'meal_total' => round((float) ($proposedTotals['meal_total'] ?? $booking->meal_price), 2),
                'extra_guest_fee' => round((float) ($proposedTotals['extra_guest_fee'] ?? $booking->extra_guest_fee), 2),
                'delta_final' => round($proposed['final_price'] - $current['final_price'], 2),
                'delta_net_stay_total' => round($proposed['net_stay_total'] - $current['net_stay_total'], 2),
                'delta_remaining_balance' => round($proposed['remaining_balance'] - $current['remaining_balance'], 2),
            ]),
            'requires_downpayment_check' => $this->requiresDownpaymentCheck($booking),
            'downpayment_shortfall' => $proposed['downpayment_shortfall'] > 0 && $this->requiresDownpaymentCheck($booking),
            'shortfall_amount' => $this->requiresDownpaymentCheck($booking) ? $proposed['downpayment_shortfall'] : 0.0,
            'proposed_downpayment_amount' => $proposed['downpayment_required'],
        ];
    }

    public function requiresDownpaymentCheck(Booking $booking): bool
    {
        if (in_array($booking->status, ['downpayment', 'paid'], true)) {
            return true;
        }

        return $this->getAmountPaid($booking) > 0;
    }

    public function assertDownpaymentMetOrAcknowledged(Booking $booking, array $proposedTotals, bool $acknowledged): void
    {
        if (! $this->requiresDownpaymentCheck($booking)) {
            return;
        }

        $comparison = $this->compareCurrentAndProposed($booking, $proposedTotals);

        if ($comparison['downpayment_shortfall'] && ! $acknowledged) {
            throw new \App\Exceptions\DownpaymentShortfallException(
                'The guest has not met the required downpayment for the new booking total. '
                .'Collect payment or acknowledge to proceed.',
                $comparison
            );
        }
    }
}
