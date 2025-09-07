<?php

namespace App\Http\Resources\Room;

use App\Http\Resources\Amenity\PublicAmenityResource;
use App\Http\Resources\Image\ImageResource;
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
        // $viewList = ['Pool', 'Garden'];
        // $floorList = ['Ground', 'Second'];
        return [
            'slug'                  => $this->slug,                     // identifier for detail route
            'name'                  => $this->name,
            'available'             => $this->quantity,
            'short_description'     => $this->short_description,
            'long_description'      => $this->description,
            'max_guests'            => $this->max_guests,
            'extra_guests'          => $this->extra_guests ?? 2,
            'min_guests'            => $this->min_guests ?? 1,
            'room_type'             => $this->room_type ?? 'overnight',
            'allows_day_use'        => $this->allows_day_use, // For backward compatibility
            'price'                 => $this->price_per_night,
            // 'view'                  => $viewList[array_rand($viewList)],
            // 'floor'                 => $floorList[array_rand($viewList)],
            'available_count'       => $this->available_count ?? null,
            'images'                => ImageResource::collection($this->images),
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
