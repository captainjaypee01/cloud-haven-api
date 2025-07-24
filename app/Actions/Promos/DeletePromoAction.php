<?php

namespace App\Actions\Promos;

use App\Contracts\Promos\DeletePromoContract;
use App\Exceptions\Promos\PromoInUseException;
use App\Models\Promo;
use Illuminate\Support\Facades\DB;

final class DeletePromoAction implements DeletePromoContract
{
    public function handle(Promo $promo): void
    {
        DB::transaction(function () use ($promo) {
            // Check if this promo is linked to any bookings
            $bookingsUsing = $promo->bookings()->take(5)->pluck('reference_number')->toArray();
            $totalUses = $promo->bookings()->count();
            if ($totalUses > 0) {
                $sampleList = implode(', ', $bookingsUsing);
                $message = sprintf(
                    "Promo code is used in %d bookings including: %s. It cannot be deleted (deactivate it instead).",
                    $totalUses,
                    $sampleList
                );
                throw (new PromoInUseException($message))->withBookings($bookingsUsing);
            }
            // No bookings use this promo, safe to delete
            $promo->delete();
        });
    }
}
