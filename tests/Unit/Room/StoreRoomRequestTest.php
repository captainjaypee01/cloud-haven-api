<?php

use App\Http\Requests\Room\StoreRoomRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an admin user to pass authorize()', function () {
    // Create a mock user without database
    $admin = new User();
    $admin->role = 'admin';

    $request = new StoreRoomRequest();
    $request->setUserResolver(fn () => $admin);

    $this->assertTrue($request->authorize());
});

it('forbids a guest user in authorize()', function () {
    $guest = new User();
    $guest->role = 'guest';

    $request = new StoreRoomRequest();
    $request->setUserResolver(fn () => $guest);
    $this->assertFalse($request->authorize());
});

it('forbids a non-logged-in user', function () {
    $request = new StoreRoomRequest();
    $request->setUserResolver(fn () => null);

    expect($request->authorize())->toBeFalse();
});
