@php
  $resort = (isset($resort) && is_array($resort)) ? $resort : (config('resort') ?: []);
  $name = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
  $phone = $resort['phone'] ?? '';
  $phoneAlt = $resort['phone_alt'] ?? '';
  $email = $resort['email'] ?? '';
  $facebook = $resort['facebook'] ?? '';
  $website = $resort['frontend_url'] ?? config('app.frontend_url') ?? config('app.url');
  $address1 = $resort['address_line1'] ?? '';
  $address2 = $resort['address_line2'] ?? '';
@endphp
  <div class="content">
    {{-- bottom spacer area if needed --}}
  </div>
</div>

<!-- Footer strip aligned to sample style: left info + right contacts/social -->
<div style="background:#fff3e0;color:#000;border-radius:0 0 12px 12px; padding:20px 32px;">
  <div style="color:#000;">
    <div style="display: inline-block; width: 48%; vertical-align: top; padding-right: 20px;">
      <strong style="color:#000;">{{ $name }}</strong><br>
      @if($address1) {{ $address1 }}<br>@endif
      @if($address2) {{ $address2 }}<br>@endif
      @if($website) Website: <a href="{{ $website }}" target="_blank" style="color:#000;text-decoration:underline;">{{ $website }}</a>@endif
    </div>
    <div style="display: inline-block; width: 48%; vertical-align: top; padding-left: 20px; text-align: right;">
      <strong style="color:#000;">Customer Service</strong><br>
      @if($phone) Phone: {{ $phone }}<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {{ $phoneAlt }}<br>@endif
      @if($email) Email: <a href="mailto:{{ $email }}" style="color:#000;text-decoration:underline;">{{ $email }}</a><br>@endif
      @if($facebook) Facebook: <a href="{{ $facebook }}" target="_blank" style="color:#000;text-decoration:underline;">Visit Page</a>@endif
    </div>
  </div>
</div>

<!-- Email Reply Notice -->
<div style="background:#fff3cd; border:1px solid #ffeaa7; border-radius:4px; padding:12px; margin:16px 32px; font-size:13px; color:#856404;">
  <strong>📧 Email Notice:</strong> Please do not reply to this automated email. For any questions or concerns, please contact us directly using the contact information provided above.
</div>

<div class="small">*** This email notification was sent because of your booking at {{ $name }}. ***</div>