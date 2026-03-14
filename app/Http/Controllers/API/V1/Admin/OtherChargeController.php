<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\EmptyResponse;
use Illuminate\Http\Request;

class OtherChargeController extends Controller
{
    public function update(Request $request, \App\Models\Booking $booking, \App\Models\OtherCharge $charge)
    {
        if ($charge->booking_id !== (int) $booking->id) {
            abort(403, 'Invalid charge/booking.');
        }
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string',
        ]);
        $charge->update($validated);
        return new EmptyResponse();
    }

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
