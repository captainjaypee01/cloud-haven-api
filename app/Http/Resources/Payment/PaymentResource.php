<?php

namespace App\Http\Resources\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'provider' => $this->provider,
            'status' => $this->status,
            'amount' => $this->amount,
            'transaction_id' => $this->transaction_id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'proof_status' => $this->proof_status ?? 'none',
            'proof_upload_count' => $this->proof_upload_count ?? 0,
            'proof_rejected_reason' => $this->proof_rejected_reason,
            'proof_last_file_path' => $this->proof_last_file_path,
            'proof_image_path' => $this->proof_image_path,
            'proof_image_url' => $this->proof_image_url,
            'created_at' => $this->created_at,
            'local_created_at' => $this->local_created_at,
            
            // Booking relationship
            'booking' => $this->whenLoaded('booking', function () {
                return [
                    'id' => $this->booking->id,
                    'reference_number' => $this->booking->reference_number,
                    'guest_name' => $this->booking->guest_name,
                    'guest_email' => $this->booking->guest_email,
                ];
            }),
            
            // Rejected by user relationship
            'rejected_by_user' => $this->whenLoaded('rejectedByUser', function () {
                return [
                    'id' => $this->rejectedByUser->id,
                    'name' => $this->rejectedByUser->name,
                ];
            }),
        ];
    }
}
