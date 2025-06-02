<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Admin Test Case for User Management
 */
uses(RefreshDatabase::class);
describe('Admin User Management', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->guest = User::factory()->guest()->create();
        $this->users = User::factory(10)->create();
    });

    describe('User List', function () {
        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->getJson('/api/v1/admin/users');
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $guest = User::factory()->create(['role' => 'guest']);

            $response = $this->withHeader('X-TEST-USER-ID', $guest->id)
                ->getJson('/api/v1/admin/users');
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('list users with pagination', function () {

            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson('/api/v1/admin/users?page=1&per_page=10')
                ->assertOk()
                ->assertJsonCount(10, 'data')
                ->assertJsonPath('meta.current_page', 1);
        });
    });

    describe('Show User', function () {
        beforeEach(function () {
            $this->showUserId = $this->users->random()->id;
            $maxId = User::max('id') ?? 0;
            $this->nonExistentId = $maxId + 100;
        });

        it('denies access when no X-TEST-USER-ID header is set', function () {
            // Hit the protected admin route without any header:
            $response = $this->getJson("/api/v1/admin/users/{$this->showUserId}");
            $response->assertStatus(401)
                ->assertJson(['error' => 'Unauthorized']);
        });

        it('denies access for a logged-in user with role=guest', function () {

            $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
                ->getJson("/api/v1/admin/users/{$this->showUserId}");
            $response->assertJson(['error' => 'Forbidden'])
                ->assertStatus(403);
        });

        it('show user details structured data', function () {
            // Pick a random user from the existing ones
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson("/api/v1/admin/users/{$this->showUserId}")
                ->assertOk()
                ->assertJsonStructure([
                    'id',
                    'clerk_id',
                    'email',
                    'first_name',
                    'last_name',
                    'country_code',
                    'contact_number',
                    'image',
                    'created_at',
                    'updated_at',
                    'providers',
                ]);
        });

        it('return "User Not Found" if User is not existing', function () {
            // Get the highest possible ID
            actingAs($this->admin)
                ->withHeader('X-TEST-USER-ID', $this->admin->id)
                ->getJson("/api/v1/admin/users/$this->nonExistentId")
                ->assertNotFound()
                ->assertJson(['error' => 'User not found.']);
        });
    });
    
    // describe('Store User', function () {
    //     beforeEach(function () {

    //     });

    //     it('denies access when no X-TEST-USER-ID header is set', function () {
    //         // Hit the protected admin route without any header:
    //         $response = $this->post("/api/v1/admin/users/");
    //         $response->assertStatus(401)
    //             ->assertJson(['error' => 'Unauthorized']);
    //     });

    //     it('denies access for a logged-in user with role=guest', function () {

    //         $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
    //             ->post("/api/v1/admin/users");
    //         $response->assertJson(['error' => 'Forbidden'])
    //             ->assertStatus(403);
    //     });

    //     it('allows an admin to create a user', function () {
    //         // Define user data to create
    //         $data = [
    //             'name'                  => 'Conference User A',
    //             'quantity'              => 2,
    //             'max_guests'            => 20,
    //             'extra_guest_fee'       => 2,
    //             'allows_day_use'        => false,
    //             'base_weekday_rate'     => 0,
    //             'base_weekend_rate'     => 0,
    //             'status'                => "archived",
    //         ];
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->postJson("/api/v1/admin/users", $data)
    //             ->assertCreated()
    //             ->assertJsonStructure([
    //                 'id',
    //                 'name',
    //                 'description',
    //                 'quantity',
    //                 'max_guests',
    //                 'extra_guest_fee',
    //                 'status',
    //                 'created_at',
    //                 'updated_at',
    //             ]);
    //     });

    //     it('returns validation errors if fields are missing', function () {
    //         // Define user data to create
    //         $data = [
    //             'name' => 'Conference User A',
    //             'max_guests' => 20,
    //         ];
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->postJson("/api/v1/admin/users", $data)
    //             ->assertUnprocessable()
    //             ->assertJsonValidationErrors([
    //              'quantity',
    //              'extra_guest_fee',
    //              'status',
    //              'base_weekday_rate',
    //              'base_weekend_rate',
    //          ]);
    //     });
    // });
    
    // describe('Update User', function () {
    //     beforeEach(function () {
    //         $this->userId = $this->users->random()->id;
    //         $maxId = User::max('id') ?? 0;
    //         $this->nonExistentId = $maxId + 100;
    //     });

    //     it('denies access when no X-TEST-USER-ID header is set', function () {
    //         // Hit the protected admin route without any header:
    //         $response = $this->postJson("/api/v1/admin/users/");
    //         $response->assertStatus(401)
    //             ->assertJson(['error' => 'Unauthorized']);
    //     });

    //     it('denies access for a logged-in user with role=guest', function () {

    //         $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
    //             ->postJson("/api/v1/admin/users");
    //         $response->assertJson(['error' => 'Forbidden'])
    //             ->assertStatus(403);
    //     });

    //     it('allows an admin to update a user', function () {
    //         // Define user data to create
    //         $data = [
    //             'name'                  => 'Conference User B',
    //             'quantity'              => 4,
    //             'max_guests'            => 10,
    //             'extra_guest_fee'       => 22,
    //             'allows_day_use'        => false,
    //             'base_weekday_rate'     => 10,
    //             'base_weekend_rate'     => 10,
    //             'status'                => "available",
    //         ];
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->putJson("/api/v1/admin/users/{$this->userId}", $data)
    //             ->assertOk()
    //             ->assertJsonStructure([
    //                 'id',
    //                 'name',
    //                 'description',
    //                 'quantity',
    //                 'max_guests',
    //                 'extra_guest_fee',
    //                 'status',
    //                 'created_at',
    //                 'updated_at',
    //             ]);
    //     });

    //     it('returns validation errors if fields are missing', function () {
    //         // Define user data to create
    //         $data = [
    //             'name' => 'Conference User C',
    //             'max_guests' => 4,
    //         ];
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->putJson("/api/v1/admin/users/{$this->userId}", $data)
    //             ->assertUnprocessable()
    //             ->assertJsonValidationErrors([
    //              'quantity',
    //              'extra_guest_fee',
    //              'status',
    //              'base_weekday_rate',
    //              'base_weekend_rate',
    //          ]);
    //     });

    //     it('return "User Not Found" if User is not existing', function () {
    //         // Get the highest possible ID
    //         $data = [
    //             'name'                  => 'Conference User B',
    //             'quantity'              => 4,
    //             'max_guests'            => 10,
    //             'extra_guest_fee'       => 22,
    //             'allows_day_use'        => false,
    //             'base_weekday_rate'     => 10,
    //             'base_weekend_rate'     => 10,
    //             'status'                => "unavailable",
    //         ];
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->putJson("/api/v1/admin/users/{$this->nonExistentId}", $data)
    //             ->assertNotFound()
    //             ->assertJson(['error' => 'User not found.']);
    //     });
    // });

    // describe('Delete User', function () {
    //     beforeEach(function () {
    //         $this->userId = $this->users->random()->id;
    //         $maxId = User::max('id') ?? 0;
    //         $this->nonExistentId = $maxId + 100;
    //     });

    //     it('denies access when no X-TEST-USER-ID header is set', function () {
    //         // Hit the protected admin route without any header:
    //         $response = $this->delete("/api/v1/admin/users/{$this->userId}");
    //         $response->assertStatus(401)
    //             ->assertJson(['error' => 'Unauthorized']);
    //     });

    //     it('denies access for a logged-in user with role=guest', function () {

    //         $response = $this->withHeader('X-TEST-USER-ID', $this->guest->id)
    //             ->delete("/api/v1/admin/users/{$this->userId}");
    //         $response->assertJson(['error' => 'Forbidden'])
    //             ->assertStatus(403);
    //     });

    //     it('allows an admin to remove a user', function () {
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->delete("/api/v1/admin/users/{$this->userId}")
    //             ->assertNoContent();
    //     });

    //     it('return "User Not Found" if User is not existing', function () {
    //         actingAs($this->admin)
    //             ->withHeader('X-TEST-USER-ID', $this->admin->id)
    //             ->delete("/api/v1/admin/users/{$this->nonExistentId}")
    //             ->assertNotFound()
    //             ->assertJson(['error' => 'User not found.']);
    //     });
    // });
});
