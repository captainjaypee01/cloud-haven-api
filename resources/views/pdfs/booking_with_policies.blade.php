<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Details & Resort Policies - {{ $booking->reference_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #2563eb;
            font-size: 24px;
            margin: 0;
        }
        .header h2 {
            color: #666;
            font-size: 16px;
            margin: 5px 0 0 0;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .subsection-title {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin: 15px 0 8px 0;
        }
        .booking-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .booking-info h3 {
            margin: 0 0 10px 0;
            color: #2563eb;
        }
        .booking-info p {
            margin: 5px 0;
        }
        .booking-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        .booking-details .detail-group {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .booking-details .detail-group h4 {
            margin: 0 0 8px 0;
            color: #2563eb;
            font-size: 13px;
        }
        .booking-details .detail-group p {
            margin: 3px 0;
            font-size: 11px;
        }
        .room-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .room-table th, .room-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        .room-table th {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .meal-breakdown {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .meal-breakdown h4 {
            margin: 0 0 8px 0;
            color: #92400e;
        }
        .meal-breakdown p {
            margin: 3px 0;
            font-size: 11px;
        }
        ul {
            margin: 8px 0;
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
        }
        .bank-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .bank-info h3 {
            margin: 0 0 10px 0;
            color: #2563eb;
        }
        .bank-info p {
            margin: 5px 0;
        }
        .highlight {
            background-color: #fef3c7;
            padding: 10px;
            border-left: 4px solid #f59e0b;
            margin: 10px 0;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    @php
        use Carbon\Carbon;
        $resort = config('resort') ?: [];
        $fmtDate = function ($date) { if(!$date) return ''; return Carbon::parse($date)->isoFormat('DD MMM YYYY'); };
        $fmtDateTime = function ($date) { if(!$date) return ''; return Carbon::parse($date)->setTimezone('Asia/Singapore')->isoFormat('DD MMM YYYY HH:mm'); };
        $fmtMoney = fn($v) => 'PHP ' . number_format((float)$v, 2);
        $isDayTour = ($booking->booking_type ?? 'overnight') === 'day_tour';
        $nights = 0;
        if (!empty($booking?->check_in_date) && !empty($booking?->check_out_date)) {
            $nights = Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date));
        }
        $resortName = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
    @endphp

    <div class="header">
        <h1>NETANIA DE LAIYA</h1>
        <h2>Booking Details & Resort Policies</h2>
        <p><strong>Booking Reference:</strong> {{ $booking->reference_number }}</p>
    </div>

    <!-- BOOKING DETAILS SECTION -->
    <div class="section">
        <div class="section-title">BOOKING DETAILS</div>
        
        <div class="booking-details">
            <div class="detail-group">
                <h4>Guest Information</h4>
                <p><strong>Name:</strong> {{ $booking->guest_name ?? 'N/A' }}</p>
                <p><strong>Email:</strong> {{ $booking->guest_email ?? 'N/A' }}</p>
                <p><strong>Phone:</strong> {{ $booking->guest_phone ?? 'N/A' }}</p>
                @if(!empty($booking->guest_address))
                <p><strong>Address:</strong> {{ $booking->guest_address }}</p>
                @endif
            </div>
            
            <div class="detail-group">
                <h4>Booking Information</h4>
                <p><strong>Status:</strong> {{ ucfirst($booking->status) }}</p>
                <p><strong>Type:</strong> {{ $isDayTour ? 'Day Tour' : 'Overnight Stay' }}</p>
                @if($isDayTour)
                    <p><strong>Tour Date:</strong> {{ $fmtDate($booking->check_in_date) }}</p>
                    <p><strong>Tour Hours:</strong> 8:00 AM - 5:00 PM</p>
                @else
                    <p><strong>Check-in:</strong> {{ $fmtDate($booking->check_in_date) }}</p>
                    <p><strong>Check-out:</strong> {{ $fmtDate($booking->check_out_date) }}</p>
                    <p><strong>Nights:</strong> {{ $nights }}</p>
                @endif
            </div>
            
            <div class="detail-group">
                <h4>Guest Count</h4>
                <p><strong>Adults:</strong> {{ $booking->adults ?? 0 }}</p>
                <p><strong>Children:</strong> {{ $booking->children ?? 0 }}</p>
                <p><strong>Total Guests:</strong> {{ $booking->total_guests ?? (($booking->adults ?? 0) + ($booking->children ?? 0)) }}</p>
            </div>
            
            <div class="detail-group">
                <h4>Payment Information</h4>
                <p><strong>Total Amount:</strong> {{ $fmtMoney($booking->final_price - ($booking->discount_amount ?? 0)) }}</p>
                @if($booking->discount_amount > 0)
                <p><strong>Discount:</strong> -{{ $fmtMoney($booking->discount_amount) }}</p>
                @endif
                <p><strong>Payment Option:</strong> {{ $booking->payment_option ? strtoupper($booking->payment_option) : 'N/A' }}</p>
                @if($booking->downpayment_amount > 0)
                <p><strong>Downpayment:</strong> {{ $fmtMoney($booking->downpayment_amount) }}</p>
                @endif
            </div>
        </div>

        @if(!empty($booking->bookingRooms) && $booking->bookingRooms->count())
        <div class="subsection-title">Rooms Booked</div>
        <table class="room-table">
            <thead>
                <tr>
                    <th>Room Type</th>
                    <th>Quantity</th>
                    <th>Adults</th>
                    <th>Children</th>
                    <th>Total Guests</th>
                    @if($isDayTour)
                        <th>Base Price</th>
                        <th>Meal Cost</th>
                        <th>Total Price</th>
                    @else
                        <th>Price/Night</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($booking->bookingRooms as $br)
                <tr>
                    <td>{{ $br->room->name ?? 'N/A' }}</td>
                    <td>1</td>
                    <td>{{ $br->adults }}</td>
                    <td>{{ $br->children }}</td>
                    <td>{{ $br->total_guests }}</td>
                    @if($isDayTour)
                        <td>{{ $fmtMoney($br->base_price ?? 0) }}</td>
                        <td>{{ $fmtMoney($br->meal_cost ?? 0) }}</td>
                        <td>{{ $fmtMoney($br->total_price ?? 0) }}</td>
                    @else
                        <td>{{ $fmtMoney($br->price_per_night) }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(!empty($booking->meal_quote_data))
        <div class="meal-breakdown">
            <h4>Meal Information</h4>
            @php
                $mealQuoteData = $booking->meal_quote_data;
                if (is_string($mealQuoteData)) {
                    $mealQuote = json_decode($mealQuoteData, true) ?: [];
                } else {
                    $mealQuote = $mealQuoteData ?: [];
                }
            @endphp
            
            @if($isDayTour && !empty($mealQuote['selections']))
                @foreach($mealQuote['selections'] as $selection)
                    <p><strong>{{ $selection['room_name'] }} - {{ $selection['adults'] + $selection['children'] }} guests</strong></p>
                    @if($selection['include_lunch'])
                        <p>✓ Buffet Lunch: {{ $fmtMoney($selection['lunch_cost']) }}</p>
                    @endif
                    @if($selection['include_pm_snack'])
                        <p>✓ PM Snack: {{ $fmtMoney($selection['pm_snack_cost']) }}</p>
                    @endif
                    <p><strong>Meal Total: {{ $fmtMoney($selection['meal_cost']) }}</strong></p>
                @endforeach
            @elseif(!empty($mealQuote['nights']))
                @php
                    $buffetNights = collect($mealQuote['nights'])->filter(fn($night) => $night['type'] === 'buffet');
                    $freeBreakfastNights = collect($mealQuote['nights'])->filter(fn($night) => $night['type'] === 'free_breakfast');
                @endphp
                @if($buffetNights->count() > 0)
                    <p><strong>Buffet Meals:</strong> {{ $buffetNights->count() }} night{{ $buffetNights->count() > 1 ? 's' : '' }}</p>
                @endif
                @if($freeBreakfastNights->count() > 0)
                    <p><strong>Free Breakfast:</strong> {{ $freeBreakfastNights->count() }} day{{ $freeBreakfastNights->count() > 1 ? 's' : '' }}</p>
                @endif
                <p><strong>Total Meal Cost: {{ $fmtMoney($mealQuote['meal_subtotal'] ?? $booking->meal_price) }}</strong></p>
            @endif
        </div>
        @endif

        @if(!empty($booking->special_requests))
        <div class="subsection-title">Special Requests</div>
        <p>{{ $booking->special_requests }}</p>
        @endif

        <div class="booking-info">
            <h3>Booking Summary</h3>
            <p><strong>Booking Date:</strong> {{ $fmtDateTime($booking->created_at) }}</p>
            <p><strong>Reference Number:</strong> {{ $booking->reference_number }}</p>
            @if($booking->reserved_until)
            <p><strong>Reserved Until:</strong> {{ $fmtDateTime($booking->reserved_until) }}</p>
            @endif
        </div>
    </div>

    <!-- LODGING INFORMATION SECTION -->
    <div class="section">
        <div class="section-title">LODGING INFORMATION</div>
        
        <div class="booking-info">
            <h3>{{ $resortName }}</h3>
            <p><strong>Address:</strong></p>
            @if(!empty($resort['address_line1']))
            <p>{{ $resort['address_line1'] }}</p>
            @endif
            @if(!empty($resort['address_line2']))
            <p>{{ $resort['address_line2'] }}</p>
            @endif
            
            <p><strong>Contact Information:</strong></p>
            @if(!empty($resort['phone']))
            <p><strong>Phone:</strong> {{ $resort['phone'] }}</p>
            @endif
            @if(!empty($resort['email']))
            <p><strong>Email:</strong> {{ $resort['email'] }}</p>
            @endif
            @if(!empty($resort['frontend_url']))
            <p><strong>Website:</strong> {{ $resort['frontend_url'] }}</p>
            @endif
        </div>

        @if(!empty($resort['maps_link']))
        <div class="subsection-title">Travel Route</div>
        <p>For directions to the resort, please visit: {{ $resort['maps_link'] }}</p>
        @endif
    </div>

    <!-- PAGE BREAK -->
    <div class="page-break"></div>

    <!-- RESORT POLICIES SECTION -->
    <div class="section">
        <div class="section-title">MODES and DETAILS of PAYMENT</div>
        
        <div class="subsection-title">Terms of Payment:</div>
        <ul>
            <li>50% deposit upon confirmation of booking through BDO</li>
            <li><strong>REMAINING 50% BALANCE-IF CASH OR ONLINE BANKING-</strong> Balance for your reservation should be settled at the resort before check-in time.</li>
            <li><strong>REMAINING 50% BALANCE – IF COMPANY CHEQUE,</strong> kindly deposit at Netania De Laiya account one week before the event.</li>
            <li>Any additional Charges should be settled before check out time.</li>
            <li>Credit card is not allowed</li>
            <li>Personal Check is not allowed</li>
        </ul>
        
        <div class="bank-info">
            <h3>Bank Details</h3>
            <p><strong>BANK:</strong> BDO</p>
            <p><strong>Account Name:</strong> NETANIA DE LAIYA INC.</p>
            <p><strong>Account Number:</strong> 004978007114</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">NETANIA DE LAIYA HOUSE RULES & POLICIES</div>
        
        <div class="subsection-title">1. CHECK-IN/OUT, BOOKING, RESCHEDULING</div>
        <ul>
            <li><strong>1.1</strong> Check-in time: 3:00 PM/Check-out time 1:00 PM: For overnight stay extended hours may be allowed depending on room availability; rate adjustment applies; requires at least 16 hours advance notice.</li>
            <li><strong>1.2</strong> You may enter the resort gate 15 minutes before check-in time to settle the balance. Please wait in the parking area or at any available tables and chairs at poolside while waiting for the check-in time at 3:00PM.</li>
            <li><strong>1.3</strong> Rescheduling Policy: For rescheduling of reservation, kindly inform the resort 1 week before the booking schedule. Deposit made for bookings is strictly non-refundable but we allow re-scheduling of reservation (valid for 30 days).</li>
            <li><strong>1.4</strong> Final Number of Rooms and Headcount: The confirmation of the final rooms and head count is one week before the booking schedule. If the resort is not informed and you are reducing your room or head count, you will not be able to refund. Drivers are included in the final head count. 3 years old and below are free of charge.</li>
            <li><strong>1.5</strong> Forfeited Reservation: If the client fails to arrive on the date of their reservation.</li>
            <li><strong>1.6</strong> Pets are allowed with the following conditions: A maximum of two (2) pets per cabana or table for day tours, regardless of the number of rooms for overnight. Only pets weighing a maximum of eighteen (18) kilograms will be allowed. Pets must be kept on a leash or in a kennel/carrier at all times, especially in public areas to maintain cleanliness in the area, pet owners are encouraged to have their pets wear diapers.</li>
            <li><strong>1.7</strong> Ecological Fee: Present your Booking Confirmation to the Municipal Tourism Reception Area. Pay for the Ecological fee of P50 per person and claim your Ecological Fee tickets together with your Referral Slip. You will only need to present your Referral Slip upon arrival at the resort.</li>
        </ul>

        <div class="subsection-title">2. OCCUPANCY & ROOM SERVICES</div>
        <ul>
            <li><strong>2.1</strong> Room capacity shall be STRICTLY OBSERVED.</li>
            <li><strong>2.2</strong> Bringing food is not allowed inside the rooms. You can order through the resort restaurant menu an hour in advance. You are allowed however to bring snacks, chips, bread, pizza, fruits, fast food meal, liquor and drinks-NO CORKAGE.</li>
            <li><strong>2.3</strong> Bringing Lechon (with corkage fee=2500)</li>
        </ul>

        <div class="subsection-title">3. DAMAGES AND LOSSES OF RESORT'S PROPERTY</div>
        <ul>
            <li><strong>3.1</strong> Guests are responsible for any damage that may occur during their stay at the resort.</li>
        </ul>

        <div class="subsection-title">4. SECURITY CONCERNS, DAMAGES & MISSING ITEMS/VALUABLES</div>
        <ul>
            <li><strong>4.1</strong> Netania De Laiya is not liable for the lost, stolen or damaged items. Please keep your valuable and do not leave your things unattended. The resort is not responsible for your personal belongings.</li>
            <li><strong>4.2</strong> The gate is closed at 10pm, but if there is an emergency and you need to go out, you can tell the front desk or guard so they can assist you.</li>
        </ul>

        <div class="subsection-title">5. SWIMMING POOL and BEACH AVAILABILITY</div>
        <ul>
            <li>Beach Cut Off Time: 6:00pm</li>
            <li>Swimming Pool Cut Off Time: 10:00pm</li>
        </ul>

        <div class="subsection-title">6. Proper Conduct</div>
        <ul>
            <li><strong>6.1</strong> Please observe silence between 10:00 PM to 6:30 AM - Guests are advised to respect the privacy and comfort of other guests. Hence, kindly refrain from loud music or any noise during these hours. Karaokes or sound systems are allowed only for exclusive and pre-arranged functions.</li>
        </ul>
    </div>

    <div class="section">
        <div class="section-title">CHILD POLICY</div>
        
        <div class="subsection-title">Age Categories & Pricing</div>
        <ul>
            <li><strong>Children aged 3 years old and below</strong> are free of charge for the entrance fee.</li>
            <li>They are allowed to share a bed with accompanying adults at no additional cost.</li>
            <li><strong>3 years old below</strong> are not required to avail of the buffet but may share food from the adults' plates.</li>
            <li><strong>Children aged 4 to 6 years old</strong> will be charged a reduced buffet rate. Current pricing available during booking.</li>
            <li><strong>7 years old above</strong> will be charged same rate as adult.</li>
        </ul>
        
        <div class="subsection-title">Additional Child Policies</div>
        <ul>
            <li>Final number of children and headcount must be confirmed one week before the booking schedule.</li>
            <li>The resort reserves the right to verify age with valid identification if needed.</li>
            <li>Children must be supervised by adults at all times, especially around water areas.</li>
            <li>Parents/guardians are responsible for their children's safety and conduct during the stay.</li>
        </ul>
    </div>

    <div class="highlight">
        <strong>Important:</strong> Please read and understand all policies before your stay. For any questions or clarifications, please contact the resort directly.
    </div>

    <div class="footer">
        <p>This document contains your booking details and the official policies of Netania De Laiya Resort.</p>
        <p>Generated on: {{ date('F j, Y') }}</p>
    </div>
</body>
</html>
