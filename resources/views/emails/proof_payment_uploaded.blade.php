<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Proof Uploaded</title>
</head>
<body>
    @php
        $resort = config('resort') ?: [];
        $resortName = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
        $fmtDate = function ($date) { if(!$date) return ''; return \Carbon\Carbon::parse($date)->format('M d, Y'); };
        $fmtDateTime = function ($date) { if(!$date) return ''; return \Carbon\Carbon::parse($date)->setTimezone('Asia/Singapore')->format('M d, Y H:i'); };
    @endphp
    <table width="100%" bgcolor="#fff" cellpadding="0" cellspacing="0" class="container">
        <tr>
            <td align="center" style="padding:32px 0;">

                @include('emails.partials._style')
                @include('emails.partials._header', ['resort' => $resort])

                <tr>
                    <td class="content">
                        <p style="margin-bottom:4px;font-size:16px;padding-left:16px;">Hello Staff,</p>
                        <p style="margin-bottom:24px;font-size:15px;padding-left:16px;">A new payment proof has been uploaded for booking <strong>{{ $booking->reference_number }}</strong>.</p>

                        <!-- Booking Details -->
                        <div class="section">
                            <div class="section-title">Booking Details</div>
                            <div class="box">
                                <div class="box-inner">
                                    <div class="kv"><strong>Reference Number:</strong> {{ $booking->reference_number }}</div>
                                    <div class="kv"><strong>Guest Name:</strong> {{ $booking->guest_name }}</div>
                                    <div class="kv"><strong>Guest Email:</strong> {{ $booking->guest_email }}</div>
                                    <div class="kv"><strong>Check-in:</strong> {{ $fmtDate($booking->check_in_date) }}</div>
                                    <div class="kv"><strong>Check-out:</strong> {{ $fmtDate($booking->check_out_date) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Details -->
                        <div class="section">
                            <div class="section-title">Payment Details</div>
                            <div class="box">
                                <div class="box-inner">
                                    <div class="kv"><strong>Payment #:</strong> {{ $sequenceNumber }}</div>
                                    <div class="kv"><strong>Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</div>
                                    <div class="kv"><strong>Provider:</strong> {{ __("payment.providers.{$payment->provider}") }}</div>
                                    <div class="kv"><strong>Upload Count:</strong> {{ $payment->proof_upload_count }}/{{ config('notifications.proof_payment.max_uploads', 3) }}</div>
                                    <div class="kv"><strong>Uploaded:</strong> {{ $fmtDateTime($payment->proof_last_uploaded_at) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Required -->
                        <div class="section">
                            <div class="section-title">Action Required</div>
                            <div class="box">
                                <div class="box-inner">
                                    <p class="m-0">Please review and verify the uploaded payment proof at your earliest convenience.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Review Button -->
                        <div class="mt-15" style="margin:16px 16px;">
                            <a href="{{ $adminLink }}" class="badge">Review Payment Proof</a>
                        </div>

                        <p style="margin:36px 0 0 0;font-size:14px;">Best regards,<br>{{ $resortName }} Notification System</p>
                    </td>
                </tr>

                @include('emails.partials._footer', ['resort' => $resort])

            </td>
        </tr>
    </table>
</body>
</html>
