<?php

use App\Models\Room;

describe('Public API - Room Management', function () {
    beforeEach(function () {
        $this->rooms = Room::factory(10)->create();
        $this->roomSlug = $this->rooms->random()->slug;
        $this->roomSlugNotExistent = "123321qwe";
    });
    it('get room list', function () {


        $this->getJson('/api/v1/rooms?page=1&per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1);
    });

    it('get room detail', function () {

        $this->getJson("/api/v1/rooms/{$this->roomSlug}")
            ->assertOk()
            ->assertJsonStructure([
                'slug',
                'name',
                'short_description',
                'long_description',
                'guests',
                'price',
                'amenities',
            ]);
    });

    it('return "Room Not Found" if Room is not existing', function () {
        // Get the wrong slug
        $this->getJson("/api/v1/rooms/{$this->roomSlugNotExistent}")
            ->assertNotFound()
            ->assertJson(['error' => 'Room not found.']);
    });
    
});
