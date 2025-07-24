<?php
namespace App\Contracts\Promos;

use App\Models\Promo;
use App\DTO\Promos\NewPromo;

interface CreatePromoContract { public function handle(NewPromo $dto): Promo; }
