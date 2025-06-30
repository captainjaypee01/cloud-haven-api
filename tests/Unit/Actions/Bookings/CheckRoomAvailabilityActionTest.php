<?php

describe('CheckRoomAvailabilityAction (No Quantity)', function () {

    beforeEach(function () {
        $this->lockService = Mockery::mock(\App\Contracts\Services\BookingLockServiceInterface::class);
        $this->repo = new \App\Repositories\RoomRepository($this->lockService);
        $this->action = new \App\Actions\Bookings\CheckRoomAvailabilityAction($this->repo);
        $this->room = \App\Models\Room::factory()->create(['quantity' => 2]);
    });

    it('passes when enough units are available for all entries', function () {
        $roomArr = [
            (object)['room_id' => $this->room->id, 'adults' => 2, 'children' => 1],
            (object)['room_id' => $this->room->id, 'adults' => 1, 'children' => 1],
        ];
        $this->lockService->shouldReceive('get')->andReturn(null); // no locks
        $this->action->execute($roomArr, '2025-10-01', '2025-10-03');
        expect(true)->toBeTrue();
    });

    it('throws if more booking_rooms than available rooms (same room)', function () {
        $roomArr = [
            (object)['room_id' => $this->room->id, 'adults' => 2, 'children' => 1],
            (object)['room_id' => $this->room->id, 'adults' => 1, 'children' => 1],
            (object)['room_id' => $this->room->id, 'adults' => 1, 'children' => 1],
        ];
        $this->lockService->shouldReceive('get')->andReturn(null);
        expect(fn() => $this->action->execute($roomArr, '2025-10-01', '2025-10-03'))
            ->toThrow(\Exception::class, 'Room not available');
    });
});
