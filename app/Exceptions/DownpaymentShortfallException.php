<?php

namespace App\Exceptions;

use Exception;

class DownpaymentShortfallException extends Exception
{
    public function __construct(
        string $message,
        public readonly array $balanceComparison = [],
    ) {
        parent::__construct($message);
    }
}
