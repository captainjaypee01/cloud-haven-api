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
            'booking_rooms'             => $this->bookingRooms,
            'payments'                  => $this->payments,
            'other_charges'             => $other_charges,
            'other_charges_list'       => $this->otherCharges,
        ];
        return $data;
    }
}
