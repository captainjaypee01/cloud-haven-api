<?php

namespace App\Exceptions\Amenities;

use Exception;

class AmenityInUseException extends Exception
{
    protected $message = 'Cannot delete amenity because it is currently in use';

    public const ERROR_CODE = 'AMENITY_IN_USE';

    // Optional: Add custom data to the exception
    protected $rooms = [];

    public function __construct($message = null, $code = 0, ?Exception $previous = null)
    {
        if ($message) {
            $this->message = $message;
        }

        parent::__construct($this->message, $code, $previous);
    }

    public function render($request)
    {
        return response()->json([
            'code' => self::ERROR_CODE,
            'message' => $this->getMessage()
        ], 422);
    }

    // Optional: Add affected rooms
    public function withRooms(array $rooms): self
    {
        $this->rooms = $rooms;
        return $this;
    }

    public function getRooms(): array
    {
        return $this->rooms;
    }
}
