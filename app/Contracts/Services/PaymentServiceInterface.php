<?php

namespace App\Contracts\Services;

use App\DTO\Payments\PaymentRequestDTO;
use App\DTO\Payments\PaymentResultDTO;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentServiceInterface
{
    public function execute(PaymentRequestDTO $dto): PaymentResultDTO;
    public function list(array $filters): LengthAwarePaginator;
}
