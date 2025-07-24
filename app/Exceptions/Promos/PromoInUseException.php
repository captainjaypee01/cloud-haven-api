<?php

namespace App\Exceptions\Promos;

use Exception;

class PromoInUseException extends Exception
{
    protected $message = 'Cannot delete promo because it is currently in use';
    public const ERROR_CODE = 'PROMO_IN_USE';

    protected array $bookings = [];

    public function __construct($message = null, $code = 0, ?Exception $previous = null)
    {
        if ($message) {
            $this->message = $message;
        }
        parent::__construct($this->message, $code, $previous);
    }

    // Optionally, render a JSON response for this exception
    public function render($request)
    {
        return response()->json([
            'code'    => self::ERROR_CODE,
            'message' => $this->getMessage(),
        ], 422);
    }

    public function withBookings(array $bookingRefs): self
    {
        $this->bookings = $bookingRefs;
        return $this;
    }

    public function getBookings(): array
    {
        return $this->bookings;
    }
}
