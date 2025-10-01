<?php

namespace Tests\Feature\Http\Controllers\API\V1\Admin;

use App\Models\User;
use App\Models\Room;
use App\Models\Booking;
use App\Enums\RoomUnitStatusEnum;
use App\Models\RoomUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

describe('Walk-In Booking Controller', function () {
    beforeEach(function () {
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        
        // Seed required data for day tour bookings
        $this->seed(\Database\Seeders\DayTourPricingSeeder::class);
        $this->seed(\Database\Seeders\MealProgramSeeder::class);
        $this->seed(\Database\Seeders\MealPriceSeeder::class);
        
        // Create overnight room for overnight booking tests
        $this->overnightRoom = Room::factory()->create([
            'name' => 'Test Overnight Room',
            'slug' => 'test-overnight-room',
            'room_type' => 'overnight',
            'quantity' => 2,
            'price_per_night' => 1000,
            'max_guests' => 2,
            'extra_guests' => 2,
        ]);
        
        // Create day tour room for day tour booking tests
        $this->dayTourRoom = Room::factory()->create([
            'name' => 'Test Day Tour Room',
            'slug' => 'test-day-tour-room',
            'room_type' => 'day_tour',
            'quantity' => 2,
            'max_guests' => 10,
            'min_guests' => 2,
            'max_guests_range' => 10,
            'price_per_night' => 500, // Use price_per_night as base price for day tours
        ]);
        
        // Create available room units for overnight room
        RoomUnit::create([
            'room_id' => $this->overnightRoom->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);
        
        RoomUnit::create([
            'room_id' => $this->overnightRoom->id,
            'unit_number' => '102',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);
    });

    test('admin can create day tour walk-in booking without nights field', function () {
        $payload = [
            'booking_type' => 'day_tour',
            'local_date' => now()->format('Y-m-d'),
            'rooms' => [
                [
                    'room_id' => $this->dayTourRoom->slug,
                    'quantity' => 1,
                    'adults' => 2,
                    'children' => 0,
                    'include_lunch' => false,
                    'include_pm_snack' => false,
                ]
            ],
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Test request',
        ];

        $response = $this->withHeader('X-TEST-USER-ID', $this->adminUser->id)
            ->postJson('/api/v1/admin/bookings/walk-in', $payload);

        $response->assertStatus(201);
        
        $data = $response->json();
        expect($data['booking_type'])->toBe('day_tour');
        expect($data['booking_source'])->toBe('walkin');
        expect($data['check_in_date'])->toBe(now()->format('Y-m-d'));
        expect($data['check_out_date'])->toBe(now()->format('Y-m-d'));
        
        // Verify booking was created in database
        assertDatabaseHas('bookings', [
            'booking_type' => 'day_tour',
            'booking_source' => 'walkin',
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
        ]);
    });

    test('admin can create overnight walk-in booking with nights field', function () {
        $payload = [
            'booking_type' => 'overnight',
            'nights' => 2,
            'local_date' => now()->format('Y-m-d'),
            'rooms' => [
                [
                    'room_id' => $this->overnightRoom->slug,
                    'quantity' => 1,
                    'adults' => 2,
                    'children' => 0,
                ]
            ],
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Test request',
        ];

        $response = $this->withHeader('X-TEST-USER-ID', $this->adminUser->id)
            ->postJson('/api/v1/admin/bookings/walk-in', $payload);

        $response->assertStatus(201);
        
        $data = $response->json();
        expect($data['booking_type'])->toBe('overnight');
        expect($data['booking_source'])->toBe('walkin');
        expect($data['check_in_date'])->toBe(now()->format('Y-m-d'));
        expect($data['check_out_date'])->toBe(now()->addDays(2)->format('Y-m-d'));
        
        // Verify booking was created in database
        assertDatabaseHas('bookings', [
            'booking_type' => 'overnight',
            'booking_source' => 'walkin',
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
        ]);
    });

    test('day tour walk-in booking fails if nights field is provided with invalid value', function () {
        $payload = [
            'booking_type' => 'day_tour',
            'nights' => 0, // This should cause validation error
            'local_date' => now()->format('Y-m-d'),
            'rooms' => [
                [
                    'room_id' => $this->dayTourRoom->slug,
                    'quantity' => 1,
                    'adults' => 2,
                    'children' => 0,
                    'include_lunch' => false,
                    'include_pm_snack' => false,
                ]
            ],
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Test request',
        ];

        $response = $this->withHeader('X-TEST-USER-ID', $this->adminUser->id)
            ->postJson('/api/v1/admin/bookings/walk-in', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nights']);
    });

    test('overnight walk-in booking fails if nights field is missing', function () {
        $payload = [
            'booking_type' => 'overnight',
            // Missing nights field
            'local_date' => now()->format('Y-m-d'),
            'rooms' => [
                [
                    'room_id' => $this->overnightRoom->slug,
                    'quantity' => 1,
                    'adults' => 2,
                    'children' => 0,
                ]
            ],
            'guest_name' => 'Test Guest',
            'guest_email' => 'test@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Test request',
        ];

        $response = $this->withHeader('X-TEST-USER-ID', $this->adminUser->id)
            ->postJson('/api/v1/admin/bookings/walk-in', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nights']);
    });
});
