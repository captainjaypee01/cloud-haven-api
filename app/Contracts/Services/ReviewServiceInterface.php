<?php

namespace App\Contracts\Services;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewServiceInterface
{
    // CRUD Methods
    public function list(array $filters): LengthAwarePaginator;
    public function create(array $data): Review;
    public function show(int $id): Review;
    public function update(array $data, int $id): Review;
    public function delete(int $id): bool;

    // Review Request Methods
    public function generateReviewToken(Booking $booking): string;
    public function sendReviewRequestEmail(Booking $booking): bool;
    public function getEligibleBookingsForReviewRequest(int $daysAfterCheckout = 1): Collection;
    public function validateReviewToken(string $token): ?Booking;
    public function canSendReviewRequest(Booking $booking): bool;
}
