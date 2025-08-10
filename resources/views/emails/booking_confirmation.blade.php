<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Confirmation</title>
</head>
<body style="background:#fff;font-family:Arial,sans-serif;margin:0;padding:0;">
    <table width="100%" bgcolor="#fff" cellpadding="0" cellspacing="0" style="margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 0;">
                <!-- Card -->
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(60,72,88,.08);border:1px solid #eee;">
                    <tr>
                        <td style="text-align:center;padding:32px 0 12px 0;">
                            <img src="https://res.cloudinary.com/dm3gsotk5/image/upload/v1753969657/netania-logo.jpg" style="height:45px;">
                        </td>
                    </tr>
                    <tr>
                        <td><hr style="border:0;height:1px;background:#81D4FA;margin:0 32px 24px 32px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;">
                            <p style="margin-bottom:16px;font-size:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},</p>
                            <p style="margin-bottom:24px;font-size:15px;">Thank you for your payment. Your booking is now <strong>confirmed</strong>!</p>
                            <div style="background:#f6f6f6;border-radius:8px;padding:20px 24px;margin-bottom:24px;">
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Reference Number:</strong> {{ $booking->reference_number }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Check-In:</strong> {{ $booking->check_in_date }} at 14:00</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Check-Out:</strong> {{ $booking->check_out_date }} at 12:00</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Guests:</strong> Adults: {{ $booking->adults }}, Children: {{ $booking->children }}, Total: {{ $booking->total_guests }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Total Amount:</strong> ₱{{ number_format($booking->final_price, 2) }}</div>
                                @if(isset($downpayment) && $downpayment > 0 && $downpayment < $booking->final_price)
                                    <div style="font-size:15px;margin-bottom:10px;"><strong>Downpayment Paid:</strong> ₱{{ number_format($downpayment, 2) }}</div>
                                    <div style="font-size:15px;margin-bottom:10px;"><strong>Remaining Balance:</strong> ₱{{ number_format($booking->final_price - $downpayment, 2) }}</div>
                                @elseif(isset($downpayment) && $downpayment >= $booking->final_price)
                                    <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Status:</strong> Fully Paid</div>
                                @endif
                                @isset($payment_method)
                                    <div style="font-size:15px;"><strong>Payment Method:</strong> {{ $payment_method }}</div>
                                @endisset
                            </div>
                            @if($booking->bookingRooms && $booking->bookingRooms->count())
                            <div style="margin-bottom:24px;">
                                <strong>Rooms Booked:</strong>
                                <ul style="padding-left:18px;">
                                    @foreach($booking->bookingRooms as $bookingRoom)
                                        <li>{{ $bookingRoom->room->name ?? '' }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                            @if(isset($downpayment) && $downpayment > 0 && $downpayment < $booking->final_price)
                                <a href="{{ config('app.frontend_url') . '/booking/' . $booking->reference_number . '/payment' }}"
                                    style="display:inline-block;padding:12px 32px;background:#00B8D4;color:#fff;border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;margin-top:16px;">Settle Remaining Balance</a>
                            @endif
                            <p style="margin:36px 0 0 0;font-size:14px;">Thank you,<br>The {{ config('app.name') }} Team</p>
                        </td>
                    </tr>
                </table>
                <!-- Footer -->
                <table width="600" cellpadding="0" cellspacing="0" style="background:#0288D1;border-radius:0 0 8px 8px;">
                    <tr>
                        <td style="color:#fff;padding:20px 32px;font-size:14px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="top" align="left" style="padding-bottom:10px;">
                                        <strong>Follow Us On:</strong>
                                        <div><a href="https://www.facebook.com/profile.php?id=100064182843841" style="color:#fff;text-decoration:none;">Facebook</a></div>
                                    </td>
                                    <td valign="top" align="right">
                                        <strong>Customer Service:</strong><br>
                                        Phone: +63 949-798-9831<br>
                                        Email: <a href="mailto:info@netaniadelaiya.com" style="color:#fff;text-decoration:underline;">info@netaniadelaiya.com</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <div style="font-size:11px;text-align:center;margin-top:10px;color:#666;">
                    *** This email notification was sent because of your booking at {{ config('app.name') }}. ***
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
