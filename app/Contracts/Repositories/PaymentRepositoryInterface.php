<?php

namespace App\Contracts\Repositories;

use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;
    public function list(array $filters): LengthAwarePaginator;
}
