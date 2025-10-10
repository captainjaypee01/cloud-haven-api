# Guest Review System Documentation

## Overview

The Guest Review System allows guests to leave reviews for their stay without requiring login credentials. The system uses secure token-based authentication to ensure only legitimate guests can submit reviews while preventing spam and abuse.

## Features

- **Token-based Security**: Each booking gets a unique, time-limited review token
- **No Login Required**: Guests can review directly from email links
- **One-time Use**: Tokens expire after use or after 30 days
- **Email Notifications**: Automatic review request emails sent 1 day after checkout
- **Spam Prevention**: Multiple validation layers and rate limiting
- **Backward Compatibility**: Existing authenticated review system still works

## Architecture

### Database Schema

#### Bookings Table Additions
```sql
review_token VARCHAR(64) UNIQUE NULL
review_token_expires_at TIMESTAMP NULL
review_email_sent_at TIMESTAMP NULL
review_token_used_at TIMESTAMP NULL
```

#### Reviews Table
The existing reviews table structure is maintained with optional fields:
- `booking_id` - Links to booking (optional for admin-created reviews)
- `user_id` - Links to user (optional for guest reviews)
- `room_id` - Links to room (optional for resort reviews)
- `first_name` - Guest's first name
- `last_name` - Guest's last name
- `type` - 'resort' or 'room'
- `rating` - 1-5 star rating
- `comment` - Review text
- `is_testimonial` - Whether to display as testimonial

### Security Model

1. **Token Generation**: SHA-256 hash of booking reference + timestamp + random bytes
2. **Token Validation**: Checks token exists, not expired, not used, booking eligible
3. **Rate Limiting**: Built-in Laravel throttling on API endpoints
4. **Signed URLs**: Review links use Laravel's signed URL feature for additional security

## API Endpoints

### Public Review Endpoints (No Authentication Required)

#### GET `/api/v1/reviews/booking`
Get booking details for review using token.

**Parameters:**
- `token` (required): 64-character review token

**Response:**
```json
{
  "data": {
    "booking": {
      "reference_number": "NL250127ABC123",
      "guest_name": "John Doe",
      "check_in_date": "2025-01-25",
      "check_out_date": "2025-01-27",
      "total_guests": 2
    },
    "rooms": [
      {
        "slug": "garden-view-ground-floor",
        "name": "Garden View - Ground Floor",
        "units": [
            "301"
        ]
      }
    ],
    "already_reviewed": false // boolean
  }
}
```

#### POST `/api/v1/reviews/submit`
Submit review using token.

**Request Body:**
```json
{
  "token": "abc123...",
  "reviews": [
    {
      "type": "resort",
      "rating": 5,
      "comment": "Great stay!"
    },
    {
      "type": "room",
      "room_id": 1,
      "rating": 4,
      "comment": "Nice room!"
    }
  ]
}
```

**Response:**
```json
{
  "message": "Thank you for your review!",
  "reviews": [...]
}
```

### Web Routes

#### GET `/review/{token}`
Redirects to frontend review page with token.

## Email System

### Review Request Email

**Trigger:** Daily at 10:00 AM via scheduled command
**Recipients:** Guests who checked out 1 day ago
**Template:** `emails.review_request`
**Features:**
- Personalized with guest name and booking details
- Secure signed URL with 30-day expiration
- Professional resort branding
- Clear call-to-action button

### Email Tracking

All review request emails are tracked using the existing `EmailTrackingService`:
- Email queued/sent status
- Delivery tracking
- Click tracking (if implemented)
- Error logging

## Scheduled Jobs

### Daily Review Request Emails
- **Command:** `reviews:send-request-emails`
- **Schedule:** Daily at 10:00 AM
- **Function:** Sends review request emails to guests who checked out 1 day ago

### Manual Testing
- **Command:** `reviews:send-test-email {booking_reference}`
- **Function:** Send test review request email for specific booking

## Frontend Integration

### Public Review Page
- **Route:** `/review/:token`
- **Component:** `PublicReview.jsx`
- **Features:**
  - Token-based access (no login required)
  - Star rating interface
  - Separate resort and room reviews
  - Form validation
  - Success/error handling
  - Responsive design

### User Experience Flow

1. **Guest checks out** → Booking marked as completed
2. **1 day later** → System sends review request email
3. **Guest clicks email link** → Redirected to public review page
4. **Guest submits review** → Token marked as used, booking marked as reviewed
5. **Success confirmation** → Guest sees thank you message

## Security Considerations

### Token Security
- **Length:** 64 characters (SHA-256 hash)
- **Entropy:** Uses booking reference + timestamp + random bytes
- **Expiration:** 30 days from generation
- **One-time use:** Token invalidated after review submission

### Validation Layers
1. **Token Format:** Must be exactly 64 characters
2. **Token Existence:** Must exist in database
3. **Token Expiration:** Must not be expired
4. **Token Usage:** Must not be already used
5. **Booking Eligibility:** Must be completed stay, not already reviewed
6. **Room Validation:** Room reviews must be for booked rooms only

### Rate Limiting
- Built-in Laravel API throttling
- Contact form rate limiting (if applicable)
- Email sending rate limiting via queue system

## Error Handling

### Common Error Scenarios
- **Invalid Token (404):** Token doesn't exist
- **Expired Token (410):** Token expired or already used
- **Already Reviewed (400):** Booking already has reviews
- **Invalid Room (422):** Room not part of booking
- **Validation Errors (422):** Invalid form data

### Error Responses
All errors return consistent JSON format:
```json
{
  "error": "Error message",
  "status": 400
}
```

## Testing

### Test Coverage
- Token validation
- Review submission
- Error scenarios
- Email sending
- Security edge cases

### Manual Testing Commands
```bash
# Send test review email
php artisan reviews:send-test-email NL250127ABC123

# Run review request emails manually
php artisan reviews:send-request-emails
```

## Configuration

### Environment Variables
```env
# Frontend URL for review links
APP_FRONTEND_URL=https://yourdomain.com

# Resort information for emails
RESORT_NAME="Netania De Laiya"
RESORT_EMAIL="netaniadelaiya@gmail.com"
RESORT_PHONE="+63 917 123 4567"
```

### Email Configuration
Uses existing Mailgun configuration for sending review request emails.

## Migration Guide

### For Existing Bookings
Existing bookings will not have review tokens. The system will:
1. Generate tokens when needed (first email send attempt)
2. Maintain backward compatibility with authenticated reviews
3. Not affect existing review data

### For New Bookings
All new bookings will automatically:
1. Be eligible for review requests after checkout
2. Receive review request emails 1 day after checkout
3. Have secure token-based review access

## Monitoring and Analytics

### Email Tracking
- Review request email delivery rates
- Email open rates (if tracking implemented)
- Click-through rates to review page
- Review submission completion rates

### Review Analytics
- Review submission rates by booking type
- Average ratings by room type
- Review response times
- Guest satisfaction trends

## Troubleshooting

### Common Issues

#### Review Emails Not Sending
1. Check scheduled job is running: `php artisan schedule:list`
2. Verify email configuration
3. Check booking eligibility criteria
4. Review email logs

#### Invalid Token Errors
1. Verify token format (64 characters)
2. Check token expiration
3. Confirm booking status
4. Validate token hasn't been used

#### Review Submission Failures
1. Check form validation
2. Verify room IDs match booking
3. Confirm booking eligibility
4. Review error logs

### Debug Commands
```bash
# Check scheduled jobs
php artisan schedule:list

# Test email sending
php artisan reviews:send-test-email {reference}

# Check booking eligibility
php artisan tinker
>>> $booking = Booking::where('reference_number', 'REF123')->first();
>>> $booking->isEligibleForReview();
```

## Future Enhancements

### Potential Improvements
1. **Review Reminders:** Follow-up emails for non-responders
2. **Review Incentives:** Discount codes for completed reviews
3. **Review Analytics:** Dashboard for review insights
4. **Social Sharing:** Share reviews on social media
5. **Review Moderation:** Admin approval for reviews
6. **Multi-language Support:** Localized review forms
7. **Review Photos:** Allow guests to upload photos
8. **Review Responses:** Allow management to respond to reviews

### Integration Opportunities
1. **Google Reviews:** Sync with Google My Business
2. **TripAdvisor:** Export reviews to TripAdvisor
3. **Booking.com:** Sync with booking platform reviews
4. **Analytics Platforms:** Google Analytics, Facebook Pixel
5. **CRM Systems:** Customer relationship management integration
