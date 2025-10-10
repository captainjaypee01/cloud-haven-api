<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>How was your stay?</title>
</head>
<body>
    @php
        $resort = config('resort') ?: [];
        $resortName = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
        $fmtDate = function ($date) { if(!$date) return ''; return \Carbon\Carbon::parse($date)->format('M d, Y'); };
        $isDayTour = ($booking->booking_type ?? 'overnight') === 'day_tour';
    @endphp
    <div class="container">
        <div class="email-wrapper">
            <div class="email-content">
                @include('emails.partials._style')
                @include('emails.partials._header', ['resort' => $resort])

                <div class="content">
                    <p style="margin-bottom:4px;font-size:16px;padding-left:16px;">Dear {{ $booking->guest_name }},</p>
                    <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">We hope you had a wonderful stay at {{ $resortName }} during your recent visit!</p>
                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">Your feedback is incredibly valuable to us and helps us improve our services for future guests. We would be grateful if you could take a few minutes to share your experience.</p>

                    <!-- Stay Details -->
                    <div class="section">
                        <div class="section-title">Your Stay Details</div>
                        <div class="box">
                            <div class="box-inner">
                                <div class="kv"><strong>Booking Reference:</strong> {{ $booking->reference_number }}</div>
                                @if($isDayTour)
                                    <div class="kv"><strong>Day Tour Date:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                                    <div class="kv"><strong>Tour Hours:</strong> 8:00 AM - 5:00 PM</div>
                                @else
                                    <div class="kv"><strong>Check-in:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                                    <div class="kv"><strong>Check-out:</strong> {{ $fmtDate($booking->check_out_date) }}</div>
                                @endif
                                <div class="kv"><strong>Total Guests:</strong> {{ $booking->total_guests }} {{ $booking->total_guests == 1 ? 'guest' : 'guests' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- What You Can Review -->
                    <div class="section">
                        <div class="section-title">What You Can Review</div>
                        <div class="box">
                            <div class="box-inner">
                                <div style="margin-bottom: 12px;">
                                    <strong>🏨 Overall Resort Experience</strong><br>
                                    <span style="font-size: 13px; color: #666;">How was your stay overall? Rate our facilities, service, and overall experience.</span>
                                </div>
                                <div>
                                    <strong>🛏️ Room Experience</strong><br>
                                    <span style="font-size: 13px; color: #666;">How was your room and its amenities? Rate cleanliness, comfort, and facilities.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Review Button -->
                    <div class="mt-15" style="margin:16px 16px; text-align: center;">
                        <a href="{{ $reviewUrl }}" class="badge" style="display:inline-block;padding:12px 32px;background:#00B8D4;color:#fff;border-radius:6px;font-size:16px;font-weight:700;text-decoration:none;">Leave a Review</a>
                    </div>

                    <!-- Contact Information -->
                    <div class="section">
                        <div class="section-title">Need Help?</div>
                        <div class="box">
                            <div class="box-inner">
                                <p class="m-0">If you have any questions or concerns, please don't hesitate to contact us:</p>
                                <div style="margin-top: 12px;">
                                    <div class="kv"><strong>Email:</strong> <a href="mailto:{{ $resort['email'] ?? 'netaniadelaiya@gmail.com' }}">{{ $resort['email'] ?? 'netaniadelaiya@gmail.com' }}</a></div>
                                    <div class="kv"><strong>Phone:</strong> {{ $resort['phone'] ?? '+63 917 123 4567' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you for choosing {{ $resortName }}!</p>
                    <p style="margin:8px 0 0 0;font-size:14px;padding-left:16px;">Best regards,<br>The {{ $resortName }} Team</p>
                    
                    <div class="small" style="font-size:11px; color:#666; text-align:center; margin-top:20px; padding: 16px 32px; background: #fff;">
                        <p class="m-0">This review link is unique to your booking and will expire in 30 days. If you have any issues accessing the review form, please contact us directly.</p>
                    </div>
                </div>

                @include('emails.partials._footer', ['resort' => $resort])

            </div>
        </div>
    </div>
</body>
</html>
