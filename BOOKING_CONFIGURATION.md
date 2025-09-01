# Booking Lock Feature Configuration

## Overview
The booking lock feature has been updated to support flexible hold durations and proof of payment-based logic instead of online payment logic. The system respects admin staff availability and provides grace periods for users to upload new proofs after rejection.

## Environment Variables

Add these to your `.env` file:

```env
# Booking Hold Duration (in hours)
# How long to hold a reservation before checking for cancellation
# Default: 2 hours
BOOKING_RESERVATION_HOLD_HOURS=2

# Scheduler Interval (in minutes) 
# How often to check for expired bookings
# Default: 30 minutes
BOOKING_SCHEDULER_INTERVAL_MINUTES=30

# Proof Rejection Grace Period (in days)
# How many days to wait after rejecting a proof before allowing cancellation
# Default: 2 days
BOOKING_PROOF_REJECTION_GRACE_PERIOD_DAYS=2

# Downpayment Percentage
# Percentage of total booking price required as downpayment
# Default: 0.5 (50%)
BOOKING_DOWNPAYMENT_PERCENT=0.5
```

## Configuration Examples

### Short Hold Duration (1 hour)
```env
BOOKING_RESERVATION_HOLD_HOURS=1
BOOKING_SCHEDULER_INTERVAL_MINUTES=10
```

### Standard Hold Duration (2 hours) - Default
```env
BOOKING_RESERVATION_HOLD_HOURS=2
BOOKING_SCHEDULER_INTERVAL_MINUTES=30
```

### Extended Hold Duration (6 hours)
```env
BOOKING_RESERVATION_HOLD_HOURS=6
BOOKING_SCHEDULER_INTERVAL_MINUTES=60
```

### Long Hold Duration (24 hours)
```env
BOOKING_RESERVATION_HOLD_HOURS=24
BOOKING_SCHEDULER_INTERVAL_MINUTES=120
```

## How It Works

1. **Booking Creation**: When a user completes checkout, a booking is created with `reserved_until` set to current time + configured hours
2. **Redis Lock**: A Redis lock is created with TTL matching the configured duration
3. **Proof of Payment**: Users can upload proof of payment during the hold period
4. **Admin Review**: Staff can accept/reject proof of payment during business hours
5. **Grace Period**: If proof is rejected, user gets 2 days (configurable) to upload new proof
6. **Automatic Cancellation**: The scheduler runs at configured intervals to check for expired bookings
7. **Cancellation Logic**: Bookings are cancelled only if:
   - Status is 'pending'
   - `reserved_until` has passed
   - AND either:
     - No payments exist at all, OR
     - All payments are rejected AND grace period has expired
8. **Manual Cancellation**: Admin staff can manually cancel bookings with specific reasons
9. **Email Notification**: Cancelled bookings trigger an automatic email to the guest

## Benefits

- **Flexible Duration**: Easy to adjust hold time based on business needs
- **Admin-Friendly**: Respects staff availability and business hours
- **Grace Periods**: Users get time to upload new proofs after rejection
- **Manual Control**: Admin staff can manually cancel bookings with reasons
- **Proof-Based Logic**: Bookings protected if proof of payment is uploaded and accepted
- **Efficient Scheduling**: Longer intervals for longer hold durations
- **Staff Flexibility**: Admin can review proofs during business hours
- **Bot Protection**: Based on actual proof upload, not just time
- **Customer Communication**: Clear cancellation notifications

## Migration Notes

- All existing bookings will continue to work with the new system
- Redis locks will automatically use the new TTL for new bookings
- The scheduler frequency can be adjusted without affecting existing functionality
- Email templates are responsive and include all necessary booking details
