<?php

return [
    'downpayment_percent' => (float) env('BOOKING_DOWNPAYMENT_PERCENT', 0.5), // Default 50%
    'reservation_hold_duration_hours' => (int) env('BOOKING_RESERVATION_HOLD_HOURS', 2), // Default 2 hours
    'scheduler_interval_minutes' => (int) env('BOOKING_SCHEDULER_INTERVAL_MINUTES', 30), // Default 30 minutes
    'proof_rejection_grace_period_days' => (int) env('BOOKING_PROOF_REJECTION_GRACE_PERIOD_DAYS', 2), // Default 2 days
    'reactivation_max_hold_hours' => (int) env('BOOKING_REACTIVATION_MAX_HOLD_HOURS', 72), // Max hold an admin can grant when reactivating/extending

    /*
    |--------------------------------------------------------------------------
    | Reactivatable Cancellation Reasons
    |--------------------------------------------------------------------------
    |
    | Keys of cancellation_reasons that an admin may reverse with the
    | "Reactivate" action. Only automatic hold-expiry cancellations qualify;
    | manual cancellations and rejected proofs stay cancelled.
    |
    */
    'reactivatable_cancellation_reasons' => ['no_payment_received', 'rejected_proof_expired'],
    
    /*
    |--------------------------------------------------------------------------
    | Booking Cancellation Reasons
    |--------------------------------------------------------------------------
    |
    | Centralized cancellation reasons used for both manual admin cancellations
    | and automatic system cancellations.
    |
    */
    'cancellation_reasons' => [
        // System automatic cancellation reasons
        'no_payment_received' => 'No proof of payment received within the required timeframe',
        'rejected_proof_expired' => 'Proof of payment rejected and grace period expired',
        'proof_rejected_invalid' => 'Proof of payment rejected - invalid or unacceptable',
        
        // Manual admin cancellation reasons
        'guest_request' => 'Cancelled at guest request',
        'invalid_booking' => 'Invalid or duplicate booking',
        'failed_booking_attempt' => 'Failed booking attempt',
        'system_error' => 'System error or technical issue',
        'operational_issue' => 'Operational or facility issue',
        'other' => 'Other reason'
    ],
];
