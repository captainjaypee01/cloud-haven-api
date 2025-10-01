<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $other_charges = $this->otherCharges()->sum('amount');
        
        // Calculate remaining balance
        $totalPaid = $this->payments()->where('status', 'paid')->sum('amount');
        $actualFinalPrice = $this->final_price - $this->discount_amount;
        $totalPayable = $actualFinalPrice + $other_charges;
        $remaining_balance = max(0, $totalPayable - $totalPaid);
        
        $data = [
            'id'                        => $this->id,
            'reference_number'          => $this->reference_number,
            'booking_type'              => $this->booking_type ?? 'overnight', // Default to overnight for legacy bookings
            'booking_source'            => $this->booking_source ?? 'online', // Default to online for legacy bookings
            'check_in_date'             => $this->check_in_date,
            'check_out_date'            => $this->check_out_date,
            'guest_name'                => $this->guest_name,
            'guest_email'               => $this->guest_email,
            'guest_phone'               => $this->guest_phone,
            'special_requests'          => $this->special_requests,
            'adults'                    => $this->adults,
            'children'                  => $this->children,
            'total_guests'              => $this->total_guests,
            'promo_id'                  => $this->promo_id,
            'total_price'               => $this->total_price,
            'meal_price'                => $this->meal_price,
            'extra_guest_fee'           => $this->extra_guest_fee,
            'extra_guest_count'         => $this->extra_guest_count,
            'meal_quote_data'           => $this->meal_quote_data,
            'discount_amount'           => $this->discount_amount,
            'downpayment_amount'        => $this->downpayment_amount,
            'final_price'               => $this->final_price,
            'total_paid'                => $totalPaid,
            'remaining_balance'         => $remaining_balance,
            'total_payable'             => $totalPayable,
            'status'                    => $this->status,
            'is_reviewed'               => $this->is_reviewed,
            'failed_payment_attempts'   => $this->failed_payment_attempts,
            'last_payment_failed_at'    => $this->last_payment_failed_at,
            'local_created_at'          => $this->local_created_at,
            'local_updated_at'          => $this->local_updated_at,
            'local_downpayment_at'      => $this->local_downpayment_at,
            'local_paid_at'             => $this->local_paid_at,
            'local_reserved_until'      => $this->local_reserved_until,
            'cancelled_at'              => $this->cancelled_at,
            'local_cancelled_at'        => $this->local_cancelled_at,
            'cancelled_by'              => $this->cancelled_by,
            'cancelled_by_name'         => $this->cancelledByUser ? ($this->cancelledByUser->first_name . ' ' . $this->cancelledByUser->last_name) : null,
            'cancellation_reason'       => $this->cancellation_reason,
            'booking_rooms'             => $this->bookingRooms->map(function ($bookingRoom) {
                return array_merge($bookingRoom->toArray(), [
                    'room_unit' => $bookingRoom->roomUnit ? [
                        'id' => $bookingRoom->roomUnit->id,
                        'unit_number' => $bookingRoom->roomUnit->unit_number,
                        'display_name' => $bookingRoom->roomUnit->display_name,
                    ] : null,
                ]);
            }),
            'payments'                  => $this->payments->map(function ($payment) {
                // Calculate downpayment status
                $downpaymentStatus = $this->calculateDownpaymentStatus($payment);
                
                return array_merge($payment->toArray(), [
                    'downpayment_status' => $downpaymentStatus,
                    'booking' => [
                        'reference_number' => $this->reference_number,
                        'id' => $this->id
                    ]
                ]);
            }),
            'other_charges'             => $other_charges,
            'other_charges_list'       => $this->otherCharges,
            'promo'                     => $this->promo ? [
                'id' => $this->promo->id,
                'code' => $this->promo->code,
                'title' => $this->promo->title,
                'description' => $this->promo->description,
                'discount_type' => $this->promo->discount_type,
                'discount_value' => $this->promo->discount_value,
            ] : null,
        ];
        return $data;
    }

    /**
     * Calculate downpayment status for a payment
     * Returns the manual downpayment_status if set, otherwise calculates based on payment sequence
     */
    private function calculateDownpaymentStatus($payment): ?string
    {
        // If payment has a manual downpayment status, use that
        if ($payment->downpayment_status) {
            return $payment->downpayment_status === 'downpayment' ? 'downpayment' : null;
        }

        // For backward compatibility, calculate based on payment sequence for pending payments
        if ($payment->status !== 'pending') {
            return null;
        }

        // Get all payments for this booking ordered by creation date
        $allPayments = $this->payments->sortBy('created_at');

        // Find the first pending payment
        $firstPendingPayment = $allPayments->firstWhere('status', 'pending');

        // If this payment is not the first pending payment, don't show downpayment status
        if (!$firstPendingPayment || $firstPendingPayment->id !== $payment->id) {
            return null;
        }

        // Check if there are any paid payments before this pending payment
        $paidPaymentsBeforeThis = $allPayments
            ->where('created_at', '<', $payment->created_at)
            ->where('status', 'paid');

        // Only show downpayment status if there are no paid payments before this pending payment
        if ($paidPaymentsBeforeThis->isEmpty()) {
            return 'downpayment';
        }

        return null;
    }
}
