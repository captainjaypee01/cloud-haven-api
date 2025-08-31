<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proof Payment Notifications
    |--------------------------------------------------------------------------
    |
    | Configuration for payment proof upload notifications sent to staff.
    | These settings control notification behavior and limits per payment.
    |
    */

    'proof_payment' => [
        // Email address to receive proof upload notifications
        'to' => env('PROOF_PAYMENT_NOTIFY_TO', 'proof@netaniadelaiya.com'),

        // Maximum number of proof uploads allowed per payment generation
        'max_uploads' => (int) env('PROOF_PAYMENT_MAX_UPLOADS', 3),

        // Minimum time (in minutes) between notifications for same payment to prevent spam
        'suppress_window_minutes' => (int) env('PROOF_PAYMENT_SUPPRESS_MIN', 5),
    ],
];
