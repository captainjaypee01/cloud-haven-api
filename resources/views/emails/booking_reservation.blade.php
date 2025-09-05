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
                    <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">Thank you for your reservation. Your booking is currently <strong>on hold</strong> and requires payment to secure the room.</p>
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
                        <div class="kv"><strong>Check-In:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                        <div class="kv"><strong>Check-Out:</strong> {{ $fmtDate($booking->check_out_date) }}</div>
                        <div class="kv"><strong>Nights:</strong> {{ $nights }}</div>
                        <div class="kv"><strong>Guests:</strong> Adults: {{ $booking->adults ?? 0 }}, Children: {{ $booking->children ?? 0 }}, Total: {{ $booking->total_guests ?? (($booking->adults ?? 0) + ($booking->children ?? 0)) }}</div>
                        <div class="kv"><strong>Total Amount:</strong> {{ $fmtMoney($booking->final_price - ($booking->discount_amount ?? 0)) }}</div>
                    </div>

                    @if(!empty($booking->bookingRooms) && $booking->bookingRooms->count())
                    <div class="section">
                        <div class="section-title">Rooms Booked</div>
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
                                
                                @if($buffetNights->count() > 0)
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
                                                Adults: {{ $night['adults'] }} × ₱{{ number_format($night['adult_price'], 2) }} = ₱{{ number_format($night['adults'] * $night['adult_price'], 2) }}
                                            </div>
                                            @if($night['children'] > 0)
                                            <div style="font-size: 13px; color: #6b7280;">
                                                Children: {{ $night['children'] }} × ₱{{ number_format($night['child_price'], 2) }} = ₱{{ number_format($night['children'] * $night['child_price'], 2) }}
                                            </div>
                                            @endif
                                            <div style="font-size: 13px; color: #111827; font-weight: bold; margin-top: 2px;">
                                                Total: ₱{{ number_format($night['night_total'], 2) }}
                                            </div>
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
                                                {{ $breakfastDate->format('M j') }} - Free Breakfast
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
                            <p class="m-0"><strong>Arrival:</strong> {{ $fmtDate($booking->check_in_date) }}</p>
                            <p class="m-0"><strong>Departure:</strong> {{ $fmtDate($booking->check_out_date) }}</p>
                            <p class="m-0"><strong>Nights:</strong> {{ $nights }}</p>
                            <p class="m-0"><strong>Children:</strong> {{$booking->children ?? 0}}</p>
                        </td>
                        </tr></table>
                    </div></div>
                    </div>

                    <!-- Cancellation Policies -->
                    <div class="section">
                    <div class="section-title">Cancellation Policies</div>
                    <div class="box"><div class="box-inner" style="font-size:13px; line-height:19px;">
                        <p class="m-0"><strong>Guarantee & Payment Policy</strong><br>{{ ($resort['policy']['guarantee'] ?? 'Full payment or Downpayment is required before the option date or prior to check-in.') }}</p>
                        <ul style="margin:8px 0 0 18px; padding:0;">
                        <li>{{ ($resort['policy']['non_refundable'] ?? 'All paid bookings are non-refundable.') }}</li>
                        <li>{{ ($resort['policy']['no_show'] ?? 'Guests will be charged the full amount in the event of a No Show.') }}</li>
                        <li>{{ ($resort['policy']['force_majeure'] ?? 'The resort is not liable for services not rendered due to Force Majeure.') }}</li>
                        </ul>
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

                    <p class="note" style="padding-left:16px;">This is <strong>not</strong> a booking confirmation. If payment is not received by the hold expiry above, your reservation will be cancelled.</p>
                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you,<br>The {{ $resortName }} Team</p>
                </div>

                @include('emails.partials._footer', ['resort' => $resort])

            </div>
        </div>
    </div>
</body>
</html>