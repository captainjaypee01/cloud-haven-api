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
                'discount_type'  => $dto->discount_type,
                'discount_value' => $dto->discount_value,
                'expires_at'     => $dto->expires_at,
                'max_uses'       => $dto->max_uses,
                'active'         => $dto->active ?? false,
                // 'active' not updated here
            ];
            // Filter out unchanged values
            $changes = array_filter($updateData, fn($val, $key) => $val !== $promo->$key, ARRAY_FILTER_USE_BOTH);
            if (empty($changes)) {
                return $promo; // nothing to update
            }
            $promo->update($changes);
            return $promo->fresh();
        });
    }
}
