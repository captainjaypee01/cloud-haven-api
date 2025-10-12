# Booking Modification Feature

## Overview

The booking modification feature allows admin users to modify existing bookings by adding, removing, or updating rooms and guest counts. This feature is particularly useful when guests change their mind about room selections or guest counts after booking.

## Features

### Room Management
- **Add Rooms**: Add additional rooms to an existing booking
- **Remove Rooms**: Remove rooms from an existing booking (minimum 1 room required)
- **Update Guest Counts**: Modify the number of adults and children per room

### Automatic Recalculation
- **Room Pricing**: Automatically recalculates room costs based on new configuration
- **Meal Pricing**: Uses existing meal quote data to recalculate meal costs
- **Extra Guest Fees**: Calculates extra guest fees for buffet days and free breakfast days
- **Promo Discounts**: Recalculates promo discounts based on new totals
- **Final Pricing**: Updates all pricing fields including total_price, meal_price, extra_guest_fee, and final_price

### Validation & Safety
- **Room Availability**: Checks room availability before allowing modifications
- **Booking Status**: Only allows modifications for pending and downpayment bookings
- **Guest Limits**: Enforces maximum guest limits per room
- **Minimum Requirements**: Ensures at least one room is always present

## API Endpoints

### Modify Booking
```
PATCH /api/v1/admin/bookings/{bookingId}/modify
```

**Request Body:**
```json
{
    "rooms": [
        {
            "room_id": "room-slug",
            "adults": 2,
            "children": 1,
            "total_guests": 3
        }
    ],
    "modification_reason": "Guest requested room change"
}
```

**Response:**
```json
{
    "data": {
        "id": 1,
        "reference_number": "CH-2024-001",
        "total_price": 2000,
        "meal_price": 500,
        "extra_guest_fee": 200,
        "final_price": 2700,
        "booking_rooms": [...]
    }
}
```

## Frontend Components

### BookingRoomModificationDialog
A comprehensive dialog component that allows admin users to:
- View current booking information
- Add/remove rooms with real-time validation
- Update guest counts per room
- See estimated pricing updates
- Provide modification reason

### Integration with BookingDetailsContent
- Added "Modify Rooms" button in the booking actions section
- Only visible for pending and downpayment bookings
- Available to staff, admin, and superadmin roles

## Business Logic

### Pricing Calculation
1. **Room Costs**: Calculated based on room price per night × number of nights
2. **Meal Costs**: Uses existing meal quote data to calculate meal pricing
3. **Extra Guest Fees**: 
   - Buffet days: Extra guests pay buffet meal price + entrance/amenity fee
   - Free breakfast days: Extra guests pay all-inclusive fee (breakfast + entrance + amenities)
4. **Promo Discounts**: Recalculated based on new totals and existing promo rules

### Availability Checking
- Excludes the current booking from availability calculations
- Checks room availability for the booking's date range
- Ensures sufficient room units are available for the new configuration

### Email Notifications
- Sends modification confirmation email to guest
- Includes updated booking details and pricing breakdown
- Includes modification reason if provided

## Permissions

- **Staff**: Can modify bookings
- **Admin**: Can modify bookings
- **Superadmin**: Can modify bookings
- **User**: Cannot modify bookings (admin feature only)

## Limitations

- Only pending and downpayment bookings can be modified
- Paid bookings cannot be modified (business rule)
- At least one room must always be present
- Room availability must be sufficient for the new configuration

## Technical Implementation

### Backend
- **DTOs**: `BookingModificationData`, `BookingRoomModificationData`
- **FormRequest**: `BookingModificationRequest` for validation
- **Action**: `ModifyBookingAction` for business logic
- **Controller**: `BookingController@modifyBooking`
- **Email**: `BookingModification` mailable

### Frontend
- **Hook**: `useBookingModification` for API calls
- **Component**: `BookingRoomModificationDialog` for UI
- **Integration**: Updated `BookingDetailsContent` with modification button

### Database
- Updates existing `booking_rooms` records
- Recalculates and updates booking totals
- Maintains audit trail through logging

## Testing

The feature includes comprehensive tests covering:
- Successful booking modifications
- Validation of booking status restrictions
- Room availability checking
- Input validation
- Error handling

## Future Enhancements

Potential future improvements could include:
- Bulk room modifications
- Room type changes (with availability checking)
- Date range modifications (separate from reschedule)
- Modification history tracking
- Approval workflows for certain modifications
