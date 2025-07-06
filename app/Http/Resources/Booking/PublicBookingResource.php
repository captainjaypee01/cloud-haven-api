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

        return array_merge(parent::toArray($request), [
            'final_price' => $this->final_price,
            'downpayment_percent' => $downpaymentPercent,
            'downpayment_amount' => $downpaymentAmount,
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
