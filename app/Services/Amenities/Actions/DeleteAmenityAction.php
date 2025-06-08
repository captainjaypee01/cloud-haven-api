<?php

namespace App\Services\Amenities\Actions;

use App\Contracts\Amenities\DeleteAmenityContract;
use App\Exceptions\Amenities\AmenityInUseException;
use App\Models\Amenity;
use Illuminate\Support\Facades\DB;

final class DeleteAmenityAction implements DeleteAmenityContract
{

    public function handle(Amenity $amenity): void
    {
        DB::transaction(function () use ($amenity) {
            // Add any pre-deletion checks here
            // Example: Check if amenity is used in any rooms
            // Check for room associations
            $rooms = $amenity->rooms()->take(5)->pluck('name')->toArray();

            if (count($rooms) > 0) {
                $message = sprintf(
                    "Amenity is used in %d rooms including: %s. Remove it from rooms first.",
                    $amenity->rooms()->count(),
                    implode(', ', $rooms)
                );

                throw new AmenityInUseException($message)
                    ->withRooms($rooms);
            }
            
            $amenity->delete();
        });
    }
}
