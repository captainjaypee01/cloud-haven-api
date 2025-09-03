<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Verification Issue</title>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <div class="email-content">
                @include('emails.partials._style')
                @include('emails.partials._header')
                
                <div class="content">
                    <p style="margin-bottom:16px;font-size:16px;padding-left:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},</p>
                    
                    <p style="margin-bottom:24px;font-size:15px;padding-left:16px;">We hope this message finds you well. We are writing to inform you about an issue with your recent payment submission for your booking reservation.</p>
                    
                    <div style="background:#fff3e0;border-left:4px solid #FF9800;padding:20px 24px;margin-bottom:24px;margin-left:16px;margin-right:16px;">
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Booking Reference:</strong> {{ $booking->reference_number }}</div>
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</div>
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Method:</strong> {{ __("payment.providers.{$payment->provider}") }}</div>
                        @if($payment->transaction_id)
                            <div style="font-size:15px;margin-bottom:10px;"><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</div>
                        @endif
                        <div style="font-size:15px;"><strong>Issue:</strong> {{ $rejectionReason ?? 'Payment verification pending' }}</div>
                    </div>

                    @if(isset($rejectionReason))
                        <p style="margin-bottom:24px;font-size:15px;padding-left:16px;">After reviewing your submitted proof of payment, we found the following issue that needs to be addressed:</p>
                        
                        <div style="background:#ffebee;border-left:4px solid #f44336;padding:16px 20px;margin-bottom:24px;margin-left:16px;margin-right:16px;">
                            <p style="margin:0;font-size:15px;color:#d32f2f;">{{ $rejectionReason }}</p>
                        </div>
                        
                        <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">Please review the issue above and submit a new proof of payment if needed.</p>
                    @else
                        <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">After reviewing your submitted proof of payment, we were unable to verify the transaction in our bank records at this time. This could be due to:</p>
                        
                        <ul style="margin-bottom:24px;font-size:15px;color:#666;padding-left:32px;">
                            <li>Processing delays by the bank (transactions may take 1-3 business days to reflect)</li>
                            <li>Incorrect account details or reference number</li>
                            <li>Technical issues with the payment processing</li>
                        </ul>
                    @endif

                    <p style="margin-bottom:24px;font-size:15px;padding-left:16px;"><strong>What happens next?</strong></p>
                    
                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">• We will continue monitoring our bank account for your payment</p>
                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">• Your booking reservation is currently on hold</p>
                    <p style="margin-bottom:24px;font-size:15px;padding-left:16px;">• We will notify you immediately once the payment is confirmed</p>

                    <div style="background:#e3f2fd;border-radius:8px;padding:20px 24px;margin-bottom:24px;margin-left:16px;margin-right:16px;">
                        <p style="margin:0;font-size:15px;"><strong>Need assistance?</strong> Please contact us at <a href="mailto:{{ config('resort.email', 'info@netaniadelaiya.com') }}" style="color:#0288D1;">{{ config('resort.email', 'info@netaniadelaiya.com') }}</a> or call {{ config('resort.phone', '+63 949-798-9831') }}. We're here to help resolve this matter quickly.</p>
                    </div>

                    <p style="margin-bottom:16px;font-size:15px;padding-left:16px;">We sincerely apologize for any inconvenience this may cause and appreciate your patience as we work to resolve this matter.</p>

                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Thank you for choosing {{ config('app.name') }},<br>The {{ config('resort.name', 'Netania De Laiya') }} Team</p>
                </div>
                
                @include('emails.partials._footer')
            </div>
        </div>
    </div>
</body>
</html>
