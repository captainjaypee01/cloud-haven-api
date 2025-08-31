<?php

use App\Mail\ProofPaymentUploadedMail;
use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->booking = Booking::factory()->create(['user_id' => $this->user->id]);
    $this->payment = Payment::factory()->create(['booking_id' => $this->booking->id]);
});

describe('ProofPaymentUploadedMail', function () {
    it('generates correct subject line', function () {
        $mailable = new ProofPaymentUploadedMail(
            $this->payment,
            $this->booking,
            2, // sequence number
            'https://admin.example.com/bookings/123'
        );
        
        $envelope = $mailable->envelope();
        
        expect($envelope->subject)->toBe(
            sprintf(
                '[Payment Proof] %s — Payment %d — %s',
                $this->booking->reference_number,
                2,
                $this->booking->guest_name
            )
        );
    });
    
    it('includes all required data in email content', function () {
        $adminLink = 'https://admin.example.com/bookings/123';
        
        $mailable = new ProofPaymentUploadedMail(
            $this->payment,
            $this->booking,
            1,
            $adminLink
        );
        
        $content = $mailable->content();
        
        expect($content->markdown)->toBe('emails.proof_payment_uploaded');
        expect($content->with)->toHaveKeys([
            'payment',
            'booking', 
            'sequenceNumber',
            'adminLink'
        ]);
        
        expect($content->with['payment'])->toBe($this->payment);
        expect($content->with['booking'])->toBe($this->booking);
        expect($content->with['sequenceNumber'])->toBe(1);
        expect($content->with['adminLink'])->toBe($adminLink);
    });
    
    it('can be rendered without errors', function () {
        $mailable = new ProofPaymentUploadedMail(
            $this->payment,
            $this->booking,
            1,
            'https://admin.example.com/bookings/123'
        );
        
        $rendered = $mailable->render();
        
        expect($rendered)->toBeString();
        expect(strlen($rendered))->toBeGreaterThan(0);
        
        // Check for key content elements
        expect($rendered)->toContain($this->booking->reference_number);
        expect($rendered)->toContain($this->booking->guest_name);
        expect($rendered)->toContain('Review Payment Proof');
    });
    
    it('maintains unified email template structure', function () {
        $mailable = new ProofPaymentUploadedMail(
            $this->payment,
            $this->booking,
            1,
            'https://admin.example.com/bookings/123'
        );
        
        $rendered = $mailable->render();
        
        // Check for unified template elements
        expect($rendered)->toContain('Booking Details');
        expect($rendered)->toContain('Payment Details');
        expect($rendered)->toContain('Action Required');
        expect($rendered)->toContain('background:#fff3e0'); // Footer background
        expect($rendered)->toContain('color:#000'); // Text color
    });
});
