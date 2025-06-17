<?php

namespace App\Http\Resources\Room;

use App\Http\Resources\Amenity\PublicAmenityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug'                  => $this->slug,                     // identifier for detail route
            'name'                  => $this->name,
            'available'             => $this->quantity,
            'short_description'     => $this->short_description,
            'long_description'      => $this->description,
            'max_guests'            => $this->max_guests,
            'extra_guests'          => 2,
            'price'                 => $this->base_weekday_rate,
            // 'weekdayRate'           => $this->base_weekday_rate,
            // 'weekendRate'           > $this->base_weekend_rate,
            // 'images'        => ImageResource::collection(
            //     $this->whenLoaded('images')
            // ),
            'amenities'     => PublicAmenityResource::collection(
                $this->whenLoaded('amenities')
            ),
        ];
    }
}
