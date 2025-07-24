<?php
namespace App\Contracts\Promos;

use App\Models\Promo;
use App\DTO\Promos\UpdatePromo;

interface UpdatePromoContract { public function handle(Promo $promo, UpdatePromo $dto): Promo; }
