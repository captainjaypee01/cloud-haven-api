<?php

use App\Models\Amenity;
use App\Models\User;

use function Pest\Laravel\actingAs;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Admin Test Case for Amenity Management
 */
uses(RefreshDatabase::class);
describe('Admin Amenity Management', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->guest = User::factory()->guest()->create();
        $this->amenities = Amenity::factory(10)->create();
        $this->existingAmenity = Amenity::factory()->create([
            'name' => 'Existing Amenity'
        ]);
    });

    describe('Amenity List', function () {
        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->getJson('/api/v1/admin/amenities');
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $guest = User::factory()->create(['role' => 'guest']);

            $response = $this->withHeader('X-TEST-USER-ID', $guest->id)
                ->getJson('/api/v1/admin/amenities');
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('list amenities with pagination', function () {

            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson('/api/v1/admin/amenities?page=1&per_page=10')
                ->assertOk()
                ->assertJsonCount(10, 'data')
                ->assertJsonPath('meta.current_page', 1);
        });
    })->group('read-operation');

    describe('Show Amenity', function () {
        beforeEach(function () {
            $this->showAmenityId = $this->amenities->random()->id;
            $maxId = Amenity::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->getJson("/api/v1/admin/amenities/{$this->showAmenityId}");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->getJson("/api/v1/admin/amenities/{$this->showAmenityId}");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('show amenity details structured data', function () {
            // Pick a random amenity from the existing ones
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson("/api/v1/admin/amenities/{$this->showAmenityId}")
                ->assertOk()
                ->assertJsonStructure([
                    'name',
                    'description',
                    'icon',
                    'price',
                    'created_at',
                    'updated_at',
                ]);
        });

        it('return "Amenity Not Found" if Amenity is not existing', function () {
            // Get the highest possible ID
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson("/api/v1/admin/amenities/$this->nonExistentId")
                ->assertNotFound()
                ->assertJson(['error' => 'Amenity not found.']);
        });
    })->group('read-operation');

    describe('Store Amenity', function () {
        beforeEach(function () {});

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->post("/api/v1/admin/amenities/");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->post("/api/v1/admin/amenities");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('allows an admin to create a amenity', function () {
            // Arrange
            $data = [
                'name'                  => fake()->word(),
                'description'           => fake()->text(),
                'icon'                  => null,
                'price'                 => array_rand([null, 10, 100, 500, 1000, 0]),
            ];

            // Act & Assert
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->postJson("/api/v1/admin/amenities", $data)
                ->assertCreated()
                ->assertJsonStructure([
                    'name',
                    'description',
                    'icon',
                    'price',
                    'created_at',
                    'updated_at',
                ]);
        });

        it('create amenity with duplicate name fails', function () {
            // Arrange
            $data = [
                'name'                  => 'Existing Amenity', // Same as existing
                'description'           => 'Test description'
            ];
            $response = $this->actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->postJson('/api/v1/admin/amenities', $data);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['name']);
        });

        it('create amenity validation error shows proper message', function () {
            // Arrange
            $data = [
                'name'                  => 'Existing Amenity', // Same as existing
                'description'           => 'Test description'
            ];
            $response = $this->actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->postJson('/api/v1/admin/amenities', $data);

            $response->assertJsonPath('errors.name.0', 'The name has already been taken.');
        });

        it('returns validation errors if fields are missing', function () {
            // Define amenity data to create
            $data = [];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->postJson("/api/v1/admin/amenities", $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'name',
                ]);
        });
    })->group('create-operation');

    describe('Update Amenity', function () {
        beforeEach(function () {
            $this->amenityId = $this->amenities->random()->id;
            $maxId = Amenity::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->putJson("/api/v1/admin/amenities/{$this->amenityId}");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->putJson("/api/v1/admin/amenities/{$this->amenityId}");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('allows an admin to update a amenity', function () {
            // Define amenity data to update
            $data = [
                'name'                  => fake()->word(),
                'description'           => fake()->text(),
                'icon'                  => null,
                'price'                 => array_rand([null, 10, 100, 500, 1000, 0]),
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->putJson("/api/v1/admin/amenities/{$this->amenities->random()->id}", $data)
                ->assertOk()
                ->assertJsonStructure([
                    'name',
                    'description',
                    'icon',
                    'price',
                    'created_at',
                    'updated_at',
                ]);
        });

        it('returns validation errors if fields are missing', function () {
            // Define amenity data to create
            $data = [];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->putJson("/api/v1/admin/amenities/{$this->amenityId}", $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'name',
                ]);
        });

        it('return "Amenity Not Found" if Amenity is not existing', function () {
            // Get the highest possible ID
            $data = [
                'name'                  => fake()->word(),
                'description'           => fake()->text(),
                'icon'                  => null,
            ];
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->putJson("/api/v1/admin/amenities/{$this->nonExistentId}", $data)
                ->assertNotFound()
                ->assertJson(['error' => 'Amenity not found.']);
        });
    })->group('update-operation');

    describe('Delete Amenity', function () {
        beforeEach(function () {
            $this->amenityId = $this->amenities->random()->id;
            $maxId = Amenity::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->delete("/api/v1/admin/amenities/{$this->amenityId}");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->delete("/api/v1/admin/amenities/{$this->amenityId}");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('allows an admin to remove a amenity', function () {
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->delete("/api/v1/admin/amenities/{$this->amenityId}")
                ->assertNoContent();
        });

        it('return "Amenity Not Found" if Amenity is not existing', function () {
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->delete("/api/v1/admin/amenities/{$this->nonExistentId}")
                ->assertNotFound()
                ->assertJson(['error' => 'Amenity not found.']);
        });
    })->group('delete-operation');
});
