<?php

return [
    'downpayment_percent' => env('BOOKING_DOWNPAYMENT_PERCENT', 0.5), // Default 50%
    'reservation_hold_duration_hours' => env('BOOKING_RESERVATION_HOLD_HOURS', 2), // Default 2 hours
    'scheduler_interval_minutes' => env('BOOKING_SCHEDULER_INTERVAL_MINUTES', 30), // Default 30 minutes
    'proof_rejection_grace_period_days' => env('BOOKING_PROOF_REJECTION_GRACE_PERIOD_DAYS', 2), // Default 2 days
];
