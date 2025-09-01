<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Confirmed</title>
</head>
<body>
    @php
        use Carbon\Carbon;
        $resort = config('resort') ?: [];
        $fmtDate = function ($date) { if(!$date) return ''; return Carbon::parse($date)->isoFormat('DD MMM YYYY'); };
        $fmtDateTime = function ($date) { if(!$date) return ''; return Carbon::parse($date)->isoFormat('DD MMM YYYY HH:mm'); };
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
    <table width="100%" bgcolor="#fff" cellpadding="0" cellspacing="0" class="container">
        <tr>
            <td align="center" style="padding:32px 0;">

            @include('emails.partials._style')
            @include('emails.partials._header', ['resort' => $resort])

            <tr>
                <td class="content">
                    <p style="margin-bottom:4px;font-size:16px;padding-left:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? 'Guest' }},</p>
                    <p style="margin-bottom:24px;font-size:15px;padding-left:16px;">Thank you for your payment. Your booking is now <strong>confirmed</strong>!</p>
                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">Here's a summary</p>

                    <!-- Core booking facts (shared) -->
                    <div class="panel" style="margin-bottom:24px;margin-left:16px;margin-right:16px;">
                        <div class="kv"><strong>Reference Number:</strong> {{ $booking->reference_number }}</div>
                        <div class="kv"><strong>Check-In:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                        <div class="kv"><strong>Check-Out:</strong> {{ $fmtDate($booking->check_out_date) }}</div>
                        <div class="kv"><strong>Nights:</strong> {{ $nights }}</div>
                        <div class="kv"><strong>Guests:</strong> Adults: {{ $booking->adults ?? 0 }}, Children: {{ $booking->children ?? 0 }}, Total: {{ $booking->total_guests ?? (($booking->adults ?? 0) + ($booking->children ?? 0)) }}</div>
                        <div class="kv"><strong>Total Amount:</strong> {{ $fmtMoney($booking->final_price) }}</div>

                        @if(isset($downpayment) && $downpayment > 0 && $downpayment < $booking->final_price)
                            <div class="kv"><strong>Downpayment Paid:</strong> {{ $fmtMoney($downpayment) }}</div>
                            <div class="kv"><strong>Remaining Balance:</strong> {{ $fmtMoney($booking->final_price - $downpayment) }}</div>
                        @elseif(isset($downpayment) && $downpayment >= $booking->final_price)
                            <div class="kv"><strong>Payment Status:</strong> Fully Paid</div>
                        @endif

                        @isset($payment_method)
                            <div class="kv"><strong>Payment Method:</strong> {{ $payment_method }}</div>
                        @endisset
                    </div>

                    @if(!empty($booking->bookingRooms) && $booking->bookingRooms->count())
                    <div class="section">
                        <div class="section-title">Rooms Booked</div>
                        <table class="table">
                        <thead>
                            <tr>
                            <th>Room</th>
                            <th class="right">Qty</th>
                            <th class="right">Room Number(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roomLines as $line)
                            <tr>
                                <td style="padding: 12px 12px;border:1px solid #bbb;">{{ $line->name ?? 'Room' }}</td>
                                <td style="padding: 12px 12px;border:1px solid #bbb;" class="left">{{ $line->qty ?? 1 }}</td>
                                <td style="padding: 12px 12px;border:1px solid #bbb;" class="left">
                                    @if($line->has_room_numbers)
                                        {{ implode(', ', $line->room_numbers) }}
                                    @else
                                        <em style="color:#666;">To be assigned</em>
                                    @endif
                                </td>
                            </tr>
                            @empty
                                <tr>
                                <td colspan="3">No rooms found</td>
                                </tr>
                            @endforelse
                        </tbody>
                        </table>
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

                    @if(isset($downpayment) && $downpayment > 0 && $downpayment < $booking->final_price)
                    <div class="mt-15" style="margin:16px 16px;">
                        <a href="{{ $frontendBase . '/booking/' . $booking->reference_number . '/payment' }}" class="badge">Settle Remaining Balance</a>
                    </div>
                    @endif

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
                    <div class="box"><div class="box-inner">
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
                                <strong>Website:</strong> <a href="{{ $resort['website'] }}" target="_blank">{{ $resort['website'] }}</a>
                            @endif
                            </p>
                        </td>
                        </tr></table>
                    </div></div>
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

                    <p style="margin:36px 0 0 0;font-size:14px;">Thank you,<br>The {{ $resortName }} Team</p>
                </td>
                </tr>

                @include('emails.partials._footer', ['resort' => $resort])

            </td>
        </tr>
    </table>
</body>
</html>