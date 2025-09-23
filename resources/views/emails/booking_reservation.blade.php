<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Booking Reservation</title>
</head>
<body>
    @php
        use Carbon\Carbon;
        $resort = config('resort') ?: [];
        $fmtDate = function ($date) { if(!$date) return ''; return Carbon::parse($date)->isoFormat('DD MMM YYYY'); };
        $fmtDateTime = function ($date) { if(!$date) return ''; return Carbon::parse($date)->setTimezone('Asia/Singapore')->isoFormat('DD MMM YYYY HH:mm'); };
        $fmtMoney = fn($v) => '₱' . number_format((float)$v, 2);
        $isDayTour = ($booking->booking_type ?? 'overnight') === 'day_tour';
        $nights = 0;
        if (!empty($booking?->check_in_date) && !empty($booking?->check_out_date)) {
            $nights = Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date));
        }
        $frontendBase = rtrim(($resort['frontend_url'] ?? (config('app.frontend_url') ?? config('app.url'))), '/');
        $resortName = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
        $roomLines = collect($booking->bookingRooms ?? [])
        ->filter(fn($br) => !empty($br->room)) // keep only rows with a room
        ->groupBy(fn($br) => $br->room_id ?? ($br->room->id ?? spl_object_id($br))) // group same room
        ->map(function ($group) {
            $first = $group->first();
            return (object)[
                'name' => $first->room->name ?? 'Room',
                'qty'  => $group->sum(fn($x) => (int)($x->quantity ?? 1)),
            ];
        })
        ->sortBy('name')
        ->values();
    @endphp
    <div class="container">
        <div class="email-wrapper">
            <div class="email-content">
                @include('emails.partials._style')
                @include('emails.partials._header', ['resort' => $resort])

                <div class="content">
                    <p style="margin-bottom:4px;font-size:16px;padding-left:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? 'Guest' }},</p>
                    <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">Thank you for your {{ $isDayTour ? 'Day Tour' : 'accommodation' }} reservation. Your booking is currently <strong>on hold</strong> and requires payment to secure {{ $isDayTour ? 'your spot' : 'the room' }}.</p>
                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">Here's a summary</p>

                    <!-- Status + Expiry (RESERVATION ONLY) -->
                    <div class="section" style="padding-top:0;">
                    <div class="section-title">Reservation Status</div>
                    <div class="box">
                        <div class="box-inner">
                        <p class="m-0"><strong>Status:</strong> On Hold (awaiting payment)</p>
                        @if(!empty($booking->reserved_until))
                            <p class="m-0"><strong>Hold Expires:</strong> {{ $fmtDateTime($booking->reserved_until) }}</p>
                        @endif
                        </div>
                    </div>
                    </div>

                    <!-- Core booking facts (shared) -->
                    <div class="panel" style="margin-bottom:24px;margin-left:16px;margin-right:16px;">
                        <div class="kv"><strong>Reference Number:</strong> {{ $booking->reference_number }}</div>
                        @if($isDayTour)
                            <div class="kv"><strong>Day Tour Date:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                            <div class="kv"><strong>Tour Hours:</strong> 8:00 AM - 5:00 PM</div>
                        @else
                            <div class="kv"><strong>Check-In:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                            <div class="kv"><strong>Check-Out:</strong> {{ $fmtDate($booking->check_out_date) }}</div>
                            <div class="kv"><strong>Nights:</strong> {{ $nights }}</div>
                        @endif
                        <div class="kv"><strong>Guests:</strong> Adults: {{ $booking->adults ?? 0 }}, Children: {{ $booking->children ?? 0 }}, Total: {{ $booking->total_guests ?? (($booking->adults ?? 0) + ($booking->children ?? 0)) }}</div>
                        <div class="kv"><strong>Total Amount:</strong> {{ $fmtMoney($booking->final_price - ($booking->discount_amount ?? 0)) }}</div>
                    </div>

                    @if(!empty($booking->bookingRooms) && $booking->bookingRooms->count())
                    <div class="section">
                        <div class="section-title">Rooms Booked</div>
                        <div class="box">
                            <div class="box-inner">
                                <p class="m-0" style="font-size:13px; color:#666; margin-bottom:12px;">
                                    <em>Room assignments will be provided upon check-in.</em>
                                </p>
                                <table class="table">
                                <thead>
                                    <tr>
                                    <th>Room</th>
                                    <th class="right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($roomLines as $line)
                                    <tr>
                                        <td style="padding: 12px 12px;border:1px solid #bbb;">{{ $line->name ?? 'Room' }}</td>
                                        <td style="padding: 12px 12px;border:1px solid #bbb;" class="left">{{ $line->qty ?? 1 }}</td>
                                    </tr>
                                    @empty
                                        <tr>
                                        <td colspan="2">No rooms found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(!empty($booking->meal_quote_data))
                    <div class="section">
                        <div class="section-title">Meal Breakdown</div>
                        <div class="box">
                            <div class="box-inner">
                                @php
                                    // Handle both string and array formats
                                    $mealQuoteData = $booking->meal_quote_data;
                                    if (is_string($mealQuoteData)) {
                                        $mealQuote = json_decode($mealQuoteData, true) ?: [];
                                    } else {
                                        $mealQuote = $mealQuoteData ?: [];
                                    }
                                    
                                    // Only proceed if we have valid data
                                    if (!empty($mealQuote['nights'])) {
                                        $buffetNights = collect($mealQuote['nights'])->filter(fn($night) => $night['type'] === 'buffet');
                                        $freeBreakfastNights = collect($mealQuote['nights'])->filter(fn($night) => $night['type'] === 'free_breakfast');
                                    } else {
                                        $buffetNights = collect();
                                        $freeBreakfastNights = collect();
                                    }
                                @endphp
                                
                                @if($isDayTour && !empty($mealQuote['selections']))
                                    <!-- Day Tour Meal Breakdown -->
                                    @foreach($mealQuote['selections'] as $selection)
                                        <div style="margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;">
                                            <div style="font-weight: bold; margin-bottom: 8px; color: #333;">
                                                {{ $selection['room_name'] }} - {{ $selection['adults'] + $selection['children'] }} guests
                                            </div>
                                            
                                            <!-- Buffet Lunch -->
                                            <div style="font-size: 13px; color: #6b7280; margin-left: 12px;">
                                                @if($selection['include_lunch'] && $selection['lunch_cost'] > 0)
                                                    <div style="margin-bottom: 4px;">
                                                        <span style="color: #059669;">✓</span> Buffet Lunch: 
                                                        @if($selection['adults'] > 0 && $selection['children'] > 0)
                                                            {{ $selection['adults'] }} adults × {{ $fmtMoney($selection['lunch_adult_price'] ?? 0) }} + {{ $selection['children'] }} children × {{ $fmtMoney($selection['lunch_child_price'] ?? 0) }} = {{ $fmtMoney($selection['lunch_cost']) }}
                                                        @elseif($selection['adults'] > 0)
                                                            {{ $selection['adults'] }} adults × {{ $fmtMoney($selection['lunch_adult_price'] ?? 0) }} = {{ $fmtMoney($selection['lunch_cost']) }}
                                                        @else
                                                            {{ $selection['children'] }} children × {{ $fmtMoney($selection['lunch_child_price'] ?? 0) }} = {{ $fmtMoney($selection['lunch_cost']) }}
                                                        @endif
                                                    </div>
                                                @else
                                                    <div style="margin-bottom: 4px; color: #9ca3af;">
                                                        <span style="color: #ef4444;">✗</span> Buffet Lunch: Not included
                                                    </div>
                                                @endif
                                            </div>
                                            
                                            <!-- PM Snack -->
                                            <div style="font-size: 13px; color: #6b7280; margin-left: 12px;">
                                                @if($selection['include_pm_snack'] && $selection['pm_snack_cost'] > 0)
                                                    <div style="margin-bottom: 4px;">
                                                        <span style="color: #059669;">✓</span> PM Snack: 
                                                        @if($selection['adults'] > 0 && $selection['children'] > 0)
                                                            {{ $selection['adults'] }} adults × {{ $fmtMoney($selection['pm_snack_adult_price'] ?? 0) }} + {{ $selection['children'] }} children × {{ $fmtMoney($selection['pm_snack_child_price'] ?? 0) }} = {{ $fmtMoney($selection['pm_snack_cost']) }}
                                                        @elseif($selection['adults'] > 0)
                                                            {{ $selection['adults'] }} adults × {{ $fmtMoney($selection['pm_snack_adult_price'] ?? 0) }} = {{ $fmtMoney($selection['pm_snack_cost']) }}
                                                        @else
                                                            {{ $selection['children'] }} children × {{ $fmtMoney($selection['pm_snack_child_price'] ?? 0) }} = {{ $fmtMoney($selection['pm_snack_cost']) }}
                                                        @endif
                                                    </div>
                                                @else
                                                    <div style="margin-bottom: 4px; color: #9ca3af;">
                                                        <span style="color: #ef4444;">✗</span> PM Snack: Not included
                                                    </div>
                                                @endif
                                            </div>
                                            
                                            @if($selection['meal_cost'] > 0)
                                                <div style="font-size: 13px; color: #111827; font-weight: bold; margin-top: 4px; margin-left: 12px;">
                                                    Meal Total: {{ $fmtMoney($selection['meal_cost']) }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @elseif($buffetNights->count() > 0)
                                <div style="margin-bottom: 16px;">
                                    <div style="font-weight: bold; margin-bottom: 8px; color: #333;">
                                        Buffet Meals: {{ $buffetNights->count() }} night{{ $buffetNights->count() > 1 ? 's' : '' }}
                                    </div>
                                    @foreach($buffetNights as $night)
                                        @php
                                            $startDate = \Carbon\Carbon::parse($night['date']);
                                            $endDate = \Carbon\Carbon::parse($night['date'])->addDay();
                                        @endphp
                                        <div style="margin-bottom: 8px; padding-left: 12px; border-left: 3px solid #e5e7eb;">
                                            <div style="font-size: 14px; color: #374151; margin-bottom: 4px;">
                                                <strong>{{ $startDate->format('M j') }} to {{ $endDate->format('M j') }}</strong>
                                            </div>
                                            <div style="font-size: 13px; color: #6b7280;">
                                                Adult Price: ₱{{ number_format($night['adult_price'] ?? 0, 2) }} per person
                                            </div>
                                            @if(isset($night['child_price']) && $night['child_price'] > 0)
                                            <div style="font-size: 13px; color: #6b7280;">
                                                Child Price: ₱{{ number_format($night['child_price'], 2) }} per person
                                            </div>
                                            @endif
                                            @if(isset($night['night_total']) && $night['night_total'] > 0)
                                            <div style="font-size: 13px; color: #111827; font-weight: bold; margin-top: 2px;">
                                                Total: ₱{{ number_format($night['night_total'], 2) }}
                                            </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                                
                                @if($freeBreakfastNights->count() > 0)
                                <div style="margin-bottom: 16px;">
                                    <div style="font-weight: bold; margin-bottom: 8px; color: #333;">
                                        Complimentary Breakfast Only: {{ $freeBreakfastNights->count() }} day{{ $freeBreakfastNights->count() > 1 ? 's' : '' }}
                                    </div>
                                    @foreach($freeBreakfastNights as $night)
                                        @php
                                            $breakfastDate = \Carbon\Carbon::parse($night['date'])->addDay();
                                        @endphp
                                        <div style="margin-bottom: 4px; padding-left: 12px; border-left: 3px solid #d1fae5;">
                                            <div style="font-size: 13px; color: #6b7280;">
                                                {{ $breakfastDate->format('M j') }} - Plated
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                                
                                @if(!empty($mealQuote['meal_subtotal']))
                                <div style="border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; color: #111827;">
                                        <span>Total Meal Cost:</span>
                                        <span>{{ $fmtMoney($mealQuote['meal_subtotal']) }}</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(!empty($booking->bookingAddons) && $booking->bookingAddons->count())
                    <div class="section">
                        <div class="section-title">Additional Services</div>
                        <table class="table">
                        <thead>
                            <tr>
                            <th>Service</th>
                            <th class="right">Price</th>
                            <th class="right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->bookingAddons as $addon)
                            <tr>
                                <td>{{ $addon->name }}</td>
                                <td class="right">{{ $fmtMoney($addon->price ?? 0) }}</td>
                                <td class="right">{{ $fmtMoney(($addon->total ?? 0)) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        </table>
                    </div>
                    @endif


                    <!-- Guest Data -->
                    <div class="section">
                    <div class="section-title">Guest Data</div>
                    <div class="box"><div class="box-inner">
                        <table width="100%"><tr>
                        <td style="width:50%;vertical-align:top;">
                            <p class="m-0"><strong>Name:</strong> {{ $booking->guest_name ?? $booking->user->name ?? '—' }}</p>
                            @if(!empty($booking->guest_address))
                            <p class="m-0"><strong>Address:</strong> {{ $booking->guest_address }}</p>
                            @endif
                            @if(!empty($booking->guest_city) || !empty($booking->guest_country))
                            <p class="m-0"><strong>City:</strong> {{ $booking->guest_city ?? '' }}</p>
                            <p class="m-0"><strong>Country:</strong> {{ $booking->guest_country ?? '' }}</p>
                            @endif
                        </td>
                        <td style="width:50%;vertical-align:top;">
                            @if(!empty($booking->guest_phone))
                            <p class="m-0"><strong>Mobile or Cell Phone:</strong> {{ $booking->guest_phone }}</p>
                            @endif
                            @if(!empty($booking->guest_email))
                            <p class="m-0"><strong>Email:</strong> <a href="mailto:{{ $booking->guest_email }}">{{ $booking->guest_email }}</a></p>
                            @endif
                        </td>
                        </tr></table>
                    </div></div>
                    </div>

                    <!-- Lodging Information -->
                    <div class="section">
                        <div class="section-title">Lodging Information</div>
                        <div class="box">
                            <div class="box-inner">
                                <table width="100%"><tr>
                                <td style="width:50%;vertical-align:top;">
                                    <p class="m-0"><strong>{{ $resortName }}</strong><br>
                                    {{ $resort['address_line1'] ?? '' }}<br>
                                    {{ $resort['address_line2'] ?? '' }}</p>
                                </td>
                                <td style="width:50%;vertical-align:top;">
                                    <p class="m-0">
                                    <strong>Phone:</strong> {{ $resort['phone'] ?? '' }}<br>
                                    <strong>Email:</strong> <a href="mailto:{{ $resort['email'] ?? '' }}">{{ $resort['email'] ?? '' }}</a><br>
                                                                @if(!empty($resort['website']))
                                <strong>Website:</strong> <a href="{{ $frontendBase }}" target="_blank">{{ $frontendBase }}</a>
                            @endif
                                    </p>
                                </td>
                                </tr></table>
                            </div>
                        </div>
                    </div>

                    @if(!empty($resort['maps_link']))
                    <div class="section">
                        <div class="section-title">Travel Route</div>
                        <div class="box"><div class="box-inner">
                        <p class="m-0"><a href="{{ $resort['maps_link'] }}" target="_blank">Travel Route</a></p>
                        </div></div>
                    </div>
                    @endif

                    <!-- Reservation Info -->
                    <div class="section">
                    <div class="section-title">Reservation Info</div>
                    <div class="box"><div class="box-inner">
                        <table width="100%"><tr>
                        <td style="width:50%;vertical-align:top;">
                            <p class="m-0"><strong>Booking #:</strong> {{ $booking->reference_number }}</p>
                            <p class="m-0"><strong>Date:</strong> {{ $fmtDateTime($booking->created_at ?? now()) }}</p>
                            <p class="m-0"><strong>Total Pax:</strong> {{ $booking->total_guests ?? (($booking->adults ?? 0) + ($booking->children ?? 0)) }}</p>
                            <p class="m-0"><strong>Adults:</strong> {{$booking->adults ?? 0}}</p>
                        </td>
                        <td style="width:50%;vertical-align:top;">
                            @if($isDayTour)
                                <p class="m-0"><strong>Tour Date:</strong> {{ $fmtDate($booking->check_in_date) }}</p>
                                <p class="m-0"><strong>Tour Duration:</strong> 8:00 AM - 5:00 PM</p>
                                <p class="m-0"><strong>Children:</strong> {{$booking->children ?? 0}}</p>
                            @else
                                <p class="m-0"><strong>Arrival:</strong> {{ $fmtDate($booking->check_in_date) }}</p>
                                <p class="m-0"><strong>Departure:</strong> {{ $fmtDate($booking->check_out_date) }}</p>
                                <p class="m-0"><strong>Nights:</strong> {{ $nights }}</p>
                                <p class="m-0"><strong>Children:</strong> {{$booking->children ?? 0}}</p>
                            @endif
                        </td>
                        </tr></table>
                    </div></div>
                    </div>

                    @if(!empty($booking->special_requests))
                    <div class="section">
                        <div class="section-title">Requests</div>
                        <div class="box"><div class="box-inner">
                        <p class="m-0">{{ $booking->special_requests }}</p>
                        </div></div>
                    </div>
                    @endif

                    <!-- Payment CTA (RESERVATION ONLY) -->
                    <div class="section">
                    <div class="section-title">How to Pay</div>
                    <div class="box"><div class="box-inner">
                        <p class="m-0" style="font-size:14px;">Click <strong>Pay Now</strong> to proceed to our secure checkout and choose your preferred payment method.</p>
                    </div></div>
                    </div>
                    <div class="mt-15" style="margin:16px 16px;">
                    <a href="{{ $frontendBase . '/booking/' . $booking->reference_number . '/payment' }}" class="badge">Pay Now (Secure Checkout)</a>
                    </div>

                    <div class="mt-15" style="margin:16px 16px;">
                    <a href="{{ $frontendBase . '/booking/' . $booking->reference_number }}" style="display:inline-block;padding:10px 18px;border:1px solid #bbb;border-radius:6px;color:#000;">View / Manage Reservation</a>
                    </div>

                    <!-- Payment Policies -->
                    <div class="section">
                    <div class="section-title">MODES and DETAILS of PAYMENT</div>
                    <div class="box"><div class="box-inner" style="font-size:13px; line-height:19px;">
                        <p class="m-0"><strong>Terms of Payment:</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li>50% deposit upon confirmation of booking through BDO</li>
                        <li><strong>REMAINING 50% BALANCE-IF CASH OR ONLINE BANKING-</strong>Balance for your reservation should be settled at the resort before check-in time.</li>
                        <li><strong>REMAINING 50% BALANCE – IF COMPANY CHEQUE,</strong> kindly deposit at Netania De Laiya account one week before the event.</li>
                        <li>Any additional Charges should be settled before check out time.</li>
                        <li>Credit card is not allowed</li>
                        <li>Personal Check is not allowed</li>
                        </ul>
                        
                        <p class="m-0" style="margin-top:16px;"><strong>BANK:</strong> BDO</p>
                        <p class="m-0"><strong>Account Name:</strong> NETANIA DE LAIYA INC.</p>
                        <p class="m-0"><strong>Account Number:</strong> 004978007114</p>
                    </div></div>
                    </div>

                    <!-- Hotel Policies -->
                    <div class="section">
                    <div class="section-title">NETANIA DE LAIYA HOUSE RULES & POLICIES</div>
                    <div class="box"><div class="box-inner" style="font-size:13px; line-height:19px;">
                        
                        <p class="m-0"><strong>1. CHECK-IN/OUT, BOOKING, RESCHEDULING</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li><strong>1.1</strong> Check-in time: 3:00 PM/Check-out time 1:00 PM: For overnight stay extended hours may be allowed depending on room availability; rate adjustment applies; requires at least 16 hours advance notice.</li>
                        <li><strong>1.2</strong> You may enter the resort gate 15 minutes before check-in time to settle the balance. Please wait in the parking area or at any available tables and chairs at poolside while waiting for the check-in time at 3:00PM.</li>
                        <li><strong>1.3</strong> Rescheduling Policy: For rescheduling of reservation, kindly inform the resort 1 week before the booking schedule. Deposit made for bookings is strictly non-refundable but we allow re-scheduling of reservation (valid for 30 days).</li>
                        <li><strong>1.4</strong> Final Number of Rooms and Headcount: The confirmation of the final rooms and head count is one week before the booking schedule. If the resort is not informed and you are reducing your room or head count, you will not be able to refund. Drivers are included in the final head count. 3 years old and below are free of charge.</li>
                        <li><strong>1.5</strong> Forfeited Reservation: If the client fails to arrive on the date of their reservation.</li>
                        <li><strong>1.6</strong> Pets are allowed with the following conditions: A maximum of two (2) pets per cabana or table for day tours, regardless of the number of rooms for overnight. Only pets weighing a maximum of eighteen (18) kilograms will be allowed. Pets must be kept on a leash or in a kennel/carrier at all times, especially in public areas to maintain cleanliness in the area, pet owners are encouraged to have their pets wear diapers.</li>
                        <li><strong>1.7</strong> Ecological Fee: Present your Booking Confirmation to the Municipal Tourism Reception Area. Pay for the Ecological fee of P50 per person and claim your Ecological Fee tickets together with your Referral Slip. You will only need to present your Referral Slip upon arrival at the resort.</li>
                        </ul>

                        <p class="m-0" style="margin-top:16px;"><strong>2. OCCUPANCY & ROOM SERVICES</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li><strong>2.1</strong> Room capacity shall be STRICTLY OBSERVED.</li>
                        <li><strong>2.2</strong> Bringing food is not allowed inside the rooms. You can order through the resort restaurant menu an hour in advance. You are allowed however to bring snacks, chips, bread, pizza, fruits, fast food meal, liquor and drinks-NO CORKAGE.</li>
                        <li><strong>2.3</strong> Bringing Lechon (with corkage fee=2500)</li>
                        </ul>

                        <p class="m-0" style="margin-top:16px;"><strong>3. DAMAGES AND LOSSES OF RESORT'S PROPERTY</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li><strong>3.1</strong> Guests are responsible for any damage that may occur during their stay at the resort.</li>
                        </ul>

                        <p class="m-0" style="margin-top:16px;"><strong>4. SECURITY CONCERNS, DAMAGES & MISSING ITEMS/VALUABLES</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li><strong>4.1</strong> Netania De Laiya is not liable for the lost, stolen or damaged items. Please keep your valuable and do not leave your things unattended. The resort is not responsible for your personal belongings.</li>
                        <li><strong>4.2</strong> The gate is closed at 10pm, but if there is an emergency and you need to go out, you can tell the front desk or guard so they can assist you.</li>
                        </ul>

                        <p class="m-0" style="margin-top:16px;"><strong>5. SWIMMING POOL and BEACH AVAILABILITY:</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li>Beach Cut Off Time: 6:00pm</li>
                        <li>Swimming Pool Cut Off Time: 10:00pm</li>
                        </ul>

                        <p class="m-0" style="margin-top:16px;"><strong>6. Proper Conduct</strong></p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li><strong>6.1</strong> Please observe silence between 10:00 PM to 6:30 AM - Guests are advised to respect the privacy and comfort of other guests. Hence, kindly refrain from loud music or any noise during these hours. Karaokes or sound systems are allowed only for exclusive and pre-arranged functions.</li>
                        </ul>
                    </div></div>
                    </div>

                    <p class="note" style="padding-left:16px;">This is <strong>not</strong> a booking confirmation. If payment is not received by the hold expiry above, your reservation will be cancelled.</p>
                    
                    <div class="section" style="margin-top: 20px;">
                        <div class="section-title">📎 Attached Document</div>
                        <div class="box"><div class="box-inner" style="font-size:13px; line-height:19px;">
                            <p class="m-0">📄 <strong>Resort Policies PDF</strong> - A detailed copy of all resort policies and house rules has been attached to this email for your reference.</p>
                        </div></div>
                    </div>
                    
                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you,<br>The {{ $resortName }} Team</p>
                </div>

                @include('emails.partials._footer', ['resort' => $resort])

            </div>
        </div>
    </div>
</body>
</html>