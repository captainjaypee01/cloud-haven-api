<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $downpaymentPercent = config('booking.downpayment_percent', 0.5);
        $downpaymentAmount = round($this->final_price * $downpaymentPercent);
        $bookingData = [
            'user'                      => $this->user_id ? true : false,
            'reference_number'          => $this->reference_number,
            'check_in_date'             => $this->check_in_date,
            'check_in_time'             => $this->check_in_time,
            'check_out_date'             => $this->check_out_date,
            'check_out_time'             => $this->check_out_time,
            'guest_name'             => $this->guest_name,
            'guest_email'             => $this->guest_email,
            'guest_phone'             => $this->guest_phone,
            'special_requests'          => $this->special_requests,
            'adults'          => $this->adults,
            'children'          => $this->children,
            'total_guests'          => $this->total_guests,
            'total_price'          => $this->total_price,
            'meal_price'          => $this->meal_price,
            'discount_amount'          => $this->discount_amount,
            'payment_option'          => $this->payment_option,
            'downpayment_amount'          => $this->downpayment_amount,
            'final_price'          => $this->final_price,
            'status'          => $this->status,
            'is_reviewed'          => $this->is_reviewed,
            'failed_payment_attempts'          => $this->failed_payment_attempts,
            'reserved_until'          => $this->local_reserved_until,
            'paid_at'          => $this->local_paid_at,
            'booking_rooms'          => $this->bookingRooms,
            'other_charges' => $this->otherCharges,
        ];
        return array_merge($bookingData, [
            'final_price' => $this->final_price,
            'downpayment_percent' => $downpaymentPercent,
            'downpayment_amount' => $downpaymentAmount,
            'created_at' => $this->local_created_at,
            'payments'  => $this->payments
                // ->where('status', 'paid')
                ->sortByDesc('created_at')  // latest payment first
                ->values() // reindex keys (important for JSON)
                ->map(function ($payment) {
                    return [
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'paid_at' => $payment->local_created_at,
                        // add any other payment fields you need
                    ];
                }),
            'pay_now_options' => [
                [
                    'label'     => 'Downpayment',
                    'amount'    => $downpaymentAmount,
                    'type'      => 'downpayment',
                    'value'     => 'downpayment',
                ],
                [
                    'label'     => 'Full Payment',
                    'amount'    => $this->final_price,
                    'type'      => 'full',
                    'value'     => 'full',
                ],
            ],
        ]);
    }
}
