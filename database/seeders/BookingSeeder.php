<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\MealPrice;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// No need to import fake() - use fully qualified name

class BookingSeeder extends Seeder
{
    private $occupancyMap = [];
    private $rooms = [];
    private $users = [];
    private $promos = [];
    private $mealPrices = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Production safety check
        if (app()->environment('production')) {
            $this->command->warn('Skipping BookingSeeder in production environment');
            return;
        }

        $this->command->info('Starting BookingSeeder for DEV/UAT environment...');

        // Initialize data
        $this->initializeData();

        // Create users if needed
        $this->createUsers();

        // Generate bookings
        $this->generateBookings();

        $this->command->info('BookingSeeder completed successfully!');
    }

    private function initializeData(): void
    {
        // Load rooms and initialize occupancy map
        $this->rooms = Room::where('status', 1)->get()->keyBy('id');
        
        foreach ($this->rooms as $room) {
            $this->occupancyMap[$room->id] = [];
        }

        // Load users
        $this->users = User::where('role', 'user')->get();

        // Load active promos
        $this->promos = Promo::where('active', true)->get();

        // Load meal prices
        $this->mealPrices = MealPrice::all()->groupBy('category');

        $this->command->info('Initialized data: ' . count($this->rooms) . ' rooms, ' . count($this->users) . ' users, ' . count($this->promos) . ' promos');
    }

    private function createUsers(): void
    {
        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@example.com',
                'password' => 'admin1234',
                'role' => 'admin',
                'email_verified_at' => now(),
                'country_code' => 'PH',
                'contact_number' => '+63-912-345-6789',
            ]
        );

        // Create customer users if we don't have enough
        $existingUserCount = User::where('role', 'user')->count();
        $targetUserCount = 100;

        if ($existingUserCount < $targetUserCount) {
            $usersToCreate = $targetUserCount - $existingUserCount;
            
            for ($i = 0; $i < $usersToCreate; $i++) {
                User::create([
                    'first_name' => \fake()->firstName(),
                    'last_name' => \fake()->lastName(),
                    'email' => \fake()->unique()->safeEmail(),
                    'password' => 'password123',
                    'role' => 'user',
                    'email_verified_at' => now(),
                    'country_code' => 'PH',
                    'contact_number' => '+63-' . \fake()->numerify('9##-###-####'),
                ]);
            }

            // Reload users
            $this->users = User::where('role', 'user')->get();
            $this->command->info('Created ' . $usersToCreate . ' new users');
        }
    }

    private function generateBookings(): void
    {
        $startDate = Carbon::parse('2025-01-01');
        $endDate = Carbon::parse('2025-10-31');
        $bookingsCreated = 0;
        $bookingsSkipped = 0;

        // Generate bookings
        while ($bookingsCreated < 800) { // Target around 800 bookings
            $checkInDate = $startDate->copy()->addDays(\fake()->numberBetween(0, $startDate->diffInDays($endDate)));
            
            // Don't create bookings in Nov/Dec
            if ($checkInDate->month >= 11) {
                continue;
            }

            $stayDuration = \fake()->numberBetween(1, 7); // 1-7 nights
            $checkOutDate = $checkInDate->copy()->addDays($stayDuration);

            // Ensure checkout is not in Nov/Dec
            if ($checkOutDate->month >= 11) {
                continue;
            }

            // Select random room types (1-2 types per booking)
            $selectedRooms = $this->selectRooms();
            
            // Check availability for all selected rooms
            if (!$this->checkAvailability($selectedRooms, $checkInDate, $checkOutDate)) {
                $bookingsSkipped++;
                if ($bookingsSkipped > 2000) { // Prevent infinite loop
                    break;
                }
                continue;
            }

            // Create the booking
            $booking = $this->createBooking($checkInDate, $checkOutDate, $selectedRooms);
            
            if ($booking) {
                // Update occupancy map
                $this->updateOccupancyMap($selectedRooms, $checkInDate, $checkOutDate);
                $bookingsCreated++;
                
                if ($bookingsCreated % 50 == 0) {
                    $this->command->info("Created $bookingsCreated bookings...");
                }
            }
        }

        $this->command->info("Successfully created $bookingsCreated bookings (skipped $bookingsSkipped due to availability)");
    }

    private function selectRooms(): array
    {
        $roomIds = $this->rooms->keys()->toArray();
        $numRoomTypes = \fake()->randomFloat() < 0.85 ? 1 : 2; // 85% single room type, 15% two types
        
        $selectedRoomIds = \fake()->randomElements($roomIds, $numRoomTypes);
        $selectedRooms = [];

        foreach ($selectedRoomIds as $roomId) {
            $room = $this->rooms[$roomId];
            $maxQuantity = min($room->quantity, 3); // Don't book more than 3 of same type
            $requestedQuantity = \fake()->numberBetween(1, $maxQuantity);
            
            $selectedRooms[] = [
                'room_id' => $roomId,
                'quantity' => $requestedQuantity,
                'room' => $room,
            ];
        }

        return $selectedRooms;
    }

    private function checkAvailability(array $selectedRooms, Carbon $checkIn, Carbon $checkOut): bool
    {
        $currentDate = $checkIn->copy();
        
        while ($currentDate->lt($checkOut)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            foreach ($selectedRooms as $roomSelection) {
                $roomId = $roomSelection['room_id'];
                $quantity = $roomSelection['quantity'];
                $room = $roomSelection['room'];
                
                $currentOccupancy = $this->occupancyMap[$roomId][$dateStr] ?? 0;
                $roomModel = $roomSelection['room'];
                
                if ($currentOccupancy + $quantity > $roomModel->quantity) {
                    return false; // Not enough rooms available
                }
            }
            
            $currentDate->addDay();
        }

        return true;
    }

    private function updateOccupancyMap(array $selectedRooms, Carbon $checkIn, Carbon $checkOut): void
    {
        $currentDate = $checkIn->copy();
        
        while ($currentDate->lt($checkOut)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            foreach ($selectedRooms as $roomSelection) {
                $roomId = $roomSelection['room_id'];
                $quantity = $roomSelection['quantity'];
                
                if (!isset($this->occupancyMap[$roomId][$dateStr])) {
                    $this->occupancyMap[$roomId][$dateStr] = 0;
                }
                
                $this->occupancyMap[$roomId][$dateStr] += $quantity;
            }
            
            $currentDate->addDay();
        }
    }

    private function createBooking(Carbon $checkIn, Carbon $checkOut, array $selectedRooms): ?Booking
    {
        $nights = $checkIn->diffInDays($checkOut);
        
        // Determine if this is a guest booking (15-25% chance)
        $isGuestBooking = \fake()->randomFloat() < 0.20; // 20% guest bookings
        $user = $isGuestBooking ? null : $this->users->random();

        // Calculate guest counts
        $totalCapacity = 0;
        foreach ($selectedRooms as $roomSelection) {
            $room = $roomSelection['room'];
            $totalCapacity += $room->max_guests * $roomSelection['quantity'];
        }
        $adults = \fake()->numberBetween(1, min($totalCapacity, 8));
        $children = \fake()->numberBetween(0, max(0, min($totalCapacity - $adults, 4)));
        $totalGuests = $adults + $children;

        // Calculate room pricing
        $totalPrice = 0;
        foreach ($selectedRooms as $roomSelection) {
            $room = $roomSelection['room'];
            $totalPrice += $room->price_per_night * $roomSelection['quantity'] * $nights;
        }

        // Calculate meal pricing
        $mealPrice = $this->calculateMealPrice($adults, $children, $nights);

        // Calculate final price
        $finalPrice = $totalPrice + $mealPrice;

        // Apply promo if eligible
        $promo = $this->selectEligiblePromo($checkIn, $checkOut, $finalPrice, $totalPrice, $mealPrice);
        $discountAmount = 0;
        $promoId = null;

        if ($promo) {
            $promoId = $promo->id;
            $discountAmount = $this->calculateDiscount($promo, $finalPrice, $totalPrice, $mealPrice);
        }

        $amountDue = $finalPrice - $discountAmount;

        // Determine payment option and status
        $paymentOption = \fake()->randomElement(['full', 'downpayment', 'full', 'downpayment', 'full']); // 60% full, 40% downpayment
        $status = $this->determineBookingStatus();
        
        $downpaymentAmount = null;
        $paidAt = null;
        $downpaymentAt = null;

        if ($status === 'paid') {
            $paidAt = $checkIn->copy()->subDays(\fake()->numberBetween(1, 30));
        } elseif ($status === 'downpayment') {
            $downpaymentAmount = $amountDue * \fake()->numberBetween(30, 60) / 100;
            $downpaymentAt = $checkIn->copy()->subDays(\fake()->numberBetween(1, 30));
            $paymentOption = 'downpayment';
        }

        // Always include guest details (even for registered users as contact person may differ)
        $guestName = \fake()->name();
        $guestEmail = \fake()->safeEmail();
        $guestPhone = '+63-' . \fake()->numerify('9##-###-####');

        // Create booking
        $booking = Booking::create([
            'user_id' => $user?->id,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_in_time' => '15:00:00',
            'check_out_date' => $checkOut->format('Y-m-d'),
            'check_out_time' => '12:00:00',
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'special_requests' => \fake()->optional(0.3)->sentence(),
            'adults' => $adults,
            'children' => $children,
            'total_guests' => $totalGuests,
            'promo_id' => $promoId,
            'total_price' => $totalPrice,
            'meal_price' => $mealPrice,
            'discount_amount' => $discountAmount,
            'payment_option' => $paymentOption,
            'downpayment_amount' => $downpaymentAmount,
            'final_price' => $finalPrice,
            'status' => $status,
            'failed_payment_attempts' => $status === 'failed' ? \fake()->numberBetween(1, 3) : 0,
            'last_payment_failed_at' => $status === 'failed' ? now()->subDays(\fake()->numberBetween(1, 7)) : null,
            'reserved_until' => $status === 'pending' ? now()->addHours(\fake()->numberBetween(1, 24)) : null,
            'downpayment_at' => $downpaymentAt,
            'paid_at' => $paidAt,
            'is_reviewed' => false,
        ]);

        // Create booking rooms
        foreach ($selectedRooms as $roomSelection) {
            $room = $roomSelection['room'];
            for ($i = 0; $i < $roomSelection['quantity']; $i++) {
                BookingRoom::create([
                    'booking_id' => $booking->id,
                    'room_id' => $roomSelection['room_id'],
                    'price_per_night' => $room->price_per_night,
                    'adults' => intval($adults / count($selectedRooms)), // Distribute guests
                    'children' => intval($children / count($selectedRooms)),
                    'total_guests' => intval($totalGuests / count($selectedRooms)),
                ]);
            }
        }

        // Create payment record if applicable
        if (in_array($status, ['paid', 'downpayment'])) {
            $this->createPayment($booking, $amountDue, $downpaymentAmount, $status);
        } elseif (in_array($status, ['failed', 'cancelled'])) {
            // Some failed/cancelled bookings might have a failed payment record
            if (\fake()->boolean(40)) {
                $this->createPayment($booking, $amountDue, $downpaymentAmount, 'failed');
            }
        }

        return $booking;
    }

    private function calculateMealPrice(int $adults, int $children, int $nights): float
    {
        $mealChoice = \fake()->randomFloat();
        
        if ($mealChoice < 0.25) {
            return 0; // No meals
        }
        
        if (!$this->mealPrices->count()) {
            return 0; // No meal prices available
        }

        $nightsWithMeals = $mealChoice < 0.60 ? $nights : \fake()->numberBetween(1, $nights); // 60% all nights, 15% partial nights
        
        $adultPrice = $this->mealPrices->get('adult')?->first()?->price ?? 0;
        $childPrice = $this->mealPrices->get('child')?->first()?->price ?? 0;
        
        return ($adults * $adultPrice + $children * $childPrice) * $nightsWithMeals;
    }

    private function selectEligiblePromo(Carbon $checkIn, Carbon $checkOut, float $finalPrice, float $totalPrice, float $mealPrice): ?Promo
    {
        $eligiblePromos = $this->promos->filter(function ($promo) use ($checkIn, $checkOut) {
            return $this->isPromoEligible($promo, $checkIn, $checkOut);
        });

        if ($eligiblePromos->isEmpty()) {
            return null;
        }

        // 40% chance of applying a promo
        if (\fake()->randomFloat() > 0.40) {
            return null;
        }

        return $eligiblePromos->random();
    }

    private function isPromoEligible(Promo $promo, Carbon $checkIn, Carbon $checkOut): bool
    {
        // Check active status
        if (!$promo->active) {
            return false;
        }

        // Check booking creation time against expires_at
        if ($promo->expires_at && now()->gt($promo->expires_at)) {
            return false;
        }

        // Check max uses
        if ($promo->max_uses && $promo->uses_count >= $promo->max_uses) {
            return false;
        }

        // Check window restrictions
        if ($promo->starts_at && $promo->ends_at) {
            // Both dates must be within window
            return $checkIn->gte($promo->starts_at) && $checkOut->lte($promo->ends_at);
        } elseif ($promo->starts_at) {
            // Both dates must be >= starts_at
            return $checkIn->gte($promo->starts_at) && $checkOut->gte($promo->starts_at);
        } elseif ($promo->ends_at) {
            // Both dates must be <= ends_at
            return $checkIn->lte($promo->ends_at) && $checkOut->lte($promo->ends_at);
        }

        return true;
    }

    private function calculateDiscount(Promo $promo, float $finalPrice, float $totalPrice, float $mealPrice): float
    {
        $baseAmount = match ($promo->scope) {
            'total' => $finalPrice,
            'room' => $totalPrice,
            'meal' => $mealPrice,
            default => $finalPrice,
        };

        if ($promo->discount_type === 'percentage') {
            return min($baseAmount * ($promo->discount_value / 100), $baseAmount);
        } else {
            return min($promo->discount_value, $baseAmount);
        }
    }

    private function determineBookingStatus(): string
    {
        $rand = \fake()->randomFloat();
        
        if ($rand < 0.65) return 'paid';
        if ($rand < 0.85) return 'downpayment';
        if ($rand < 0.92) return 'pending';
        if ($rand < 0.97) return 'cancelled';
        return 'failed';
    }

    private function createPayment(Booking $booking, float $amountDue, ?float $downpaymentAmount, string $bookingStatus): void
    {
        $providers = ['gcash', 'card', 'paymaya', 'bdo_checkout'];
        
        $paymentAmount = $bookingStatus === 'downpayment' ? $downpaymentAmount : $amountDue;
        $paymentStatus = match ($bookingStatus) {
            'paid', 'downpayment' => 'paid',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };

        Payment::create([
            'booking_id' => $booking->id,
            'provider' => \fake()->randomElement($providers),
            'amount' => $paymentAmount,
            'status' => $paymentStatus,
            'transaction_id' => $paymentStatus === 'paid' ? 'TXN-' . Str::random(12) : null,
            'remarks' => $paymentStatus === 'failed' ? 'Payment failed due to insufficient funds' : null,
            'error_code' => $paymentStatus === 'failed' ? 'INSUFFICIENT_FUNDS' : null,
            'error_message' => $paymentStatus === 'failed' ? 'The transaction was declined due to insufficient funds.' : null,
            'response_data' => $paymentStatus === 'paid' ? json_encode(['success' => true, 'reference' => Str::random(8)]) : null,
        ]);
    }
}
