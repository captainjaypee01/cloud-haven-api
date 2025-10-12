<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Modification - {{ $booking->reference_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .booking-details {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .room-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .price-breakdown {
            background-color: #e3f2fd;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        .highlight {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Booking Modification Confirmation</h1>
        <p>Dear {{ $booking->guest_name }},</p>
        <p>Your booking has been successfully modified. Please find the updated details below:</p>
    </div>

    <div class="booking-details">
        <h2>Booking Information</h2>
        <p><strong>Reference Number:</strong> {{ $booking->reference_number }}</p>
        <p><strong>Guest Name:</strong> {{ $booking->guest_name }}</p>
        <p><strong>Email:</strong> {{ $booking->guest_email }}</p>
        <p><strong>Phone:</strong> {{ $booking->guest_phone }}</p>
        
        @if($booking->booking_type === 'day_tour')
            <p><strong>Tour Date:</strong> {{ \Carbon\Carbon::parse($booking->check_in_date)->format('F j, Y') }}</p>
            <p><strong>Tour Hours:</strong> 8:00 AM - 5:00 PM</p>
        @else
            <p><strong>Check-in:</strong> {{ \Carbon\Carbon::parse($booking->check_in_date)->format('F j, Y') }}</p>
            <p><strong>Check-out:</strong> {{ \Carbon\Carbon::parse($booking->check_out_date)->format('F j, Y') }}</p>
            <p><strong>Duration:</strong> {{ \Carbon\Carbon::parse($booking->check_in_date)->diffInDays(\Carbon\Carbon::parse($booking->check_out_date)) }} night(s)</p>
        @endif
        
        <p><strong>Total Guests:</strong> {{ $booking->total_guests }} ({{ $booking->adults }} adults, {{ $booking->children }} children)</p>
    </div>

    <div class="room-details">
        <h3>Room Details</h3>
        @foreach($booking->bookingRooms as $bookingRoom)
            <div style="margin-bottom: 15px; padding: 10px; background-color: white; border-radius: 4px;">
                <p><strong>Room:</strong> {{ $bookingRoom->room->name }}</p>
                <p><strong>Guests:</strong> {{ $bookingRoom->adults }} adults, {{ $bookingRoom->children }} children ({{ $bookingRoom->total_guests }} total)</p>
                @if($booking->booking_type === 'day_tour')
                    <p><strong>Price per Person:</strong> ₱{{ number_format($bookingRoom->price_per_night, 2) }}</p>
                @else
                    <p><strong>Price per Night:</strong> ₱{{ number_format($bookingRoom->price_per_night, 2) }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="price-breakdown">
        <h3>Updated Price Breakdown</h3>
        <p><strong>Room Price:</strong> ₱{{ number_format($booking->total_price, 2) }}</p>
        <p><strong>Meal Price:</strong> ₱{{ number_format($booking->meal_price ?? 0, 2) }}</p>
        @if($booking->extra_guest_fee > 0)
            <p><strong>Extra Guest Fee:</strong> ₱{{ number_format($booking->extra_guest_fee, 2) }}</p>
        @endif
        @if($booking->discount_amount > 0)
            <p><strong>Discount:</strong> -₱{{ number_format($booking->discount_amount, 2) }}</p>
        @endif
        @if($booking->pwd_senior_discount > 0)
            <p><strong>PWD/Senior Discount:</strong> -₱{{ number_format($booking->pwd_senior_discount, 2) }}</p>
        @endif
        @if($booking->special_discount > 0)
            <p><strong>Special Discount:</strong> -₱{{ number_format($booking->special_discount, 2) }}</p>
        @endif
        <hr>
        <p><strong>Final Price:</strong> ₱{{ number_format($booking->final_price, 2) }}</p>
    </div>

    @if($modificationReason)
        <div class="highlight">
            <h3>Modification Reason</h3>
            <p>{{ $modificationReason }}</p>
        </div>
    @endif

    <div class="footer">
        <p>If you have any questions about this modification, please contact us.</p>
        <p>Thank you for choosing our resort!</p>
        <p><strong>Cloud Haven Resort</strong></p>
    </div>
</body>
</html>
