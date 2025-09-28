<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>New Contact Message</title>
</head>
<body>
    @php
        $resort = config('resort') ?: [];
        $resortName = $resort['name'] ?? (config('app.name') ?? 'Netania De Laiya');
        $fmtDateTime = function ($date) { 
            if(!$date) return ''; 
            return \Carbon\Carbon::parse($date)->setTimezone('Asia/Singapore')->isoFormat('DD MMM YYYY HH:mm'); 
        };
    @endphp
    <div class="container">
        <div class="email-wrapper">
            <div class="email-content">
                @include('emails.partials._style')
                @include('emails.partials._header', ['resort' => $resort])

                <div class="content">
                    <p style="margin-bottom:4px;font-size:16px;padding-left:16px;">Hello Admin,</p>
                    <p style="margin-bottom:12px;font-size:15px;padding-left:16px;">You have received a new message through the contact form on {{ $resortName }} website.</p>

                    <!-- Contact Message Details -->
                    <div class="section">
                        <div class="section-title">Contact Message Details</div>
                        <div class="box">
                            <div class="box-inner">
                                <div class="kv"><strong>Name:</strong> {{ $contactMessage->name }}</div>
                                <div class="kv"><strong>Email:</strong> <a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></div>
                                <div class="kv"><strong>Submitted:</strong> {{ $fmtDateTime($contactMessage->submitted_at) }}</div>
                                @if($contactMessage->ip_address)
                                <div class="kv"><strong>IP Address:</strong> {{ $contactMessage->ip_address }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Message Content -->
                    <div class="section">
                        <div class="section-title">Message</div>
                        <div class="box">
                            <div class="box-inner">
                                <div style="white-space: pre-wrap; font-size: 14px; line-height: 1.6; color: #333;">{{ $contactMessage->message }}</div>
                            </div>
                        </div>
                    </div>

                    @if($contactMessage->is_spam)
                    <!-- Spam Warning -->
                    <div class="section">
                        <div class="section-title" style="background: #ffebee; color: #c62828; border-color: #ffcdd2;">⚠️ Spam Detected</div>
                        <div class="box" style="border-color: #ffcdd2;">
                            <div class="box-inner" style="background: #ffebee;">
                                <p style="margin: 0; color: #c62828; font-weight: bold;">This message has been flagged as spam.</p>
                                @if($contactMessage->spam_reason)
                                <p style="margin: 8px 0 0 0; color: #c62828;">Reason: {{ $contactMessage->spam_reason }}</p>
                                @endif
                                <p style="margin: 8px 0 0 0; color: #c62828; font-size: 13px;">Please review before taking any action.</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Quick Actions -->
                    <div class="section">
                        <div class="section-title">Quick Actions</div>
                        <div class="box">
                            <div class="box-inner">
                                <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: bold; color: #2e7d32;">
                                    💡 You can simply reply to this email - it will automatically go to {{ $contactMessage->name }}!
                                </p>
                                <p style="margin: 0 0 12px 0; font-size: 13px; color: #666;">
                                    The Reply-To header is set to {{ $contactMessage->email }}, so your reply will reach the guest directly.
                                </p>
                                <div style="margin-top: 16px;">
                                    <a href="mailto:{{ $contactMessage->email }}?subject=Re: Your message to {{ $resortName }}" 
                                       style="display:inline-block;padding:10px 18px;background:#00B8D4;color:#fff;border-radius:6px;text-decoration:none;margin-right:12px;">
                                        Reply to {{ $contactMessage->name }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="section">
                        <div class="section-title">Additional Information</div>
                        <div class="box">
                            <div class="box-inner">
                                <div class="kv"><strong>Message ID:</strong> #{{ $contactMessage->id }}</div>
                                <div class="kv"><strong>User Agent:</strong> {{ $contactMessage->user_agent ?? 'Not available' }}</div>
                                <div class="kv"><strong>Status:</strong> 
                                    @if($contactMessage->is_spam)
                                        <span style="color: #c62828; font-weight: bold;">Flagged as Spam</span>
                                    @else
                                        <span style="color: #2e7d32; font-weight: bold;">Legitimate Message</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <p style="margin:36px 0 0 0;font-size:14px;padding-left:16px;">Best regards,<br>The {{ $resortName }} System</p>
                </div>

                @include('emails.partials._footer', ['resort' => $resort])

            </div>
        </div>
    </div>
</body>
</html>
