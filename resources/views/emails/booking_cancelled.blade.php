<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Cancelled</title>
</head>
<body>
    @php
        use Carbon\Carbon;
        $resort = config('resort') ?: [];
        $fmtDate = function ($date) { if(!$date) return ''; return Carbon::parse($date)->isoFormat('DD MMM YYYY'); };
        $fmtDateTime = function ($date) { if(!$date) return ''; return Carbon::parse($date)->setTimezone('Asia/Singapore')->isoFormat('DD MMM YYYY HH:mm'); };
        $fmtMoney = fn($v) => '₱' . number_format((float)$v, 2);
        $isDayTour = $booking->isDayTour();
        $nights = 0;
        if (!empty($booking?->check_in_date) && !empty($booking?->check_out_date)) {
            $nights = Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date));
        }
        $frontendBase = rtrim(($resort['frontend_url'] ?? (config('app.frontend_url') ?? config('app.url'))), '/');
        $resortName = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
        $holdHours = config('booking.reservation_hold_duration_hours', 2);
        $isManualCancellation = $isManualCancellation ?? false;
        $reason = $reason ?? null;
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
                    @if($isManualCancellation)
                        <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">We regret to inform you that your {{ $isDayTour ? 'Day Tour' : 'accommodation' }} booking reservation has been <strong>cancelled</strong> by our administrative staff.</p>
                    @else
                        <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">We regret to inform you that your {{ $isDayTour ? 'Day Tour' : 'accommodation' }} booking reservation has been <strong>cancelled</strong> due to no proof of payment being received within the required timeframe.</p>
                    @endif

                    <!-- Cancellation Status -->
                    <div class="section" style="padding-top:0;">
                    <div class="section-title">Cancellation Notice</div>
                    <div class="box">
                        <div class="box-inner">
                        <p class="m-0"><strong>Status:</strong> Cancelled</p>
                        @if($isManualCancellation && $reason)
                            <p class="m-0"><strong>Reason:</strong> {{ $reason }}</p>
                        @else
                            <p class="m-0"><strong>Reason:</strong> No proof of payment received within {{ $holdHours }} hour(s)</p>
                        @endif
                        @if(!empty($booking->reserved_until))
                            <p class="m-0"><strong>Hold Expired:</strong> {{ $fmtDateTime($booking->reserved_until) }}</p>
                        @endif
                        </div>
                    </div>
                    </div>

                    <!-- Core booking facts -->
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
                        <div class="kv"><strong>Total Amount:</strong> {{ $fmtMoney($booking->final_price) }}</div>
                    </div>

                    @if(!empty($booking->bookingRooms) && $booking->bookingRooms->count())
                    <div class="section">
                        <div class="section-title">Cancelled Rooms</div>
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
                                <td style="padding: 12px 12px;border:1px solid #bbb;" class="right">{{ $line->qty ?? 1 }}</td>
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

                    <!-- What happened section -->
                    <div class="section">
                        <div class="section-title">What Happened?</div>
                        <div class="box"><div class="box-inner">
                            @if($isManualCancellation)
                                <p style="margin:0 0 12px 0;">Your booking has been cancelled by our administrative staff.</p>
                                @if($reason)
                                    <p style="margin:0 0 12px 0;"><strong>Reason:</strong> {{ $reason }}</p>
                                @endif
                                <p style="margin:0;">The reservation has been cancelled to make the rooms available for other guests.</p>
                            @else
                                <p style="margin:0 0 12px 0;">Your booking was held for <strong>{{ $holdHours }} hour(s)</strong> to allow time for proof of payment submission. Unfortunately, we did not receive an accepted proof of payment within this timeframe.</p>
                                <p style="margin:0;">The reservation has been automatically cancelled to make the rooms available for other guests.</p>
                            @endif
                        </div></div>
                    </div>

                    <!-- Book again section -->
                    <div class="section">
                        <div class="section-title">Want to Book Again?</div>
                        <div class="box"><div class="box-inner" style="text-align:center;">
                            <p style="margin:0 0 16px 0;">If you're still interested in staying with us, you can make a new booking:</p>
                            <a href="{{ $frontendBase }}" class="badge" style="text-decoration:none;">Make a New Booking</a>
                        </div></div>
                    </div>

                    <!-- Contact section -->
                    <div class="section">
                        <div class="section-title">Need Help?</div>
                        <div class="box"><div class="box-inner">
                            <p style="margin:0 0 8px 0;">If you believe this cancellation was made in error or if you have any questions, please contact us immediately:</p>
                            <ul style="margin:8px 0 0 18px; padding:0;">
                                @if(!empty($resort['email']))
                                    <li><strong>Email:</strong> <a href="mailto:{{ $resort['email'] }}" style="color:#000;text-decoration:underline;">{{ $resort['email'] }}</a></li>
                                @endif
                                @if(!empty($resort['phone']))
                                    <li><strong>Phone:</strong> {{ $resort['phone'] }}</li>
                                @endif
                            </ul>
                        </div></div>
                    </div>

                    <p class="note" style="padding-left:16px;">We apologize for any inconvenience this may have caused and look forward to serving you in the future.</p>
                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you,<br>The {{ $resortName }} Team</p>
                </div>

                @include('emails.partials._footer', ['resort' => $resort])

            </div>
        </div>
    </div>
</body>
</html>
