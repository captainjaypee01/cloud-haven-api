<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Verification Issue</title>
</head>
<body style="background:#fff;font-family:Arial,sans-serif;margin:0;padding:0;">
    <table width="100%" bgcolor="#fff" cellpadding="0" cellspacing="0" style="margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 0;">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(60,72,88,.08);border:1px solid #eee;">
                    <tr>
                        <td style="text-align:center;padding:32px 0 12px 0;">
                            <img src="https://res.cloudinary.com/dm3gsotk5/image/upload/v1753969657/netania-logo.jpg" style="height:45px;">
                        </td>
                    </tr>
                    <tr>
                        <td><hr style="border:0;height:1px;background:#FF9800;margin:0 32px 24px 32px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;">
                            <p style="margin-bottom:16px;font-size:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},</p>
                            
                            <p style="margin-bottom:24px;font-size:15px;">We hope this message finds you well. We are writing to inform you about an issue with your recent payment submission for your booking reservation.</p>
                            
                            <div style="background:#fff3e0;border-left:4px solid #FF9800;padding:20px 24px;margin-bottom:24px;">
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Booking Reference:</strong> {{ $booking->reference_number }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Method:</strong> {{ ucfirst($payment->provider) }}</div>
                                @if($payment->transaction_id)
                                    <div style="font-size:15px;margin-bottom:10px;"><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</div>
                                @endif
                                <div style="font-size:15px;"><strong>Issue:</strong> {{ $rejectionReason ?? 'Payment verification pending' }}</div>
                            </div>

                            @if(isset($rejectionReason))
                                <p style="margin-bottom:24px;font-size:15px;">After reviewing your submitted proof of payment, we found the following issue that needs to be addressed:</p>
                                
                                <div style="background:#ffebee;border-left:4px solid #f44336;padding:16px 20px;margin-bottom:24px;">
                                    <p style="margin:0;font-size:15px;color:#d32f2f;">{{ $rejectionReason }}</p>
                                </div>
                                
                                <p style="margin-bottom:16px;font-size:15px;">Please review the issue above and submit a new proof of payment if needed.</p>
                            @else
                                <p style="margin-bottom:16px;font-size:15px;">After reviewing your submitted proof of payment, we were unable to verify the transaction in our bank records at this time. This could be due to:</p>
                                
                                <ul style="margin-bottom:24px;font-size:15px;color:#666;">
                                    <li>Processing delays by the bank (transactions may take 1-3 business days to reflect)</li>
                                    <li>Incorrect account details or reference number</li>
                                    <li>Technical issues with the payment processing</li>
                                </ul>
                            @endif

                            <p style="margin-bottom:24px;font-size:15px;"><strong>What happens next?</strong></p>
                            
                            <p style="margin-bottom:16px;font-size:15px;">• We will continue monitoring our bank account for your payment</p>
                            <p style="margin-bottom:16px;font-size:15px;">• Your booking reservation is currently on hold</p>
                            <p style="margin-bottom:24px;font-size:15px;">• We will notify you immediately once the payment is confirmed</p>

                            <div style="background:#e3f2fd;border-radius:8px;padding:20px 24px;margin-bottom:24px;">
                                <p style="margin:0;font-size:15px;"><strong>Need assistance?</strong> Please contact us at <a href="mailto:info@netaniadelaiya.com" style="color:#0288D1;">info@netaniadelaiya.com</a> or call +63 949-798-9831. We're here to help resolve this matter quickly.</p>
                            </div>

                            <p style="margin-bottom:16px;font-size:15px;">We sincerely apologize for any inconvenience this may cause and appreciate your patience as we work to resolve this matter.</p>

                            <p style="margin:36px 0 0 0;font-size:14px;">Thank you for choosing {{ config('app.name') }},<br>The Netania De Laiya Team</p>
                        </td>
                    </tr>
                    
                    @include('emails.partials._footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
