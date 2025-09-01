<?php

use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Mail\ProofPaymentAcceptedMail;
use App\Mail\ProofPaymentRejectedMail;

describe('Proof Status Mailables', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'guest_email' => 'guest@example.com',
            'guest_name' => 'John Doe',
            'reference_number' => 'REF123456'
        ]);
        $this->payment = Payment::create([
            'booking_id' => $this->booking->id,
            'amount' => 1500.00,
            'provider' => 'bank_transfer',
            'transaction_id' => 'TXN789',
            'status' => 'pending'
        ]);
    });

    describe('ProofPaymentAcceptedMail', function () {
        it('generates correct subject line', function () {
            $mailable = new ProofPaymentAcceptedMail($this->payment);
            $envelope = $mailable->envelope();
            
            expect($envelope->subject)->toContain('[Payment Confirmed]')
                ->and($envelope->subject)->toContain('REF123456');
        });

        it('includes correct data in content', function () {
            $mailable = new ProofPaymentAcceptedMail($this->payment);
            $content = $mailable->content();
            
            expect($content->view)->toBe('emails.payment_success')
                ->and($content->with['payment']->id)->toBe($this->payment->id)
                ->and($content->with['booking']->id)->toBe($this->booking->id);
        });

        it('renders without errors', function () {
            $mailable = new ProofPaymentAcceptedMail($this->payment);
            
            expect(fn() => $mailable->render())->not->toThrow(\Exception::class);
        });
    });

    describe('ProofPaymentRejectedMail', function () {
        it('generates correct subject line', function () {
            $mailable = new ProofPaymentRejectedMail($this->payment, 'Invalid receipt image');
            $envelope = $mailable->envelope();
            
            expect($envelope->subject)->toContain('[Payment Verification Issue]')
                ->and($envelope->subject)->toContain('REF123456');
        });

        it('includes correct data in content', function () {
            $reason = 'Receipt image is unclear';
            $mailable = new ProofPaymentRejectedMail($this->payment, $reason);
            $content = $mailable->content();
            
            expect($content->view)->toBe('emails.payment_problem')
                ->and($content->with['payment']->id)->toBe($this->payment->id)
                ->and($content->with['booking']->id)->toBe($this->booking->id)
                ->and($content->with['rejectionReason'])->toBe($reason);
        });

        it('uses default reason when none provided', function () {
            $mailable = new ProofPaymentRejectedMail($this->payment);
            
            expect($mailable->rejectionReason)->toBe('Payment verification could not be completed at this time.');
        });

        it('renders without errors', function () {
            $mailable = new ProofPaymentRejectedMail($this->payment, 'Test rejection reason');
            
            expect(fn() => $mailable->render())->not->toThrow(\Exception::class);
        });
    });
});
