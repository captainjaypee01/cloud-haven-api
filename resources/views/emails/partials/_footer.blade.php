@php
  $resort = (isset($resort) && is_array($resort)) ? $resort : (config('resort') ?: []);
  $name = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
  $phone = $resort['phone'] ?? '';
  $email = $resort['email'] ?? '';
  $facebook = $resort['facebook'] ?? '';
  $website = $resort['website'] ?? '';
  $address1 = $resort['address_line1'] ?? '';
  $address2 = $resort['address_line2'] ?? '';
@endphp
  <tr>
    <td class="content">
      {{-- bottom spacer area if needed --}}
    </td>
  </tr>
</table>

<!-- Footer strip aligned to sample style: left info + right contacts/social -->
<table width="600" cellpadding="0" cellspacing="0" style="background:#111;color:#fff;border-radius:0 0 8px 8px;">
  <tr>
    <td style="padding:20px 32px;">
      <table width="100%" cellpadding="0" cellspacing="0" style="color:#fff;">
        <tr>
          <td valign="top" align="left" style="padding-right:10px;">
            <strong>{{ $name }}</strong><br>
            @if($address1) {{ $address1 }}<br>@endif
            @if($address2) {{ $address2 }}<br>@endif
            @if($website) Website: <a href="{{ $website }}" target="_blank" style="color:#fff;text-decoration:underline;">{{ $website }}</a>@endif
          </td>
          <td valign="top" align="right" style="padding-left:10px;">
            <strong>Customer Service</strong><br>
            @if($phone) Phone: {{ $phone }}<br>@endif
            @if($email) Email: <a href="mailto:{{ $email }}" style="color:#fff;text-decoration:underline;">{{ $email }}</a><br>@endif
            @if($facebook) Facebook: <a href="{{ $facebook }}" target="_blank" style="color:#fff;text-decoration:underline;">Visit Page</a>@endif
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<div class="small">*** This email notification was sent because of your booking at {{ $name }}. ***</div>