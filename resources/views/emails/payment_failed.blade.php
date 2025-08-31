<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Failed</title>
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
                        <td><hr style="border:0;height:1px;background:#FF6F6F;margin:0 32px 24px 32px;"></td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;">
                            <p style="margin-bottom:16px;font-size:16px;">Hi {{ $booking->guest_name ?? $booking->user->name ?? '' }},</p>
                            <p style="margin-bottom:24px;font-size:15px;color:#d44;"><strong>Your payment attempt was unsuccessful.</strong></p>
                            <div style="background:#fbe9e7;border-radius:8px;padding:20px 24px;margin-bottom:24px;">
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Reference Number:</strong> {{ $booking->reference_number }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Amount:</strong> ₱{{ number_format($payment->amount, 2) }}</div>
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Method:</strong> {{ ucfirst($payment->provider) }}</div>
                                @if($payment->transaction_id)
                                    <div style="font-size:15px;margin-bottom:10px;"><strong>Transaction ID:</strong> {{ $payment->transaction_id }}</div>
                                @endif
                                <div style="font-size:15px;margin-bottom:10px;"><strong>Payment Date:</strong> {{ $payment->created_at->format('F j, Y, g:i a') }}</div>
                                @if($payment->error_message)
                                    <div style="font-size:15px;margin-bottom:10px;color:#b71c1c;"><strong>Error:</strong> {{ $payment->error_message }}</div>
                                @endif
                            </div>
                            <p style="font-size:15px;">Please try your payment again or contact our customer service if you need assistance.</p>
                            <p style="margin:36px 0 0 0;font-size:14px;">Thank you,<br>The {{ config('app.name') }} Team</p>
                        </td>
                    </tr>
                </table>
                @include('emails.partials._footer')
            </td>
        </tr>
    </table>
</body>
</html>
