<?php

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Payment::with(['booking:id,reference_number,guest_name,guest_email', 'rejectedByUser:id,name'])
            ->select([
                'id', 'booking_id', 'provider', 'status', 'amount', 
                'transaction_id', 'error_code', 'error_message', 
                'proof_status', 'proof_upload_count', 'proof_rejected_reason',
                'proof_last_file_path', 'proof_image_path', 'created_at'
            ]);

        // Search by reference number
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('booking', function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%");
            });
        }

        // Filter by payment status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by proof status
        if (!empty($filters['proof_status'])) {
            $proofStatus = $filters['proof_status'];
            if ($proofStatus === 'none') {
                $query->where(function ($q) {
                    $q->whereNull('proof_status')
                      ->orWhere('proof_status', 'none');
                });
            } else {
                $query->where('proof_status', $proofStatus);
            }
        }

        // Filter by date range
        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }
        
        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }
        
        // Legacy single date filter (for backward compatibility)
        if (!empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        // Sorting
        $sort = $filters['sort'] ?? 'created_at|desc';
        [$sortField, $sortDirection] = explode('|', $sort);
        
        // Map frontend sort fields to actual database fields
        $sortFieldMap = [
            'reference_number' => 'bookings.reference_number',
            'created_at' => 'payments.created_at',
            'provider' => 'payments.provider',
            'amount' => 'payments.amount',
            'status' => 'payments.status',
            'transaction_id' => 'payments.transaction_id',
            'proof_status' => 'payments.proof_status',
        ];

        if (isset($sortFieldMap[$sortField])) {
            if ($sortField === 'reference_number') {
                // For booking reference number, we need to join
                $query->join('bookings', 'payments.booking_id', '=', 'bookings.id')
                      ->orderBy($sortFieldMap[$sortField], $sortDirection)
                      ->select('payments.*'); // Ensure we only select payment columns
            } else {
                $query->orderBy($sortFieldMap[$sortField], $sortDirection);
            }
        } else {
            $query->orderBy('payments.created_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 10;
        return $query->paginate($perPage);
    }
}
