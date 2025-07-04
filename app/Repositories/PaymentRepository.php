<?php

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }
}
