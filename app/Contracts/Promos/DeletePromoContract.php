<?php
namespace App\Contracts\Promos;

use App\Models\Promo;

interface DeletePromoContract { public function handle(Promo $promo): void; }
