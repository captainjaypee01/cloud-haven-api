<?php
namespace App\Contracts\Repositories;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;

interface RoomRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator;
    public function getId(int $id): Room;
}
