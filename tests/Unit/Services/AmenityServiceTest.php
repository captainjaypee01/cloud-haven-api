<?php

use App\Contracts\Amenities\CreateAmenityContract;
use App\Contracts\Amenities\DeleteAmenityContract;
use App\Contracts\Amenities\UpdateAmenityContract;
use App\Contracts\Repositories\AmenityRepositoryInterface;
use App\DTO\Amenities\AmenityDtoFactory;
use App\DTO\Amenities\NewAmenity;
use App\DTO\Amenities\UpdateAmenity;
use App\Exceptions\Amenities\AmenityInUseException;
use App\Models\Amenity;
use App\Models\Room;
use App\Services\Amenities\AmenityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

describe("Amenity Service Test", function () {
    beforeEach(function () {
        $this->mockAmenityRepository = mock(AmenityRepositoryInterface::class);
        $this->dtoFactory = mock(AmenityDtoFactory::class);
        $this->creator = mock(CreateAmenityContract::class);
        $this->updater = mock(UpdateAmenityContract::class);
        $this->deleter = mock(DeleteAmenityContract::class);
        $this->amenityService = new AmenityService($this->mockAmenityRepository, $this->dtoFactory, $this->creator, $this->updater, $this->deleter);

        app()->instance(CreateAmenityContract::class, $this->creator);
        app()->instance(UpdateAmenityContract::class, $this->updater);
        app()->instance(DeleteAmenityContract::class, $this->deleter);
    });

    afterEach(function () {
        Mockery::close();
    });
    describe("Read Operation", function () {
        it("returns paginated amenities", function () {

            $paginator = mock(LengthAwarePaginator::class);
            $total = 10;
            $filters = ['page' => 1, 'per_page' => $total];
            $this->mockAmenityRepository->shouldReceive('get')
                ->with($filters, $filters['sort'] ?? null, $total)
                ->andReturn($paginator);

            $paginator->shouldReceive('total')->andReturn($total);

            $result = $this->amenityService->list($filters);
            expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
            expect($result)->toHaveProperties(['total']);
            expect($result->total())->toBe($total);
        });

        test('it returns paginated amenities using real LengthAwarePaginator Class', function () {
            // Create a real paginator instance
            $paginator = new LengthAwarePaginator(
                items: new Collection(),
                total: 100,
                perPage: 10,
                currentPage: 1
            );

            // Create mock repository
            $mockRepository = Mockery::mock(AmenityRepositoryInterface::class);
            $mockRepository->shouldReceive('get')
                ->withSomeOfArgs([], null, 10) // Use loose argument matching
                ->andReturn($paginator);

            // Create service with mocked repository
            $service = new \App\Services\Amenities\AmenityService($mockRepository, $this->dtoFactory, $this->creator, $this->updater, $this->deleter);

            // Call the service method
            $result = $service->list([]);

            // Assertions
            expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
            expect($result->total())->toBe(100);
        });
        test('it returns paginated amenities from database', function () {

            // Resolve repository and service from container
            $repository = app()->instance(AmenityRepositoryInterface::class, mock(AmenityRepositoryInterface::class));
            $service = new \App\Services\Amenities\AmenityService($repository, $this->dtoFactory, $this->creator, $this->updater, $this->deleter);
            $repository->shouldReceive('get')
                ->withSomeOfArgs([], null, 10);
            // Call service method
            $result = $service->list([]);

            // Assertions
            expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
            // expect($result->total())->toBe(100);
        });

        test('it returns paginated amenities with container', function () {
            $paginator = new LengthAwarePaginator([], 100, 10, 1);

            $this->mockAmenityRepository->shouldReceive('get')->andReturn($paginator);
            // Bind to container
            app()->instance(AmenityRepositoryInterface::class, $this->mockAmenityRepository);

            // Resolve service from container
            $service = app(AmenityService::class);

            $result = $service->list([]);

            expect($result->total())->toBe(100);
        });

        test('it returns empty pagination when no amenities exist', function () {
            // Create an empty paginator
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                items: collect(),
                total: 0,
                perPage: 10,
                currentPage: 1
            );

            // Create a mock repository
            $this->mockAmenityRepository->shouldReceive('get')
                ->withSomeOfArgs([], null, 10)
                ->andReturn($paginator);

            // Create service with mocked repository
            $service = $this->amenityService;

            $result = $service->list([]);

            // Assertions
            expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            expect($result->total())->toBe(0);
            expect($result->count())->toBe(0);
            expect($result->items())->toBeEmpty();
        });

        test('pagination metadata is correct when empty', function () {
            // Create an empty paginator
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                items: collect(),
                total: 0,
                perPage: 10,
                currentPage: 1
            );

            // Create a mock repository
            $this->mockAmenityRepository->shouldReceive('get')
                ->withSomeOfArgs([], null, 10)
                ->andReturn($paginator);

            // Create service with mocked repository
            $service = $this->amenityService;

            $result = $service->list([]);

            expect($result->currentPage())->toBe(1);
            expect($result->lastPage())->toBe(1);
            expect($result->hasMorePages())->toBeFalse();
            expect($result->firstItem())->toBeNull();
            expect($result->lastItem())->toBeNull();
        });

        test('it returns empty pagination when no amenities exist in database', function () {
            // Get service from container
            $service = app(\App\Services\Amenities\AmenityService::class);

            // Call service method
            $result = $service->list([]);

            // Assertions
            expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            expect($result->count())->toBe(0);
            expect($result->items())->toBeEmpty();
        });


        test('show returns amenity by id', function () {
            $room = Amenity::factory()->make(['id' => 5]);

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with(5)
                ->once()
                ->andReturn($room);

            $result = $this->amenityService->show(5);

            expect($result)
                ->toBeInstanceOf(Amenity::class)
                ->id->toBe(5);
        });

        it('throws user not found error if id doesn\'t exist', function () {
            $room = Amenity::factory()->make(['id' => 5]);
            $roomIdNotExist = 105;
            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($roomIdNotExist)
                ->once()
                ->andThrow(new ModelNotFoundException('Amenity not found.'));

            $result = $this->amenityService->show($roomIdNotExist);

            expect($result)
                ->toBeInstanceOf(Amenity::class)
                ->id->toBe(5);
        })->throws(ModelNotFoundException::class, 'Amenity not found');;
    });

    describe("Create Operation", function () {
        it('creates amenity', function () {
            // Act
            $data = [
                'name'          => fake()->word(),
                'description'   => fake()->paragraph(),
                'icon'          => null,
                'price'         => null,
            ];

            $dto = new NewAmenity(...$data);
            $this->dtoFactory->shouldReceive('newAmenity')
                ->with($data)
                ->once()
                ->andReturn($dto);

            $this->creator->shouldReceive('handle')
                ->with($dto)
                ->once()
                ->andReturn(new Amenity);

            // Act
            $result = $this->amenityService->create($data);

            // Assert
            expect($result)
                ->toBeInstanceOf(Amenity::class);
        });
    });

    describe("Update Operation", function () {
        it('updates amenity', function () {
            // Arrange

            $id = 10;
            $originalAmenity = Amenity::factory()->make(['id' => $id]);
            $data = [
                'name'          => fake()->word(),
                'description'   => "test",
                'icon'          => null,
                'price'         => null,
            ];

            // 2. Create UPDATED amenity
            $updatedAmenity = clone $originalAmenity;
            $updatedAmenity->name = $data['name'];
            $updatedAmenity->description = $data['description'];

            $dto = new UpdateAmenity(...$data);

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($id)
                ->once()
                ->andReturn($originalAmenity);

            $this->dtoFactory->shouldReceive('updateAmenity')
                ->with($data)
                ->once()
                ->andReturn($dto);

            $this->updater->shouldReceive('handle')
                ->with($originalAmenity, $dto)
                ->once()
                ->andReturn($updatedAmenity);

            // Act
            $result = $this->amenityService->update($id, $data);

            // Assert
            expect($result)->toBeInstanceOf(Amenity::class);
            expect($result)->toBe($updatedAmenity)
                ->and($result->description)->toBe($data['description'])
                ->and($result->name)->toBe($data['name']);
        });

        it('shows amenity not found when updating', function () {
            // Arrange
            $id = 10;
            $data = [
                'name'          => fake()->word(),
            ];

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($id)
                ->once()
                ->andThrow(ModelNotFoundException::class);

            // Act
            $this->amenityService->update($id, $data);

            // Assert
        })->throws(ModelNotFoundException::class);
    });

    describe('Delete Operation', function () {

        it('deletes an existing amenity', function () {
            // Arrange
            $id = 10;
            $amenity = Amenity::factory()->make(['id' => $id]);

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($id)
                ->once()
                ->andReturn($amenity);

            $this->deleter->shouldReceive('handle')
                ->with($amenity)
                ->once();

            // Act
            $this->amenityService->delete($id);

            // Assert
            // If we reach here without exceptions, deletion was initiated
            expect(true)->toBeTrue();
        });

        // Alternative: Test that deleter was called
        it('calls deleter with amenity', function () {
            $id = 10;
            $amenity = Amenity::factory()->make(['id' => $id]);

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($id)
                ->andReturn($amenity);

            $this->deleter->shouldReceive('handle')
                ->with($amenity)
                ->once();

            $this->amenityService->delete($id);
        });

        it('shows amenity not found when deleting', function () {
            // Arrange
            $id = 10;

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($id)
                ->once()
                ->andThrow(ModelNotFoundException::class);

            // Act
            $this->amenityService->delete($id);

            // Assert
        })->throws(ModelNotFoundException::class);

        it('propagates AmenityInUseException during deletion', function () {
            $id = 10;
            $amenity = Amenity::factory()->make(['id' => $id]);

            $this->mockAmenityRepository->shouldReceive('getId')
                ->with($id)
                ->once()
                ->andReturn($amenity);

            $this->deleter->shouldReceive('handle')
                ->with($amenity)
                ->once()
                ->andThrow(new AmenityInUseException('Amenity is currently used'));

            $this->amenityService->delete($id);
        })->throws(AmenityInUseException::class, 'Amenity is currently used');
        
    });
});
