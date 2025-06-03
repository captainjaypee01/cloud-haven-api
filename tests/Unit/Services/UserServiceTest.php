<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use Mockery;
use App\Models\User;
use App\Services\UserService;
use Tests\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Contracts\Users\CreateUserContract;
use App\Contracts\Users\UpdateUserContract;
use App\Contracts\Users\DeleteUserContract;
use App\Contracts\Users\SyncLinkedProvidersContract;
use App\DTO\Users\NewUser;
use App\DTO\Users\SyncProviders;
use App\DTO\Users\UpdateUser;
use App\DTO\Users\UserDtoFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;

describe('User Service Test', function () {

    beforeEach(function () {
        // Create mock dependencies
        $this->repository = Mockery::mock(UserRepositoryInterface::class);
        $this->creator = Mockery::mock(CreateUserContract::class);
        $this->updater = Mockery::mock(UpdateUserContract::class);
        $this->deleter = Mockery::mock(DeleteUserContract::class);
        $this->syncProviders = Mockery::mock(SyncLinkedProvidersContract::class);
        $this->dtoFactory = Mockery::mock(UserDtoFactory::class);

        // Instantiate service with mocked dependencies
        $this->service = new UserService(
            $this->repository,
            $this->creator,
            $this->updater,
            $this->deleter,
            $this->syncProviders,
            $this->dtoFactory,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    test('list returns paginated users', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $filters = ['per_page' => 15, 'page' => 1];

        $this->repository->shouldReceive('get')
            ->with($filters, $filters['sort'] ?? null, 15)
            ->once()
            ->andReturn($paginator);

        $result = $this->service->list($filters);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });


    test('show returns user by id', function () {
        $user = new User();
        $user->id = 10;

        $this->repository->shouldReceive('getById')
            ->with(10)
            ->once()
            ->andReturn($user);

        $result = $this->service->show(10);

        expect($result)
            ->toBeInstanceOf(User::class)
            ->id->toBe(10);
    });

    it('return user not found if user doesn\'t exist when fetching', function () {
        $user = new User();
        $user->id = 10;

        $this->repository->shouldReceive('getById')
            ->with(5)
            ->once()
            ->andThrow(new \Exception('User not found'));
        // ->andReturn($user);

        $result = $this->service->show(5);

        expect($result)
            ->toBeInstanceOf(User::class)
            ->id->toBe(10);
    })->throws(\Exception::class, 'User not found');

    test('createUserByClerk - user by Clerk webhook with default role of user', function () {
        $user = new User();
        $data = [
            'clerk_id'              => 'user_2xsCuoAOUwJ8CNLPlfihRtrisai',
            'email'                 => 'user@cloudhaven.com',
            'first_name'            => 'Cloud Haven',
            'last_name'             => 'Resort',
            'role'                  => 'user',
            'country_code'          => '+63',
            'contact_number'        => '9124576322',
            'image_url'             => '',
            'password'              => '',
            'email_verified_at'     => \Carbon\CarbonImmutable::createFromTimestampUTC(1748717615210), //'2025-05-31 18:53:35',
            'linkedProviders'       => [
                [
                    'id'        => 'idn_2xsCuJZLzsfsmIwC7vELUzNbuKU',
                    'type'      => 'oauth_google',
                ],
            ],
        ];

        // Mock DTO creation
        $dto = new NewUser(...$data);

        $this->dtoFactory->shouldReceive('newUser')
            ->with($data)
            ->once()
            ->andReturn($dto);

        $this->creator->shouldReceive('handle')
            ->with($dto)
            ->once()
            ->andReturn($user);

        $this->dtoFactory->shouldReceive('syncProviders')
            ->with($dto->linkedProviders)
            ->once()
            ->andReturn(new SyncProviders($dto->linkedProviders));

        $this->syncProviders->shouldReceive('handle')
            ->with($user, Mockery::type(SyncProviders::class))
            ->once();

        $result = $this->service->createUserByClerk($data);

        expect($result)->toBeInstanceOf(User::class);
    });
    
    test('createUser - user by Admin dashboard with default role of user', function () {
        $user = new User();
        $data = [
            'clerk_id'              => 'user_2xsCuoAOUwJ8CNLPlfihRtrisai',
            'email'                 => 'user@cloudhaven.com',
            'first_name'            => 'Cloud Haven',
            'last_name'             => 'Resort',
            'role'                  => 'user',
            'country_code'          => '+63',
            'contact_number'        => '9124576322',
            'image_url'             => '',
            'password'              => '',
            'email_verified_at'     => null, //'2025-05-31 18:53:35',
            'linkedProviders'       => [],
        ];

        // Mock DTO creation
        $dto = new NewUser(...$data);

        $this->dtoFactory->shouldReceive('newUser')
            ->with($data)
            ->once()
            ->andReturn($dto);

        $this->creator->shouldReceive('handle')
            ->with($dto)
            ->once()
            ->andReturn($user);

        $this->dtoFactory->shouldReceive('syncProviders')
            ->with($dto->linkedProviders)
            ->once()
            ->andReturn(new SyncProviders($dto->linkedProviders));

        $this->syncProviders->shouldReceive('handle')
            ->with($user, Mockery::type(SyncProviders::class))
            ->once();

        $result = $this->service->createUser($data);

        expect($result)->toBeInstanceOf(User::class);
    });

    test('updateByClerkId updates existing user and syncs providers', function () {
        $clerkId = 'user_123';
        $data = [
            'id' => $clerkId,
            'email' => 'updated@example.com',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'role' => 'user',
            'email_addresses' => [[
                'linked_to' => [
                    ['id' => 'provider_1', 'type' => 'google']
                ]
            ]]
        ];

        $existingUser = Mockery::mock(User::class);

        // Create a real DTO instance with all required properties
        $updateDto = new UpdateUser(
            clerk_id: $clerkId,
            email: 'updated@example.com',
            first_name: 'Updated',
            last_name: 'Name',
            role: 'user',
            password: null,
            country_code: null,
            contact_number: null,
            image_url: null,
            email_verified_at: null,
            linkedProviders: [['id' => 'provider_1', 'type' => 'google']]
        );

        $this->repository->shouldReceive('findByClerkId')
            ->with($clerkId)
            ->once()
            ->andReturn($existingUser);

        $this->dtoFactory->shouldReceive('updateUser')
            ->with($data)
            ->once()
            ->andReturn($updateDto);

        $this->updater->shouldReceive('handle')
            ->with($existingUser, $updateDto)
            ->once()
            ->andReturn($existingUser);

        $this->dtoFactory->shouldReceive('syncProviders')
            ->with($updateDto->linkedProviders)
            ->once()
            ->andReturn(new SyncProviders($updateDto->linkedProviders));

        $this->syncProviders->shouldReceive('handle')
            ->with($existingUser, Mockery::type(SyncProviders::class))
            ->once();

        $result = $this->service->updateByClerkId($clerkId, $data);
        expect($result)->toBe($existingUser);
    });
    test('updateByUserId updates existing user in Admin Dashboard', function () {
        $userId = 123;
        $clerkId = 'user_123';
        $data = [
            'id' => $clerkId,
            'email' => 'updated@example.com',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'role' => 'user',
            'email_addresses' => [[
                'linked_to' => [
                    ['id' => 'provider_1', 'type' => 'google']
                ]
            ]]
        ];

        $existingUser = Mockery::mock(User::class);

        // Create a real DTO instance with all required properties
        $updateDto = new UpdateUser(
            clerk_id: $clerkId,
            email: 'updated@example.com',
            first_name: 'Updated',
            last_name: 'Name',
            role: 'user',
            password: null,
            country_code: null,
            contact_number: null,
            image_url: null,
            email_verified_at: null,
            linkedProviders: [['id' => 'provider_1', 'type' => 'google']]
        );

        $this->repository->shouldReceive('getById')
            ->with($userId)
            ->once()
            ->andReturn($existingUser);

        $this->dtoFactory->shouldReceive('updateUser')
            ->with($data)
            ->once()
            ->andReturn($updateDto);

        $this->updater->shouldReceive('handle')
            ->with($existingUser, $updateDto)
            ->once()
            ->andReturn($existingUser);

        $result = $this->service->updateById($userId, $data);
        expect($result)->toBe($existingUser);
    });

    test('updateByClerkId update user when not found and syncs providers', function () {
        $clerkId = 'user_456';
        $data = [
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'role' => 'user'
        ];

        $this->repository->shouldReceive('findByClerkId')
            ->with($clerkId)
            ->once()
            ->andThrow(ModelNotFoundException::class);

        $this->expectException(ModelNotFoundException::class);
        $this->service->updateByClerkId($clerkId, $data);
    });

    test('updateByUserId update user when not found in Admin Dashboard', function () {
        $userId = 456;
        $clerkId = 'user_456';
        $data = [
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'role' => 'user'
        ];

        $this->repository->shouldReceive('getById')
            ->with($userId)
            ->once()
            ->andThrow(ModelNotFoundException::class);

        $this->expectException(ModelNotFoundException::class);
        $this->service->updateById($userId, $data);
    });

    test('deleteByClerkId deletes existing user', function () {
        $clerkId = 'user_123';
        $user = Mockery::mock(User::class);

        $this->repository->shouldReceive('findByClerkId')
            ->with($clerkId)
            ->once()
            ->andReturn($user);

        $this->deleter->shouldReceive('handle')
            ->with($user)
            ->once();

        $this->service->deleteByClerkId($clerkId);
    });

    test('deleteByClerkId throws when user not found', function () {
        $clerkId = 'user_456';

        $this->repository->shouldReceive('findByClerkId')
            ->with($clerkId)
            ->once()
            ->andThrow(ModelNotFoundException::class);

        $this->expectException(ModelNotFoundException::class);
        $this->service->deleteByClerkId($clerkId);
    });

    // test('create propagates exceptions', function () {
    //     $data = ['name' => 'Test User'];
    //     $userId = 100;

    //     // Mock DTO creation
    //     $dto = new NewUser(
    //         'Test User',
    //         null,
    //         1,
    //         2,
    //         10.0,
    //         false,
    //         'available',
    //         100.0,
    //         150.0
    //     );

    //     $this->dtoFactory->shouldReceive('newUser')
    //         ->with($data)
    //         ->once()
    //         ->andReturn($dto);

    //     $this->creator->shouldReceive('handle')
    //         ->with($dto, $userId)
    //         ->once()
    //         ->andThrow(new \Exception('Test error'));

    //     $this->service->create($data, $userId);
    // })->throws(\Exception::class, 'Test error');
});
