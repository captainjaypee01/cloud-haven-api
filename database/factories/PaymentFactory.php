<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'provider' => $this->faker->randomElement(['gcash', 'maya', 'bank_transfer', 'cash']),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'status' => $this->faker->randomElement(['pending', 'paid', 'cancelled', 'failed']),
            'transaction_id' => $this->faker->uuid(),
            'remarks' => $this->faker->optional()->sentence(),
            'error_code' => null,
            'error_message' => null,
            'response_data' => null,
            'proof_status' => 'none',
            'proof_upload_count' => 0,
            'proof_upload_generation' => 1,
            'proof_last_file_path' => null,
            'proof_rejected_reason' => null,
            'proof_rejected_by' => null,
            'proof_rejected_at' => null,
            'last_proof_notification_at' => null,
            'proof_last_uploaded_at' => null,
        ];
    }

    /**
     * Indicate that the payment has pending proof.
     */
    public function pendingProof(): static
    {
        return $this->state(fn (array $attributes) => [
            'proof_status' => 'pending',
            'proof_upload_count' => 1,
            'proof_last_uploaded_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment has accepted proof.
     */
    public function acceptedProof(): static
    {
        return $this->state(fn (array $attributes) => [
            'proof_status' => 'accepted',
            'proof_upload_count' => 1,
            'proof_last_uploaded_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment has rejected proof.
     */
    public function rejectedProof(string $reason = 'Invalid proof'): static
    {
        return $this->state(fn (array $attributes) => [
            'proof_status' => 'rejected',
            'proof_upload_count' => 1,
            'proof_rejected_reason' => $reason,
            'proof_rejected_by' => User::factory(),
            'proof_rejected_at' => now(),
            'proof_last_uploaded_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the payment has rejected proof that's past grace period.
     */
    public function rejectedProofExpired(string $reason = 'Invalid proof'): static
    {
        return $this->state(fn (array $attributes) => [
            'proof_status' => 'rejected',
            'proof_upload_count' => 1,
            'proof_rejected_reason' => $reason,
            'proof_rejected_by' => User::factory(),
            'proof_rejected_at' => now()->subDays(3), // Past 2-day grace period
            'proof_last_uploaded_at' => now()->subDays(4),
        ]);
    }

    /**
     * Indicate that the payment is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_code' => 'PAYMENT_FAILED',
            'error_message' => 'Payment processing failed',
        ]);
    }
}
