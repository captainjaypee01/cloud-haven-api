<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Successful</title>
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
                        <td><hr style="border:0;height:1px;background:#81D4FA;margin:0 32px 24px 32px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;">
                            <p style="margin-bottom:16px;font-size:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},</p>
                            <p style="margin-bottom:24px;font-size:15px;">We've received your payment successfully. Here are the payment details:</p>
                            <div style="background:#f6f6f6;border-radius:8px;padding:20px 24px;margin-bottom:24px;">
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Reference Number:</strong> {{ $booking->reference_number }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Method:</strong> {{ __("payment.providers.{$payment->provider}") }}</div>
                                @if($payment->transaction_id)
                                    <div style="font-size:15px;margin-bottom:10px;"><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</div>
                                @endif
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Date:</strong> {{ $payment->created_at->setTimezone('Asia/Singapore')->format('F j, Y, g:i a') }}</div>
                            </div>

                            <p style="margin:36px 0 0 0;font-size:14px;">Thank you,<br>The {{ config('app.name') }} Team</p>
                        </td>
                    </tr>
                    
                    @include('emails.partials._footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
