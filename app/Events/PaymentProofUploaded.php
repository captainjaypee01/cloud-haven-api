<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentProofUploaded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Payment $payment;
    public Booking $booking;
    public int $sequenceNumber;
    public string $adminLink;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, Booking $booking, int $sequenceNumber, string $adminLink)
    {
        $this->payment = $payment;
        $this->booking = $booking;
        $this->sequenceNumber = $sequenceNumber;
        $this->adminLink = $adminLink;
    }
}
