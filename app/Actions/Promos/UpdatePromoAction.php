<?php

namespace App\Actions\Promos;

use App\Contracts\Promos\UpdatePromoContract;
use App\Models\Promo;
use App\DTO\Promos\UpdatePromo;
use Illuminate\Support\Facades\DB;
use Exception;

final class UpdatePromoAction implements UpdatePromoContract
{
    public function handle(Promo $promo, UpdatePromo $dto): Promo
    {
        return DB::transaction(function () use ($promo, $dto) {
            // Ensure promo code is unique if changed
            if ($dto->code !== $promo->code) {
                if (Promo::where('code', $dto->code)->exists()) {
                    throw new Exception('Promo code already exists.');
                }
            }
            // Prepare updated fields
            $updateData = [
                'code'           => $dto->code,
                'title'          => $dto->title,
                'description'    => $dto->description,
                'scope'          => $dto->scope,
                'discount_type'  => $dto->discount_type,
                'discount_value' => $dto->discount_value,
                'starts_at'      => $dto->starts_at,
                'ends_at'        => $dto->ends_at,
                'expires_at'     => $dto->expires_at,
                'max_uses'       => $dto->max_uses,
                'image_url'      => $dto->image_url,
                'exclusive'      => $dto->exclusive,
                'active'         => $dto->active,
                // New fields for flexible promo logic
                'excluded_days'  => $dto->excluded_days,
                'per_night_calculation' => $dto->per_night_calculation,
            ];
            // Filter out unchanged values - handle null values properly
            $changes = [];
            foreach ($updateData as $key => $value) {
                $currentValue = $promo->$key;
                // Special handling for null values
                if ($value !== $currentValue) {
                    $changes[$key] = $value;
                }
            }
            
            if (empty($changes)) {
                return $promo; // nothing to update
            }
            
            $promo->update($changes);
            return $promo->fresh();
        });
    }
}
