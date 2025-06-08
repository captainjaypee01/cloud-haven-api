<?php

namespace App\Services\Amenities\Actions;

use App\Contracts\Amenities\UpdateAmenityContract;
use App\DTO\Amenities\UpdateAmenity;
use App\Models\Amenity;
use Illuminate\Support\Facades\DB;

final class UpdateAmenityAction implements UpdateAmenityContract
{
    public function handle(Amenity $amenity, UpdateAmenity $dto): Amenity
    {
        return DB::transaction(function () use ($amenity, $dto) {
            // Check for unique name (excluding current amenity)
            if ($dto->name && $dto->name !== $amenity->name) {
                if (Amenity::where('name', $dto->name)->exists()) {
                    throw new \Exception('Amenity name already exists');
                }
            }

            // Prepare update data (allow explicit nulls)
            $updateData = [
                'name' => $dto->name,
                'description' => $dto->description,
                'icon' => $dto->icon,
                'price' => $dto->price,
            ];

            // Filter out unchanged fields
            $changes = array_filter($updateData, fn($value, $key) => $value !== $amenity->$key, ARRAY_FILTER_USE_BOTH);

            if (empty($changes)) {
                return $amenity; // No changes needed
            }

            $amenity->update($changes);
            return $amenity->fresh();
        });
    }
}
