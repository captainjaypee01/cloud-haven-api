<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'room_id' => $this->room_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'type' => $this->type,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_testimonial' => $this->is_testimonial,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'email' => $this->user->email,
                ];
            }),
            'room' => $this->whenLoaded('room', function () {
                return [
                    'id' => $this->room->id,
                    'name' => $this->room->name,
                    'room_type' => $this->room->room_type,
                ];
            }),
            'booking' => $this->whenLoaded('booking', function () {
                return [
                    'id' => $this->booking->id,
                    'reference_number' => $this->booking->reference_number,
                    'booking_type' => $this->booking->booking_type,
                ];
            }),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
