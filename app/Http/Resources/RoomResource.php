<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'description'           => $this->description,
            'short_description'     => $this->short_description,
            'quantity'              => $this->quantity,
            'max_guests'            => $this->max_guests,
            'extra_guests'          => $this->extra_guests,
            'status'                => $this->status,
            'price'                 => $this->price_per_night,
            'base_weekday_rate'     => $this->base_weekday_rate,
            'base_weekend_rate'     => $this->base_weekend_rate,
            'price_per_night'       => $this->price_per_night,
            'is_featured'           => $this->is_featured,
            // 'images'            => ImageResource::collection($this->images),
            // 'amenities'         => AmenityResource::collection($this->amenities),
            'created_at'            => $this->created_at->toDateTimeString(),
            'updated_at'            => $this->updated_at->toDateTimeString(),
        ];
    }
}
