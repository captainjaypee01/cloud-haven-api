@php
  $resort = (isset($resort) && is_array($resort)) ? $resort : (config('resort') ?: []);
  $logo = $resort['logo_url'] ?? 'https://res.cloudinary.com/dm3gsotk5/image/upload/v1753969657/netania-logo.jpg';
  $name = $resort['name'] ?? (config('app.name') ?? 'Your Resort');
  $city = $resort['city'] ?? '';
  $cover = $resort['cover_url'] ?? '';
  $coverAlt = $resort['cover_alt'] ?? ($name . ' cover');
@endphp
<div class="card">
    <div class="header-logo">
        <img src="{{ $logo }}" alt="{{ $name }}" style="height:45px; margin: 0 auto;">
        <h1 style="padding:8px 0 0 0;font-size:20px;line-height:1;margin:0;color:#000; font-weight:700;text-align:center;">{{ $name }}</h1>
        @if(!empty($city))
            <h2 style="padding:6px 0 0 0;font-size:14px;line-height:1;margin:0;color:#000; font-weight:500;text-align:center;">{{ $city }}</h2>
        @endif
    </div>
    @if(!empty($cover))
        <div style="padding:0 16px 8px 16px;">
            <img src="{{ $cover }}" alt="{{ $coverAlt }}" class="cover-img">
        </div>
    @endif
    <hr class="hr">