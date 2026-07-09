<?php

use App\Mail\BookingConfirmation;
use App\Mail\BookingModification;
use App\Mail\BookingReservation;
use App\Mail\BookingReschedule;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Services\ResortPoliciesPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

function makeOvernightBookingForEmail(array $overrides = []): Booking
{
    $room = Room::factory()->create([
        'name' => 'Garden View - Ground Floor',
        'slug' => 'garden-view-ground-floor',
        'price_per_night' => 15000,
        'room_type' => 'overnight',
        'max_guests' => 8,
    ]);

    $roomQuote = [
        'nights' => [
            [
                'date' => '2026-07-16',
                'rooms' => [
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                ],
            ],
            [
                'date' => '2026-07-17',
                'rooms' => [
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                ],
            ],
            [
                'date' => '2026-07-18',
                'rooms' => [
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                ],
            ],
            [
                'date' => '2026-07-19',
                'rooms' => [
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                    ['room_id' => $room->id, 'slug' => $room->slug, 'rate' => 15000],
                ],
            ],
        ],
        'total_room' => 120000,
        'locked_at' => now()->toIso8601String(),
    ];

    $mealQuote = [
        'nights' => [
            [
                'type' => 'buffet',
                'date' => '2026-07-16',
                'adult_price' => 1700,
                'child_price' => 1000,
                'night_total' => 23800,
            ],
            [
                'type' => 'free_breakfast',
                'date' => '2026-07-17',
                'adult_breakfast_price' => 800,
                'night_total' => 0,
            ],
        ],
        'meal_subtotal' => 23800,
    ];

    $booking = Booking::factory()->create(array_merge([
        'booking_type' => 'overnight',
        'status' => 'pending',
        'check_in_date' => '2026-07-16',
        'check_out_date' => '2026-07-20',
        'guest_name' => 'John Paul Dala',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '88943684',
        'adults' => 14,
        'children' => 2,
        'total_guests' => 16,
        'total_price' => 120000,
        'meal_price' => 23800,
        'extra_guest_fee' => 3500,
        'extra_guest_count' => 5,
        'final_price' => 147300,
        'room_quote_data' => json_encode($roomQuote),
        'meal_quote_data' => json_encode($mealQuote),
    ], $overrides));

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $room->id,
        'adults' => 8,
        'children' => 0,
        'total_guests' => 8,
        'price_per_night' => 15000,
        'total_price' => 60000,
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $room->id,
        'adults' => 6,
        'children' => 2,
        'total_guests' => 8,
        'price_per_night' => 15000,
        'total_price' => 60000,
    ]);

    return $booking->fresh(['bookingRooms.room', 'payments', 'otherCharges']);
}

describe('Booking reservation email', function () {
    it('renders without errors and shows booking totals', function () {
        $booking = makeOvernightBookingForEmail();

        $html = (new BookingReservation($booking))->render();

        expect($html)->toBeString()->not->toBeEmpty();
        expect($html)->toContain($booking->reference_number);
        expect($html)->toContain('John Paul Dala');
        expect($html)->toContain('₱147,300.00');
        expect($html)->toContain('Garden View - Ground Floor');
        expect($html)->toContain('Extra Guest Fees');
        expect($html)->toContain('₱3,500.00');
        expect($html)->toContain('Meal Breakdown');
    });

    it('generates policies PDF attachment with nightly room rates', function () {
        $booking = makeOvernightBookingForEmail();

        $mailable = new BookingReservation($booking);
        $attachments = $mailable->attachments();

        expect($attachments)->toHaveCount(1);
        expect(file_exists($attachments[0]->as ?? ''))->toBeFalse();

        $pdfPath = app(ResortPoliciesPdfService::class)->generatePdf($booking);
        expect(file_exists($pdfPath))->toBeTrue();
        expect(filesize($pdfPath))->toBeGreaterThan(1000);

        $pdfHtml = View::make('pdfs.booking_with_policies', ['booking' => $booking])->render();
        expect($pdfHtml)->toContain('Nightly Room Rates (locked at booking)');
        expect($pdfHtml)->toContain('2026-07-16');
        expect($pdfHtml)->toContain('PHP 30,000.00');
        expect($pdfHtml)->toContain('PHP 120,000.00');

        @unlink($pdfPath);
    });
});

describe('Booking confirmation email', function () {
    it('renders without errors and shows confirmed totals', function () {
        $booking = makeOvernightBookingForEmail(['status' => 'paid']);

        $html = (new BookingConfirmation($booking))->render();

        expect($html)->toContain($booking->reference_number);
        expect($html)->toContain('₱147,300.00');
        expect($html)->toContain('Extra Guest Fees');
    });
});

describe('Booking modification email', function () {
    it('renders without errors after guest count change keeps room total', function () {
        $booking = makeOvernightBookingForEmail([
            'total_price' => 120000,
            'final_price' => 147300,
        ]);

        $html = (new BookingModification($booking, 'Guest count change'))->render();

        expect($html)->toContain($booking->reference_number);
        expect($html)->toContain('Guest count change');
        expect($html)->toContain('₱147,300.00');
    });
});

describe('Booking reschedule email', function () {
    it('renders without errors and shows updated dates', function () {
        $booking = makeOvernightBookingForEmail([
            'check_in_date' => '2026-10-15',
            'check_out_date' => '2026-10-17',
            'total_price' => 90000,
            'final_price' => 90000,
            'meal_price' => 0,
            'extra_guest_fee' => 0,
            'extra_guest_count' => 0,
        ]);

        $html = (new BookingReschedule($booking, '2026-07-16', '2026-07-20'))->render();

        expect($html)->toContain($booking->reference_number);
        expect($html)->toContain('15 Oct 2026');
        expect($html)->toContain('₱90,000.00');
    });
});

describe('Booking email after shorten stay', function () {
    it('reflects shortened snapshot totals in email and PDF', function () {
        $shortenedQuote = [
            'nights' => [
                [
                    'date' => '2026-07-16',
                    'rooms' => [
                        ['room_id' => 1, 'slug' => 'garden-view-ground-floor', 'rate' => 15000],
                        ['room_id' => 1, 'slug' => 'garden-view-ground-floor', 'rate' => 15000],
                    ],
                ],
                [
                    'date' => '2026-07-17',
                    'rooms' => [
                        ['room_id' => 1, 'slug' => 'garden-view-ground-floor', 'rate' => 15000],
                        ['room_id' => 1, 'slug' => 'garden-view-ground-floor', 'rate' => 15000],
                    ],
                ],
                [
                    'date' => '2026-07-18',
                    'rooms' => [
                        ['room_id' => 1, 'slug' => 'garden-view-ground-floor', 'rate' => 15000],
                        ['room_id' => 1, 'slug' => 'garden-view-ground-floor', 'rate' => 15000],
                    ],
                ],
            ],
            'total_room' => 90000,
        ];

        $booking = makeOvernightBookingForEmail([
            'check_out_date' => '2026-07-19',
            'total_price' => 90000,
            'final_price' => 113800,
            'room_quote_data' => json_encode($shortenedQuote),
        ]);

        $booking->bookingRooms->each(fn ($br) => $br->update([
            'price_per_night' => 15000,
            'total_price' => 45000,
        ]));

        $html = (new BookingReservation($booking->fresh(['bookingRooms.room', 'payments', 'otherCharges'])))->render();
        expect($html)->toContain('₱113,800.00');

        $pdfHtml = View::make('pdfs.booking_with_policies', ['booking' => $booking])->render();
        expect($pdfHtml)->toContain('PHP 90,000.00');
        expect($pdfHtml)->toContain('2026-07-18');
        expect($pdfHtml)->not->toContain('2026-07-19');
    });
});
