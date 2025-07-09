<x-mail::message>
<div style="text-align:center; margin-bottom:24px">
    <img src="{{ asset('netania-logo.jpg') }}" alt="{{ config('app.name') }}" style="max-width:180px;">
</div>
# Your Booking is Reserved!

Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},

Thank you for choosing {{ config('app.name') }}!

**Your reservation is on hold for 15 minutes.**  
Please complete your payment to secure your booking.

- **Reference Number:** {{ $booking->reference_number }}
- **Payment Due:** {{ $booking->reserved_until }}

<x-mail::button :url="config('app.frontend_url') . '/booking/' . $booking->reference_number . '/payment'">
Pay Now
</x-mail::button>

@if($booking->bookingRooms && $booking->bookingRooms->count())
<x-mail::panel>
**Rooms Booked:**
@foreach($booking->bookingRooms as $bookingRoom)
- {{ $bookingRoom->room->name ?? '' }}
@endforeach
</x-mail::panel>
@endif

*If payment is not received by the due time above, your reservation will be cancelled.*

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
