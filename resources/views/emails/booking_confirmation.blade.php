<x-mail::message>
<div style="text-align:center; margin-bottom:24px">
    <img src="{{ asset('netania-logo.jpg') }}" alt="{{ config('app.name') }}" style="max-width:180px;">
</div>
# Booking Confirmed 🎉

Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},

Your payment has been received. **Your booking is now confirmed!**

- **Reference Number:** {{ $booking->reference_number }}
- **Check-In:** {{ $booking->check_in_date }} at {{ $booking->check_in_time }}
- **Check-Out:** {{ $booking->check_out_date }} at {{ $booking->check_out_time }}
- **Guests:** Adults: {{ $booking->adults }}, Children: {{ $booking->children }}, Total: {{ $booking->total_guests }}
- **Total Price:** ₱{{ number_format($booking->total_price, 2) }}
- **Paid At:** {{ $booking->paid_at ?? now() }}

@if($booking->bookingRooms && $booking->bookingRooms->count())
<x-mail::panel>
**Rooms Booked:**
@foreach($booking->bookingRooms as $bookingRoom)
- {{ $bookingRoom->room->name ?? '' }}
@endforeach
</x-mail::panel>
@endif

<x-mail::button :url="config('app.frontend_url') . '/booking/' . $booking->reference_number">
View My Booking
</x-mail::button>

Thank you for your trust! We look forward to welcoming you at {{ config('app.name') }}.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
