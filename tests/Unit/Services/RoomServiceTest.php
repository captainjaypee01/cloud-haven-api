<?php

namespace Tests\Unit\Services;

use Mockery;
use App\Models\Room;
use App\Services\RoomService;
use Tests\TestCase;
use App\Queries\RoomQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Contracts\Room\CreateRoomContract;
use App\Contracts\Room\UpdateRoomContract;
use App\Contracts\Room\DeleteRoomContract;
use App\Contracts\Room\UpdateStatusContract;
use App\DTO\Rooms\NewRoom;
use App\DTO\Rooms\RoomDtoFactory;
use App\DTO\Rooms\UpdateRoom;

describe('Room Service Test', function () {

    beforeEach(function () {
        // Create mock dependencies
        $this->query = Mockery::mock(RoomQuery::class);
        $this->creator = Mockery::mock(CreateRoomContract::class);
        $this->updater = Mockery::mock(UpdateRoomContract::class);
        $this->deleter = Mockery::mock(DeleteRoomContract::class);
        $this->statusUpdater = Mockery::mock(UpdateStatusContract::class);
        $this->dtoFactory = Mockery::mock(RoomDtoFactory::class);

        // Instantiate service with mocked dependencies
        $this->service = new RoomService(
            $this->query,
            $this->creator,
            $this->updater,
            $this->deleter,
            $this->statusUpdater,
            $this->dtoFactory,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    test('list returns paginated rooms', function () {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $filters = ['status' => 'available', 'per_page' => 15, 'page' => 1];

        $this->query->shouldReceive('get')
            ->with($filters, $filters['sort'] ?? null, 15)
            ->once()
            ->andReturn($paginator);

        $result = $this->service->list($filters);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });


    test('show returns room by id', function () {
        $room = Room::factory()->make(['id' => 5]);

        $this->query->shouldReceive('getId')
            ->with(5)
            ->once()
            ->andReturn($room);

        $result = $this->service->show(5);

        expect($result)
            ->toBeInstanceOf(Room::class)
            ->id->toBe(5);
    });

    test('create room handles request and user', function () {
        $room = Room::factory()->make(['id' => 3]);
        $userId = 100;
        $data = [
            'name'                  => 'Conference Room A',
            'description'           => '',
            'quantity'              => 2,
            'max_guests'            => 20,
            'extra_guest_fee'       => 2,
            'allows_day_use'        => false,
            'base_weekday_rate'     => 0,
            'base_weekend_rate'     => 0,
            'status'                => "archived",
        ];

        // Mock DTO creation
        $dto = new NewRoom(...$data);
        $this->dtoFactory->shouldReceive('newRoom')
            ->with($data)
            ->once()
            ->andReturn($dto);
        $this->creator->shouldReceive('handle')
            ->with($dto, $userId)
            ->once()
            ->andReturn($room);

        $result = $this->service->create($data, $userId);

        expect($result)->toBeInstanceOf(Room::class);
    });

    test('update room handles request and user', function () {
        $room = Room::factory()->make(['id' => 7]);
        $userId = 200;
        $data = [
            'name'                  => 'Conference Room B',
            'description'           => '',
            'quantity'              => 4,
            'max_guests'            => 10,
            'extra_guest_fee'       => 22,
            'allows_day_use'        => false,
            'base_weekday_rate'     => 10,
            'base_weekend_rate'     => 10,
            'status'                => "available",
        ];

        $this->query->shouldReceive('getId')
            ->with(7)
            ->once()
            ->andReturn($room);

        // Mock DTO creation
        $dto = new UpdateRoom(...$data);
        $this->dtoFactory->shouldReceive('updateRoom')
            ->with($data)
            ->once()
            ->andReturn($dto);

        $this->updater->shouldReceive('handle')
            ->with($room, $dto, $userId)
            ->once()
            ->andReturn($room);

        $result = $this->service->update($data, 7, $userId);

        expect($result)->toBeInstanceOf(Room::class);
    });
    test('delete room handles room and user', function () {
        $room = Room::factory()->make(['id' => 8]);
        $userId = 300;

        $this->query->shouldReceive('getId')
            ->with(8)
            ->once()
            ->andReturn($room);

        $this->deleter->shouldReceive('handle')
            ->with($room, $userId)
            ->once();

        $this->service->delete(8, $userId);
    });

    test('update status handles room and user', function () {
        $room = Room::factory()->make(['id' => 9]);
        $userId = 400;
        $newStatus = 'archived';

        $this->query->shouldReceive('getId')
            ->with(9)
            ->once()
            ->andReturn($room);

        $this->statusUpdater->shouldReceive('handle')
            ->with($room, $newStatus, $userId)
            ->once()
            ->andReturn($room);

        $result = $this->service->updateStatus(9, $newStatus, $userId);

        expect($result)->toBeInstanceOf(Room::class);
    });
    
    test('create propagates exceptions', function () {
        $data = ['name' => 'Test Room'];
        $userId = 100;

        // Mock DTO creation
        $dto = new NewRoom(
            'Test Room',
            null,
            1,
            2,
            10.0,
            false,
            'available',
            100.0,
            150.0
        );

        $this->dtoFactory->shouldReceive('newRoom')
            ->with($data)
            ->once()
            ->andReturn($dto);

        $this->creator->shouldReceive('handle')
            ->with($dto, $userId)
            ->once()
            ->andThrow(new \Exception('Test error'));

        $this->service->create($data, $userId);
    })->throws(\Exception::class, 'Test error');
});

// Similar patterns for other methods