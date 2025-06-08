<?php

namespace App\Services\Amenities\Actions;

use App\Contracts\Amenities\CreateAmenityContract;
use App\DTO\Amenities\NewAmenity;
use App\Models\Amenity;
use Illuminate\Support\Facades\DB;

final class CreateAmenityAction implements CreateAmenityContract
{
    public function handle(NewAmenity $dto): Amenity
    {
        return DB::transaction(
            function () use ($dto) {

                // Validate unique name
                if (Amenity::where('name', $dto->name)->exists()) {
                    throw new \Exception('Amenity name already exists');
                }

                return Amenity::create(
                    [
                        'name'              => $dto->name,
                        'description'       => $dto->description,
                        'icon'              => $dto->icon,
                        'price'             => $dto->price,
                    ]
                );
            }
        );
    }
}
