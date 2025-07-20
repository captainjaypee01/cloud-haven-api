<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\EmptyResponse;
use Illuminate\Http\Request;

class OtherChargeController extends Controller
{
    public function destroy(\App\Models\Booking $booking, \App\Models\OtherCharge $charge)
    {
        // Optionally check the booking_id matches
        if ($charge->booking_id !== $booking->id) {
            abort(403, 'Invalid charge/booking.');
        }
        $charge->delete();
        return new EmptyResponse();
    }
}
