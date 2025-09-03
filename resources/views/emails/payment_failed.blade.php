<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Failed</title>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <div class="email-content">
                @include('emails.partials._style')
                @include('emails.partials._header')
                
                <div class="content">
                    <p style="margin-bottom:16px;font-size:16px;padding-left:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},</p>
                    <p style="margin-bottom:24px;font-size:15px;color:#d44;padding-left:16px;"><strong>Your payment attempt was unsuccessful.</strong></p>
                    
                    <div style="background:#fff3e0;border-left:4px solid #FF9800;padding:20px 24px;margin-bottom:24px;margin-left:16px;margin-right:16px;">
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Booking Reference:</strong> {{ $booking->reference_number }}</div>
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</div>
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Method:</strong> {{ __("payment.providers.{$payment->provider}") }}</div>
                        @if($payment->transaction_id)
                            <div style="font-size:15px;margin-bottom:10px;"><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</div>
                        @endif
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Date:</strong> {{ $payment->created_at->setTimezone('Asia/Singapore')->format('F j, Y, g:i a') }}</div>
                        @if($payment->error_message)
                            <div style="font-size:15px;color:#b71c1c;"><strong>Error:</strong> {{ $payment->error_message }}</div>
                        @endif
                    </div>
                    
                    <p style="font-size:15px;padding-left:16px;">Please try your payment again or contact our customer service if you need assistance.</p>
                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you,<br>The {{ config('app.name') }} Team</p>
                </div>
                
                @include('emails.partials._footer')
            </div>
        </div>
    </div>
</body>
</html>
