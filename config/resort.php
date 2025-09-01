<?php
return [
    'name' => env('RESORT_NAME', config('app.name', 'Your Resort')),
    'logo_url' => env('RESORT_LOGO_URL', 'https://res.cloudinary.com/dm3gsotk5/image/upload/v1753969657/netania-logo.jpg'),
    'cover_url' => env('RESORT_COVER_URL', ''),
    'cover_alt' => env('RESORT_COVER_ALT', 'Resort Cover Photo'),
    'website' => env('RESORT_WEBSITE', config('app.frontend_url', config('app.url'))),
    'email' => env('RESORT_EMAIL', 'info@example.com'),
    'phone' => env('RESORT_PHONE', '+63 900 000 0000'),
    'address_line1' => env('RESORT_ADDRESS_LINE1', 'Laiya, San Juan, Batangas'),
    'address_line2' => env('RESORT_ADDRESS_LINE2', ''),
    'city' => env('RESORT_CITY', 'San Juan'),
    'country' => env('RESORT_COUNTRY', 'Philippines'),
    'facebook' => env('RESORT_FACEBOOK', 'https://www.facebook.com/profile.php?id=100064182843841'),
    'maps_link' => env('RESORT_MAPS_LINK', ''),
    'frontend_url' => env('FRONTEND_URL', config('app.frontend_url', config('app.url'))),

    // Optional: quick policy text blocks (edit freely or remove if you don’t want them here)
    'policy' => [
        'guarantee' => env('RESORT_POLICY_GUARANTEE', 'Full payment is required before the option date or prior to check-in.'),
        'non_refundable' => env('RESORT_POLICY_NON_REFUNDABLE', 'All paid bookings are non-refundable.'),
        'no_show' => env('RESORT_POLICY_NO_SHOW', 'Guests will be charged the full amount in the event of a No Show.'),
        'force_majeure' => env('RESORT_POLICY_FORCE_MAJEURE', 'The resort is not liable for services not rendered due to Force Majeure.'),
    ],

    // Default check-in/out times (used in confirmation email)
    'check_in_time' => env('RESORT_CHECK_IN_TIME', '14:00'),
    'check_out_time' => env('RESORT_CHECK_OUT_TIME', '12:00'),
];
