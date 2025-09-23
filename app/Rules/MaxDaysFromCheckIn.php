<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class MaxDaysFromCheckIn implements ValidationRule
{
    protected $maxDays;
    protected $checkInDate;

    public function __construct($maxDays, $checkInDate = null)
    {
        $this->maxDays = $maxDays;
        $this->checkInDate = $checkInDate;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Get check-in date from request data if not provided in constructor
        $checkInDate = $this->checkInDate ?? request()->input('check_in_date');
        
        if (!$checkInDate) {
            $fail('Check-in date is required to validate check-out date.');
            return;
        }

        try {
            $checkIn = Carbon::parse($checkInDate);
            $checkOut = Carbon::parse($value);
            
            $daysDifference = $checkIn->diffInDays($checkOut);
            
            if ($daysDifference > $this->maxDays) {
                $fail("Overnight bookings are limited to a maximum of {$this->maxDays} days.");
            }
        } catch (\Exception $e) {
            $fail('Invalid date format provided.');
        }
    }
}
