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
            'id'                    => $this->id,                       // numeric ID for API calls
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
            'price'                 => $this->price_per_night_avg ?? $this->price_per_night,
            'stay_total'            => $this->when(isset($this->stay_total), $this->stay_total),
            'price_per_night_avg'   => $this->when(isset($this->price_per_night_avg), $this->price_per_night_avg),
            'nightly_rates'         => $this->when(isset($this->nightly_rates), $this->nightly_rates),
            // 'view'                  => $viewList[array_rand($viewList)],
            // 'floor'                 => $floorList[array_rand($viewList)],
            'available_count'       => $this->available_count ?? null,
            'pending_count'         => $this->pending_count ?? null,
            'confirmed_count'       => $this->confirmed_count ?? null,
            'maintenance_count'     => $this->maintenance_count ?? null,
            'blocked_count'         => $this->blocked_count ?? null,
            'total_units'           => $this->total_units ?? $this->quantity,
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
