<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Netania Account Details</title>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <div class="email-content">
                @include('emails.partials._style')
                @include('emails.partials._header')
                
                <div class="content">
                    <p style="margin-bottom:16px;font-size:16px;padding-left:16px;">Hi {{ $firstName }},</p>
                    <p style="margin-bottom:24px;font-size:15px;padding-left:16px;">An account has been created for you. Please use the temporary password below to log in to your account:</p>
                    <div style="background:#f6f6f6;border-radius:8px;padding:20px 24px;margin-bottom:24px;margin-left:16px;margin-right:16px;">
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Email:</strong> {{ $email }}</div>
                        <div style="font-size:15px;margin-bottom:10px;"><strong>Temporary Password:</strong> {{ $password }}</div>
                    </div>
                    <a href="{{ config('app.frontend_url') }}/login" style="display:inline-block;padding:12px 32px;background:#00B8D4;color:#fff;border-radius:6px;text-decoration:none;font-size:16px;font-weight:bold;margin-top:16px;margin-left:16px;">Login Now</a>
                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">For your security, please change your password after logging in.</p>
                    <p style="margin:16px 0 0 0;font-size:14px;padding-left:16px;">Thank you,<br>The {{ config('app.name') }} Team</p>
                </div>
                
                @include('emails.partials._footer')
            </div>
        </div>
    </div>
</body>
</html>
