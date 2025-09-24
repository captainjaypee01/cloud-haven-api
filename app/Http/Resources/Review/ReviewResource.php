<?php

namespace App\Http\Resources\Review;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'type' => $this->type,
            'is_testimonial' => $this->is_testimonial,
            'created_at' => $this->created_at->toDateTimeString(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'profile_image_url' => $this->user->profile_image_url,
                ];
            }),
            // For admin-created reviews without user
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }
}
