<?php

namespace App\Contracts\Repositories;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;
}
