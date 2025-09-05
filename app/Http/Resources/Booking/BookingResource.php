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
        $data = [
            'id'                        => $this->id,
            'reference_number'          => $this->reference_number,
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
            'meal_quote_data'           => $this->meal_quote_data,
            'discount_amount'           => $this->discount_amount,
            'downpayment_amount'        => $this->downpayment_amount,
            'final_price'               => $this->final_price,
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
            'booking_rooms'             => $this->bookingRooms,
            'payments'                  => $this->payments->map(function ($payment) {
                return array_merge($payment->toArray(), [
                    'booking' => [
                        'reference_number' => $this->reference_number,
                        'id' => $this->id
                    ]
                ]);
            }),
            'other_charges'             => $other_charges,
            'other_charges_list'       => $this->otherCharges,
        ];
        return $data;
    }
}
