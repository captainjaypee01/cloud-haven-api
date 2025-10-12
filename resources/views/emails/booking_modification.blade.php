<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Booking Modified</title>
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
            $roomNumbers = $group->filter(fn($br) => !empty($br->roomUnit?->unit_number))
                                ->pluck('roomUnit.unit_number')
                                ->sort()
                                ->values()
                                ->toArray();
            
            return (object)[
                'name' => $first->room->name ?? 'Room',
                'qty'  => $group->sum(fn($x) => (int)($x->quantity ?? 1)),
                'room_numbers' => $roomNumbers,
                'has_room_numbers' => !empty($roomNumbers),
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
                    <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">Your {{ $isDayTour ? 'Day Tour' : 'accommodation' }} booking has been <strong>modified</strong> as requested.</p>
                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">Here are the updated details:</p>

                    <!-- Modification Notice -->
                    <div class="section" style="padding-top:0;">
                    <div class="section-title">Booking Modified</div>
                    <div class="box">
                        <div class="box-inner">
                        <p class="m-0"><strong>Status:</strong> Modified</p>
                        <p class="m-0"><strong>Reference:</strong> {{ $booking->reference_number }}</p>
                        @if(isset($modificationReason) && !empty($modificationReason))
                        <p class="m-0"><strong>Reason:</strong> {{ $modificationReason }}</p>
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
                        <div class="kv"><strong>Total Price:</strong> {{ $fmtMoney($booking->final_price) }}</div>
                        @if($booking->discount_amount > 0)
                        <div class="kv"><strong>Promo Discount:</strong> -{{ $fmtMoney($booking->discount_amount) }}</div>
                        @endif
                        @if($booking->pwd_senior_discount > 0)
                        <div class="kv"><strong>PWD/Senior Discount:</strong> -{{ $fmtMoney($booking->pwd_senior_discount) }}</div>
                        @endif
                        @if($booking->special_discount > 0)
                        <div class="kv"><strong>Special Discount:</strong> -{{ $fmtMoney($booking->special_discount) }}</div>
                        @endif
                        <div class="kv"><strong>Total Amount:</strong> {{ $fmtMoney($booking->final_price - ($booking->discount_amount ?? 0) - ($booking->pwd_senior_discount ?? 0) - ($booking->special_discount ?? 0)) }}</div>
                        @php
                            $totalPaid = $booking->payments->where('status', 'paid')->sum('amount');
                            $otherCharges = $booking->otherCharges->sum('amount');
                            $actualFinalPrice = $booking->final_price - $booking->discount_amount - $booking->pwd_senior_discount - $booking->special_discount;
                            $totalPayable = $actualFinalPrice + $otherCharges;
                            $remainingBalance = max(0, $totalPayable - $totalPaid);
                        @endphp
                        <div class="kv"><strong>Total Paid:</strong> {{ $fmtMoney($totalPaid) }}</div>
                        <div class="kv"><strong>Remaining Balance:</strong> {{ $fmtMoney($remainingBalance) }}</div>
                    </div>

                    @if(!empty($booking->bookingRooms) && $booking->bookingRooms->count())
                    <div class="section">
                        <div class="section-title">Updated Rooms</div>
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
                        <div class="section-title">Updated Meal Breakdown</div>
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
                @else
                                    <!-- Overnight Meal Breakdown -->
                                    @php
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
                                        <div style="margin-bottom: 8px; padding-left: 12px; border-left: 3px solid #d1fae5;">
                                            <div style="font-size: 14px; color: #374151; margin-bottom: 4px;">
                                                <strong>{{ $breakfastDate->format('M j') }} - Plated</strong>
                                            </div>
                                            @if(isset($night['extra_guest_fee']) && $night['extra_guest_fee'] > 0)
                                            <div style="font-size: 13px; color: #6b7280;">
                                                Extra Guest Fee: ₱{{ number_format($night['extra_guest_fee'], 2) }} per extra guest
                                                <div style="font-size: 12px; color: #9ca3af; margin-top: 2px;">
                                                    (includes breakfast, entrance fee, and amenities)
                                                </div>
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
                                
                                @endif
                                
                                @if(isset($mealQuote['meal_subtotal']) || $booking->meal_price > 0)
                                <div style="border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; color: #111827;">
                                        <span>Total Meal Cost:</span>
                                        <span>{{ $fmtMoney($mealQuote['meal_subtotal'] ?? $booking->meal_price) }}</span>
                                    </div>
                                </div>
                                @endif
                                
                                @if($booking->extra_guest_fee > 0 && $booking->extra_guest_count > 0)
                                <div style="border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 12px;">
                                    <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px; color: #7c3aed;">
                                        <span>Extra Guest Fees ({{ $booking->extra_guest_count }} guest{{ $booking->extra_guest_count > 1 ? 's' : '' }}):</span>
                                        <span>{{ $fmtMoney($booking->extra_guest_fee) }}</span>
                                    </div>
                                    <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                        Entrance fee, amenities, and additional services for extra guests on buffet days
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
                                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {{ $resort['phone_alt'] ?? '' }}<br>
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

                    <div class="mt-15" style="margin:16px 16px;">
                        <a href="{{ $frontendBase . '/booking/' . $booking->reference_number }}" style="display:inline-block;padding:10px 18px;border:1px solid #bbb;border-radius:6px;color:#000;">View / Manage Reservation</a>
                    </div>

                    <p class="note" style="padding-left:16px;">Your booking has been <strong>modified</strong> as requested. We look forward to welcoming you!</p>
                    
                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you,<br>The {{ $resortName }} Team</p>
                </div>

                @include('emails.partials._footer', ['resort' => $resort])

            </div>
        </div>
    </div>
</body>
</html>
