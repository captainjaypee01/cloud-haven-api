<?php

use App\Exceptions\Amenities\AmenityInUseException;
use App\Models\Amenity;
use App\Models\Room;
use App\Services\Amenities\Actions\DeleteAmenityAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Deletion Test', function () {

    test('prevents deletion when amenity is in use', function () {
        // Arrange
        $amenity = Amenity::factory()->create();
        $room = Room::factory()->create();
        $room->amenities()->attach($amenity);

        // Act & Assert
        $this->expectException(AmenityInUseException::class);
        $this->expectExceptionMessageMatches('/used in \d+ rooms/');

        app()->make(DeleteAmenityAction::class)->handle($amenity);
    });

    test('deletes when not in use', function () {
        $amenity = Amenity::factory()->create();

        // Act & Assert
        app()->make(DeleteAmenityAction::class)->handle($amenity);

        $this->assertSoftDeleted($amenity);
    });
});
