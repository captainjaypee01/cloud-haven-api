# Logging Standards for Cloud Haven API

## Overview
This document outlines the logging standards and best practices for the Cloud Haven API application. These standards ensure consistent, meaningful, and actionable logging across the application.

## Log Levels

### INFO Level
Use for important business events and successful operations:
- Booking creation and status changes
- Payment processing and confirmations
- Email notifications sent
- File uploads completed
- CRUD operations (Create, Update, Delete)
- User authentication events

### WARNING Level
Use for potentially problematic situations that don't stop execution:
- Room unit assignment failures
- Email delivery issues
- File processing warnings
- Data validation warnings

### ERROR Level
Use for error conditions that prevent normal operation:
- Database connection failures
- Payment processing errors
- File upload failures
- Critical system errors

## Logging Format

### Structured Logging
All logs should use structured format with context arrays:

```php
Log::info('Action description', [
    'key1' => 'value1',
    'key2' => 'value2',
    'user_id' => auth()->id(),
    'booking_id' => $booking->id,
    'reference_number' => $booking->reference_number
]);
```

### Required Context Fields

#### For Booking Operations
- `booking_id`: The internal booking ID
- `reference_number`: The customer-facing reference number
- `guest_email`: Customer email address
- `status_changed_from` / `status_changed_to`: For status changes

#### For Payment Operations
- `payment_id`: The payment record ID
- `booking_id`: Associated booking ID
- `reference_number`: Booking reference number
- `payment_amount`: Amount of the payment
- `payment_provider`: Payment method used

#### For Email Operations
- `email`: Recipient email address
- `email_type`: Type of email (confirmation, payment_success, etc.)
- `booking_id`: Associated booking ID
- `reference_number`: Booking reference number

#### For File Upload Operations
- `file_count`: Number of files uploaded
- `file_sizes`: Array of file sizes
- `file_types`: Array of MIME types
- `upload_count`: Current upload attempt number

#### For Admin Operations
- `admin_user_id`: ID of the admin performing the action
- `target_user_id` / `target_room_id`: ID of the resource being modified
- `updated_fields`: Array of field names being updated

## Specific Logging Requirements

### Booking Service
- Log booking creation start and completion
- Log payment status changes with before/after states
- Log room unit assignment success/failure
- Log email notifications queued

### Payment Service
- Log payment processing start and completion
- Log email notifications sent (confirmation, success, failure)
- Log payment status changes

### Payment Proof Service
- Log file upload attempts with file metadata
- Log upload success/failure with detailed error information
- Log proof status changes (accepted/rejected)

### Admin Controllers
- Log all CRUD operations (Create, Read, Update, Delete)
- Include admin user ID and target resource information
- Log field-level changes for updates

### File Upload Operations
- Log upload start with file metadata
- Log completion with success details
- Log failures with error context

## Frontend Logging

### Console Logs
- **REMOVE**: All debug console.log statements
- **KEEP**: Only critical error logging with console.error
- **AVOID**: console.warn and console.info in production

### Error Handling
- Log API errors with meaningful context
- Include user action that triggered the error
- Avoid logging sensitive information

## Log Monitoring

### Key Metrics to Monitor
1. **Booking Creation Rate**: Track successful vs failed bookings
2. **Payment Success Rate**: Monitor payment processing success
3. **Email Delivery**: Track email notification success rates
4. **File Upload Success**: Monitor file upload completion rates
5. **Admin Activity**: Track admin CRUD operations

### Alert Conditions
- High error rates in payment processing
- Email delivery failures
- File upload failures
- Database connection issues
- Unusual admin activity patterns

## Implementation Examples

### Booking Creation
```php
Log::info('Starting booking creation process', [
    'guest_email' => $bookingData->guest_email,
    'guest_name' => $bookingData->guest_name,
    'check_in_date' => $bookingData->check_in_date,
    'check_out_date' => $bookingData->check_out_date,
    'total_adults' => $bookingData->total_adults,
    'total_children' => $bookingData->total_children,
    'user_id' => $userId,
    'room_count' => count($bookingData->rooms)
]);
```

### Payment Processing
```php
Log::info('Payment processed successfully', [
    'payment_id' => $payment->id,
    'booking_id' => $booking->id,
    'reference_number' => $booking->reference_number,
    'payment_amount' => $payment->amount,
    'payment_provider' => $payment->provider,
    'status' => $payment->status
]);
```

### Email Notification
```php
Log::info('Booking confirmation email queued', [
    'booking_id' => $booking->id,
    'reference_number' => $booking->reference_number,
    'email' => $booking->guest_email,
    'email_type' => 'booking_confirmation',
    'payment_amount' => $payment->amount
]);
```

### Admin CRUD Operation
```php
Log::info('Admin updating room', [
    'admin_user_id' => $request->user()->id,
    'room_id' => $room,
    'updated_fields' => array_keys($validatedData)
]);
```

## Security Considerations

### Sensitive Data
- **NEVER LOG**: Passwords, API keys, or personal identification numbers
- **LIMIT LOGGING**: Credit card numbers, bank account details
- **ANONYMIZE**: Consider logging user IDs instead of emails for privacy

### Data Retention
- Follow data retention policies for log files
- Implement log rotation to manage disk space
- Consider log aggregation for long-term storage

## Performance Considerations

- Use appropriate log levels to avoid performance impact
- Avoid logging in tight loops or high-frequency operations
- Consider asynchronous logging for high-volume operations
- Monitor log file sizes and implement rotation

## Review and Maintenance

- Regularly review log patterns for optimization opportunities
- Update logging standards as the application evolves
- Train team members on logging best practices
- Monitor log quality and completeness
