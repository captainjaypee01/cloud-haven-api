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
            'pending'           => $this['pending'] ?? 0,
            'confirmed'         => $this['confirmed'] ?? 0,
            'maintenance'       => $this['maintenance'] ?? 0,
            'total_units'       => $this['total_units'] ?? 0,
            'room_name'         => $this['room_name'],
        ];
    }
}
