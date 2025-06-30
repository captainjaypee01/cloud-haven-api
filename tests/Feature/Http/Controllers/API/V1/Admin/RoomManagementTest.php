<?php

use App\Models\Room;
use App\Models\User;

use function Pest\Laravel\actingAs;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Admin Test Case for Room Management
 */
uses(RefreshDatabase::class);
describe('Admin Room Management', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->guest = User::factory()->guest()->create();
        $this->rooms = Room::factory(10)->create();
    });

    describe('Room List', function () {
        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->getJson('/api/v1/admin/rooms');
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $guest = User::factory()->create(['role' => 'guest']);

            $response = $this->withHeader('X-TEST-USER-ID', $guest->id)
                ->getJson('/api/v1/admin/rooms');
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('list rooms with pagination', function () {

            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson('/api/v1/admin/rooms?page=1&per_page=10')
                ->assertOk()
                ->assertJsonCount(10, 'data')
                ->assertJsonPath('meta.current_page', 1);
        });
    });

    describe('Show Room', function () {
        beforeEach(function () {
            $this->showRoomId = $this->rooms->random()->id;
            $maxId = Room::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->getJson("/api/v1/admin/rooms/{$this->showRoomId}");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->getJson("/api/v1/admin/rooms/{$this->showRoomId}");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('show room details structured data', function () {
            // Pick a random room from the existing ones
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson("/api/v1/admin/rooms/{$this->showRoomId}")
                ->assertOk()
                ->assertJsonStructure([
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'max_guests',
                    'extra_guest_fee',
                    'status',
                    'created_at',
                    'updated_at',
                ]);
        });

        it('return "Room Not Found" if Room is not existing', function () {
            // Get the highest possible ID
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson("/api/v1/admin/rooms/$this->nonExistentId")
                ->assertNotFound()
                ->assertJson(['error' => 'Room not found.']);
        });
    });
    
    describe('Store Room', function () {
        beforeEach(function () {

        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->post("/api/v1/admin/rooms/");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->post("/api/v1/admin/rooms");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('allows an admin to create a room', function () {
            // Define room data to create
            $data = [
                'name'                  => 'Conference Room A',
                'quantity'              => 2,
                'max_guests'            => 20,
                'extra_guest_fee'       => 2,
                'allows_day_use'        => false,
                'base_weekday_rate'     => 0,
                'base_weekend_rate'     => 0,
                'price_per_night'       => 10,
                'status'                => "archived",
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->postJson("/api/v1/admin/rooms", $data)
                ->assertCreated()
                ->assertJsonStructure([
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'max_guests',
                    'extra_guest_fee',
                    'price',
                    'status',
                    'created_at',
                    'updated_at',
                ]);
        });

        it('returns validation errors if fields are missing', function () {
            // Define room data to create
            $data = [
                'name' => 'Conference Room A',
                'max_guests' => 20,
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->postJson("/api/v1/admin/rooms", $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                 'quantity',
                 'extra_guest_fee',
                 'status',
                 'base_weekday_rate',
                 'base_weekend_rate',
                 'price_per_night'
             ]);
        });
    });
    
    describe('Update Room', function () {
        beforeEach(function () {
            $this->roomId = $this->rooms->random()->id;
            $maxId = Room::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->postJson("/api/v1/admin/rooms/");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->postJson("/api/v1/admin/rooms");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('allows an admin to update a room', function () {
            // Define room data to create
            $data = [
                'name'                  => 'Conference Room B',
                'quantity'              => 4,
                'max_guests'            => 10,
                'extra_guest_fee'       => 22,
                'allows_day_use'        => false,
                'base_weekday_rate'     => 10,
                'base_weekend_rate'     => 10,
                'price_per_night'       => 10,
                'status'                => "available",
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->putJson("/api/v1/admin/rooms/{$this->roomId}", $data)
                ->assertOk()
                ->assertJsonStructure([
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'max_guests',
                    'extra_guest_fee',
                    'status',
                    'created_at',
                    'updated_at',
                ]);
        });

        it('returns validation errors if fields are missing', function () {
            // Define room data to create
            $data = [
                'name' => 'Conference Room C',
                'max_guests' => 4,
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->putJson("/api/v1/admin/rooms/{$this->roomId}", $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                 'quantity',
                 'extra_guest_fee',
                 'status',
                 'base_weekday_rate',
                 'base_weekend_rate',
                 'price_per_night'
             ]);
        });

        it('return "Room Not Found" if Room is not existing', function () {
            // Get the highest possible ID
            $data = [
                'name'                  => 'Conference Room B',
                'quantity'              => 4,
                'max_guests'            => 10,
                'extra_guest_fee'       => 22,
                'allows_day_use'        => false,
                'base_weekday_rate'     => 10,
                'base_weekend_rate'     => 10,
                'price_per_night'       => 10,
                'status'                => "unavailable",
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->putJson("/api/v1/admin/rooms/{$this->nonExistentId}", $data)
                ->assertNotFound()
                ->assertJson(['error' => 'Room not found.']);
        });
    });

    describe('Delete Room', function () {
        beforeEach(function () {
            $this->roomId = $this->rooms->random()->id;
            $maxId = Room::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->delete("/api/v1/admin/rooms/{$this->roomId}");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->delete("/api/v1/admin/rooms/{$this->roomId}");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('allows an admin to remove a room', function () {
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->delete("/api/v1/admin/rooms/{$this->roomId}")
                ->assertNoContent();
        });

        it('return "Room Not Found" if Room is not existing', function () {
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->delete("/api/v1/admin/rooms/{$this->nonExistentId}")
                ->assertNotFound()
                ->assertJson(['error' => 'Room not found.']);
        });
    });
});
