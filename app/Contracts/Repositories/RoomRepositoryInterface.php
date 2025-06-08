<?php
namespace App\Contracts\Repositories;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;

interface RoomRepositoryInterface extends RootRepositoryInterface
{
    public function getId(int $id): Room;
}
