<?php

namespace App\Actions\Promos;

use App\Contracts\Promos\CreatePromoContract;
use App\Models\Promo;
use App\DTO\Promos\NewPromo;
use Illuminate\Support\Facades\DB;

final class CreatePromoAction implements CreatePromoContract
{
    public function handle(NewPromo $dto): Promo
    {
        return DB::transaction(function () use ($dto) {
            // Create new Promo record
            $promo = Promo::create([
                'code'           => $dto->code,
                'discount_type'  => $dto->discount_type,
                'discount_value' => $dto->discount_value,
                'expires_at'     => $dto->expires_at,
                'max_uses'       => $dto->max_uses,
                'uses_count'     => $dto->uses_count ?? 0,  // initialize uses_count to 0
                'active'         => $dto->active ?? false,
            ]);
            return $promo;
        });
    }
}
