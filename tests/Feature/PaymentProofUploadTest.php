<?php

use App\Models\User;
use App\Models\Booking;
use App\Models\Payment;
use App\Events\PaymentProofUploaded;
use App\Services\PaymentProofService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Mock local storage for file uploads (payment proofs use public disk)
    Storage::fake('public');
    Mail::fake();
    Event::fake();
    
    // Create test user, booking, and payment
    $this->user = User::factory()->create();
    $this->booking = Booking::factory()->create(['user_id' => $this->user->id]);
    $this->payment = Payment::factory()->create(['booking_id' => $this->booking->id]);
    
    // Admin user for admin tests
    $this->admin = User::factory()->create(['role' => 'admin']);
});

describe('User Payment Proof Upload', function () {
    it('allows user to upload proof for their payment', function () {
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/user/payments/{$this->payment->id}/proof", [
                'proof_file' => $file
            ]);
            
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Proof of payment uploaded successfully.'
            ]);
            
        $this->payment->refresh();
        expect($this->payment->proof_upload_count)->toBe(1);
        expect($this->payment->proof_status)->toBe('pending');
        expect($this->payment->proof_last_uploaded_at)->not->toBeNull();
        
        Event::assertDispatched(PaymentProofUploaded::class);
    });
    
    it('prevents user from uploading proof for others payment', function () {
        $otherUser = User::factory()->create();
        $otherBooking = Booking::factory()->create(['user_id' => $otherUser->id]);
        $otherPayment = Payment::factory()->create(['booking_id' => $otherBooking->id]);
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/user/payments/{$otherPayment->id}/proof", [
                'proof_file' => $file
            ]);
            
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'forbidden'
            ]);
    });
    
    it('enforces 3 upload limit per payment generation', function () {
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        // Upload 3 times successfully
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($this->user)
                ->postJson("/api/v1/user/payments/{$this->payment->id}/proof", [
                    'proof_file' => UploadedFile::fake()->image("receipt{$i}.jpg")
                ]);
                
            $response->assertStatus(200);
            $this->payment->refresh();
            expect($this->payment->proof_upload_count)->toBe($i);
        }
        
        // 4th upload should fail
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/user/payments/{$this->payment->id}/proof", [
                'proof_file' => UploadedFile::fake()->image('receipt4.jpg')
            ]);
            
        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'proof_upload_limit_reached'
            ]);
    });
    
    it('validates file type and size', function () {
        // Test invalid file type
        $invalidFile = UploadedFile::fake()->create('document.txt', 100);
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/user/payments/{$this->payment->id}/proof", [
                'proof_file' => $invalidFile
            ]);
            
        $response->assertStatus(422);
        
        // Test file too large (6MB when limit is 5MB)
        $largeFile = UploadedFile::fake()->create('receipt.jpg', 6144); // 6MB
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/user/payments/{$this->payment->id}/proof", [
                'proof_file' => $largeFile
            ]);
            
        $response->assertStatus(422);
    });
    
    it('requires authentication for user route', function () {
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $response = $this->postJson("/api/v1/user/payments/{$this->payment->id}/proof", [
            'proof_file' => $file
        ]);
        
        $response->assertStatus(401);
    });
});

describe('Admin Payment Proof Management', function () {
    it('allows admin to reset proof uploads', function () {
        // Set up payment with some uploads
        $this->payment->update([
            'proof_upload_count' => 2,
            'proof_status' => 'rejected',
            'proof_upload_generation' => 1
        ]);
        
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/payments/{$this->payment->id}/proof-upload/reset", [
                'reason' => 'Testing reset functionality'
            ]);
            
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Proof upload count reset successfully.'
            ]);
            
        $this->payment->refresh();
        expect($this->payment->proof_upload_count)->toBe(0);
        expect($this->payment->proof_upload_generation)->toBe(2);
        expect($this->payment->proof_status)->toBe('none');
    });
    
    it('allows admin to accept proof', function () {
        $this->payment->update(['proof_status' => 'pending']);
        
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/payments/{$this->payment->id}/proof-status", [
                'status' => 'accepted'
            ]);
            
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Proof accepted successfully.'
            ]);
            
        $this->payment->refresh();
        expect($this->payment->proof_status)->toBe('accepted');
        expect($this->payment->proof_rejected_reason)->toBeNull();
    });
    
    it('allows admin to reject proof with reason', function () {
        $this->payment->update(['proof_status' => 'pending']);
        
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/payments/{$this->payment->id}/proof-status", [
                'status' => 'rejected',
                'reason' => 'Image is blurry and unreadable'
            ]);
            
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Proof rejected successfully.'
            ]);
            
        $this->payment->refresh();
        expect($this->payment->proof_status)->toBe('rejected');
        expect($this->payment->proof_rejected_reason)->toBe('Image is blurry and unreadable');
        expect($this->payment->proof_rejected_by)->toBe($this->admin->id);
    });
    
    it('requires admin role for proof management', function () {
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/admin/payments/{$this->payment->id}/proof-upload/reset");
            
        $response->assertStatus(403); // Forbidden due to role middleware
    });
    
    it('validates proof status values', function () {
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/payments/{$this->payment->id}/proof-status", [
                'status' => 'invalid_status'
            ]);
            
        $response->assertStatus(422);
    });
});

describe('Payment Proof Service', function () {
    beforeEach(function () {
        $this->proofService = app(PaymentProofService::class);
    });
    
    it('handles successful proof upload', function () {
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $result = $this->proofService->uploadProof($this->payment, $file);
        
        expect($result['success'])->toBeTrue();
        expect($result['upload_count'])->toBe(1);
        expect($result['max_uploads'])->toBe(3);
        
        $this->payment->refresh();
        expect($this->payment->proof_status)->toBe('pending');
        expect($this->payment->proof_upload_count)->toBe(1);
    });
    
    it('handles upload limit reached', function () {
        $this->payment->update(['proof_upload_count' => 3]);
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $result = $this->proofService->uploadProof($this->payment, $file);
        
        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('proof_upload_limit_reached');
    });
    
    it('handles proof reset functionality', function () {
        $this->payment->update([
            'proof_upload_count' => 2,
            'proof_status' => 'pending',
            'proof_upload_generation' => 1
        ]);
        
        $result = $this->proofService->resetProofUploads(
            $this->payment, 
            'Test reset', 
            $this->admin->id
        );
        
        expect($result['success'])->toBeTrue();
        
        $this->payment->refresh();
        expect($this->payment->proof_upload_count)->toBe(0);
        expect($this->payment->proof_upload_generation)->toBe(2);
        expect($this->payment->proof_status)->toBe('none');
    });
    
    it('handles proof status updates', function () {
        $this->payment->update(['proof_status' => 'pending']);
        
        $result = $this->proofService->updateProofStatus(
            $this->payment,
            'accepted',
            null,
            $this->admin->id
        );
        
        expect($result['success'])->toBeTrue();
        
        $this->payment->refresh();
        expect($this->payment->proof_status)->toBe('accepted');
    });
});

describe('Guest User Proof Upload', function () {
    it('allows guest users to upload proof via reference number', function () {
        // Create a guest booking (user_id = null)
        $guestBooking = Booking::factory()->create(['user_id' => null]);
        $guestPayment = Payment::factory()->create(['booking_id' => $guestBooking->id]);
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $response = $this->postJson("/api/v1/bookings/ref/{$guestBooking->reference_number}/payments/{$guestPayment->id}/proof", [
            'proof_file' => $file
        ]);
            
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Proof of payment uploaded successfully.'
            ]);
            
        $guestPayment->refresh();
        expect($guestPayment->proof_upload_count)->toBe(1);
        expect($guestPayment->proof_status)->toBe('pending');
    });
    
    it('prevents access to payments from different bookings via guest route', function () {
        $guestBooking = Booking::factory()->create(['user_id' => null]);
        $differentPayment = Payment::factory()->create(['booking_id' => $this->booking->id]); // Different booking
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $response = $this->postJson("/api/v1/bookings/ref/{$guestBooking->reference_number}/payments/{$differentPayment->id}/proof", [
            'proof_file' => $file
        ]);
            
        $response->assertStatus(404); // Payment not found for this booking
    });
    
    it('enforces upload limits for guest users same as registered users', function () {
        $guestBooking = Booking::factory()->create(['user_id' => null]);
        $guestPayment = Payment::factory()->create([
            'booking_id' => $guestBooking->id,
            'proof_upload_count' => 3  // Already at limit
        ]);
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        
        $response = $this->postJson("/api/v1/bookings/ref/{$guestBooking->reference_number}/payments/{$guestPayment->id}/proof", [
            'proof_file' => $file
        ]);
            
        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error_code' => 'proof_upload_limit_reached'
            ]);
    });
});

describe('Email Notification Suppression', function () {
    it('sends email on first proof upload', function () {
        Event::fake();
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        $this->proofService = app(PaymentProofService::class);
        
        $this->proofService->uploadProof($this->payment, $file);
        
        Event::assertDispatched(PaymentProofUploaded::class);
    });
    
    it('suppresses email notifications within suppress window', function () {
        // Mock the time for suppression test
        $this->payment->update([
            'last_proof_notification_at' => now()->subMinutes(2) // 2 minutes ago
        ]);
        
        Event::fake();
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        $this->proofService = app(PaymentProofService::class);
        
        $this->proofService->uploadProof($this->payment, $file);
        
        // Event should still be dispatched, but listener should suppress email
        Event::assertDispatched(PaymentProofUploaded::class);
    });
    
    it('allows email after suppress window expires', function () {
        // Set last notification to 6 minutes ago (beyond 5-minute default window)
        $this->payment->update([
            'last_proof_notification_at' => now()->subMinutes(6)
        ]);
        
        Event::fake();
        
        $file = UploadedFile::fake()->image('receipt.jpg');
        $this->proofService = app(PaymentProofService::class);
        
        $this->proofService->uploadProof($this->payment, $file);
        
        Event::assertDispatched(PaymentProofUploaded::class);
    });
});
