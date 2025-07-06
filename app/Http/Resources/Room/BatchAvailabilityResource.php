<?php

namespace App\Http\Resources\Room;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchAvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'room_id'           => $this['room_id'],
            'requested_count'   => $this['requested_count'],
            'available'         => $this['available'],
            'available_count'   => $this['available_count'],
            'room_name'         => $this['room_name'],
        ];
    }
}
