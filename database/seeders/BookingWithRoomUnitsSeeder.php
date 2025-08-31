<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\MealPrice;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Enums\RoomUnitStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookingWithRoomUnitsSeeder extends Seeder
{
    private $occupancyMap = [];
    private $rooms = [];
    private $roomUnits = [];
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
            $this->command->warn('Skipping BookingWithRoomUnitsSeeder in production environment');
            return;
        }

        $this->command->info('Starting BookingWithRoomUnitsSeeder for DEV/UAT environment...');

        // Initialize data
        $this->initializeData();

        // Create users if needed
        $this->createUsers();

        // Generate bookings with focus on weekends
        $this->generateWeekendBookings();

        $this->command->info('BookingWithRoomUnitsSeeder completed successfully!');
    }

    private function initializeData(): void
    {
        // Load rooms and initialize occupancy map
        $this->rooms = Room::where('status', 1)->get()->keyBy('id');
        
        // Load ALL room units (not just available ones)
        $this->roomUnits = RoomUnit::all()->groupBy('room_id');
        
        foreach ($this->rooms as $room) {
            $this->occupancyMap[$room->id] = [];
        }

        // Load users
        $this->users = User::where('role', 'user')->get();

        // Load active promos
        $this->promos = Promo::where('active', true)->get();

        // Load meal prices
        $this->mealPrices = MealPrice::all()->groupBy('category');

        $this->command->info('Initialized data: ' . count($this->rooms) . ' rooms, ' . count($this->roomUnits->flatten(1)) . ' room units, ' . count($this->users) . ' users, ' . count($this->promos) . ' promos');
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
        $targetUserCount = 50; // Reduced for testing

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

    private function generateWeekendBookings(): void
    {
        $startDate = Carbon::parse('2025-01-01');
        $endDate = Carbon::parse('2025-12-31');
        $bookingsCreated = 0;

        // Generate bookings for every day of the year
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeek;
            
            if ($dayOfWeek >= 5 || $dayOfWeek == 0) { // Friday (5), Saturday (6), Sunday (0)
                // Create 4-6 bookings for weekends
                $weekendBookings = $this->createWeekendBookings($currentDate);
                $bookingsCreated += $weekendBookings;
                
                if ($weekendBookings > 0) {
                    $this->command->info("Created $weekendBookings bookings for weekend of " . $currentDate->format('Y-m-d'));
                }
            } else {
                // Create 4-6 bookings for weekdays
                $weekdayBookings = $this->createWeekdayBookings($currentDate);
                $bookingsCreated += $weekdayBookings;
                
                if ($weekdayBookings > 0) {
                    $this->command->info("Created $weekdayBookings bookings for weekday of " . $currentDate->format('Y-m-d'));
                }
            }
            
            $currentDate->addDay();
        }

        $this->command->info("Successfully created $bookingsCreated bookings");
    }

    private function createWeekendBookings(Carbon $date): int
    {
        $bookingsCreated = 0;
        $maxWeekendBookings = \fake()->numberBetween(4, 6); // 4-6 bookings for weekends
        
        for ($i = 0; $i < $maxWeekendBookings; $i++) {
            $stayDuration = \fake()->randomElement([1, 2, 3]); // Weekend stays
            $checkInDate = $date->copy();
            $checkOutDate = $checkInDate->copy()->addDays($stayDuration);
            
            // Select available room units
            $selectedRoomUnits = $this->selectAvailableRoomUnits($checkInDate, $checkOutDate);
            
            if (empty($selectedRoomUnits)) {
                continue; // No available units
            }
            
            // Create the booking
            $booking = $this->createBookingWithRoomUnits($checkInDate, $checkOutDate, $selectedRoomUnits);
            
            if ($booking) {
                // Update occupancy map
                $this->updateOccupancyMap($selectedRoomUnits, $checkInDate, $checkOutDate);
                $bookingsCreated++;
            }
        }
        
        return $bookingsCreated;
    }

    private function createWeekdayBookings(Carbon $date): int
    {
        $bookingsCreated = 0;
        $maxWeekdayBookings = \fake()->numberBetween(4, 6); // 4-6 bookings for weekdays
        
        for ($i = 0; $i < $maxWeekdayBookings; $i++) {
            $stayDuration = \fake()->randomElement([1, 2, 3, 4, 5]); // Longer stays possible
            $checkInDate = $date->copy();
            $checkOutDate = $checkInDate->copy()->addDays($stayDuration);
            
            // Select available room units
            $selectedRoomUnits = $this->selectAvailableRoomUnits($checkInDate, $checkOutDate);
            
            if (empty($selectedRoomUnits)) {
                continue; // No available units
            }
            
            // Create the booking
            $booking = $this->createBookingWithRoomUnits($checkInDate, $checkOutDate, $selectedRoomUnits);
            
            if ($booking) {
                // Update occupancy map
                $this->updateOccupancyMap($selectedRoomUnits, $checkInDate, $checkOutDate);
                $bookingsCreated++;
            }
        }
        
        return $bookingsCreated;
    }

    private function selectAvailableRoomUnits(Carbon $checkIn, Carbon $checkOut): array
    {
        $availableUnits = [];
        
        foreach ($this->roomUnits as $roomId => $units) {
            foreach ($units as $unit) {
                // Check if unit is available (not under maintenance or blocked) AND not occupied
                if ($this->isUnitAvailable($unit->id, $checkIn, $checkOut) && $this->isUnitBookable($unit)) {
                    $room = $this->rooms[$roomId];
                    $availableUnits[] = [
                        'room_unit_id' => $unit->id,
                        'room_id' => $roomId,
                        'room' => $room,
                        'unit' => $unit,
                    ];
                }
            }
        }
        
        // If no units available, return empty array
        if (empty($availableUnits)) {
            return [];
        }
        
        // Randomly select 1-4 units (or all available if less than 4)
        // Prefer multiple rooms per booking when possible
        $preferredUnits = min(4, count($availableUnits));
        $numUnits = \fake()->numberBetween(1, $preferredUnits);
        $selectedUnits = \fake()->randomElements($availableUnits, $numUnits);
        return is_array($selectedUnits) ? $selectedUnits : $selectedUnits->toArray();
    }

    private function isUnitAvailable(int $unitId, Carbon $checkIn, Carbon $checkOut): bool
    {
        $currentDate = $checkIn->copy();
        
        while ($currentDate->lt($checkOut)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            if (isset($this->occupancyMap[$unitId][$dateStr])) {
                return false; // Unit is occupied on this date
            }
            
            $currentDate->addDay();
        }
        
        return true;
    }

    private function isUnitBookable(RoomUnit $unit): bool
    {
        // Check if unit is not under maintenance or blocked
        return !in_array($unit->status, [RoomUnitStatusEnum::MAINTENANCE, RoomUnitStatusEnum::BLOCKED]);
    }

    private function createBookingWithRoomUnits(Carbon $checkIn, Carbon $checkOut, array $selectedRoomUnits): ?Booking
    {
        $nights = $checkIn->diffInDays($checkOut);
        $user = $this->users->random();

        // Calculate total capacity and guests
        $totalCapacity = 0;
        foreach ($selectedRoomUnits as $unitSelection) {
            $room = $unitSelection['room'];
            $totalCapacity += $room->max_guests;
        }
        
        $adults = \fake()->numberBetween(1, min($totalCapacity, 8));
        $children = \fake()->numberBetween(0, max(0, min($totalCapacity - $adults, 4)));
        $totalGuests = $adults + $children;

        // Calculate room pricing
        $totalPrice = 0;
        foreach ($selectedRoomUnits as $unitSelection) {
            $room = $unitSelection['room'];
            $totalPrice += $room->price_per_night * $nights;
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

        // Determine status based on booking date and realistic business logic
        $bookingMonth = $checkIn->month;
        $bookingYear = $checkIn->year;
        
        // For 2025 data, treat January-June as "past months" (mostly paid)
        // July-December as "future months" (mix of statuses)
        $isPastMonth = $bookingMonth <= 8; // January to August 2025
        $isCurrentMonth = $bookingMonth == 9; // September 2025
        $isFutureMonth = $bookingMonth >= 10; // October to December 2025
        
        // Determine status based on when the booking is for
        $status = $this->determineStatusByDate($checkIn, $isPastMonth, $isCurrentMonth, $isFutureMonth);
        
        // Set payment details based on status
        $paymentAmount = null;
        $downpaymentAmount = null;
        $paidAt = null;
        $downpaymentAt = null;
        $failedPaymentAttempts = 0;
        $lastPaymentFailedAt = null;
        $reservedUntil = null;

        switch ($status) {
            case 'paid':
                $paymentAmount = $finalPrice;
                $paidAt = $checkIn->copy()->subDays(\fake()->numberBetween(1, 30));
                break;
                
            case 'downpayment':
                $downpaymentAmount = $finalPrice * 0.5; // Exactly 50%
                $paymentAmount = $downpaymentAmount;
                $downpaymentAt = $checkIn->copy()->subDays(\fake()->numberBetween(1, 30));
                break;
                
            case 'failed':
                $failedPaymentAttempts = \fake()->numberBetween(1, 3);
                $lastPaymentFailedAt = now()->subDays(\fake()->numberBetween(1, 7));
                break;
                
            case 'cancelled':
                // No payment, just cancelled
                break;
                
            case 'pending':
            default:
                $reservedUntil = now()->addHours(\fake()->numberBetween(1, 24));
                break;
        }

        // Guest details
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
            'payment_option' => $status === 'downpayment' ? 'downpayment' : 'full',
            'downpayment_amount' => $downpaymentAmount,
            'final_price' => $finalPrice,
            'status' => $status,
            'failed_payment_attempts' => $failedPaymentAttempts,
            'last_payment_failed_at' => $lastPaymentFailedAt,
            'reserved_until' => $reservedUntil,
            'downpayment_at' => $downpaymentAt,
            'paid_at' => $paidAt,
            'is_reviewed' => false,
        ]);

        // Create booking rooms with room units
        foreach ($selectedRoomUnits as $unitSelection) {
            $room = $unitSelection['room'];
            $unit = $unitSelection['unit'];
            
            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $unitSelection['room_id'],
                'room_unit_id' => $unitSelection['room_unit_id'],
                'price_per_night' => $room->price_per_night,
                'adults' => intval($adults / count($selectedRoomUnits)), // Distribute guests
                'children' => intval($children / count($selectedRoomUnits)),
                'total_guests' => intval($totalGuests / count($selectedRoomUnits)),
            ]);
        }

        // Create payment record for ALL bookings (this determines the actual status)
        $this->createPayment($booking, $paymentAmount, $status);

        return $booking;
    }

    private function updateOccupancyMap(array $selectedRoomUnits, Carbon $checkIn, Carbon $checkOut): void
    {
        $currentDate = $checkIn->copy();
        
        while ($currentDate->lt($checkOut)) {
            $dateStr = $currentDate->format('Y-m-d');
            
            foreach ($selectedRoomUnits as $unitSelection) {
                $unitId = $unitSelection['room_unit_id'];
                if (!isset($this->occupancyMap[$unitId])) {
                    $this->occupancyMap[$unitId] = [];
                }
                $this->occupancyMap[$unitId][$dateStr] = true;
            }
            
            $currentDate->addDay();
        }
    }

    private function calculateMealPrice(int $adults, int $children, int $nights): float
    {
        $adultMealPrice = $this->mealPrices->get('adult')?->first()?->price ?? 0;
        $childMealPrice = $this->mealPrices->get('children')?->first()?->price ?? 0;
        
        return ($adults * $adultMealPrice + $children * $childMealPrice) * $nights;
    }

    private function selectEligiblePromo(Carbon $checkIn, Carbon $checkOut, float $finalPrice, float $totalPrice, float $mealPrice): ?Promo
    {
        foreach ($this->promos as $promo) {
            if ($this->isPromoEligible($promo, $checkIn, $checkOut, $finalPrice, $totalPrice, $mealPrice)) {
                return $promo;
            }
        }
        
        return null;
    }

    private function isPromoEligible(Promo $promo, Carbon $checkIn, Carbon $checkOut, float $finalPrice, float $totalPrice, float $mealPrice): bool
    {
        // Check minimum stay
        $nights = $checkIn->diffInDays($checkOut);
        if ($promo->minimum_stay && $nights < $promo->minimum_stay) {
            return false;
        }

        // Check minimum amount
        if ($promo->minimum_amount && $finalPrice < $promo->minimum_amount) {
            return false;
        }

        // Check date range
        if ($promo->start_date && $checkIn->lt($promo->start_date)) {
            return false;
        }
        if ($promo->end_date && $checkOut->gt($promo->end_date)) {
            return false;
        }

        return true;
    }

    private function calculateDiscount(Promo $promo, float $finalPrice, float $totalPrice, float $mealPrice): float
    {
        if ($promo->discount_type === 'percentage') {
            $discountAmount = $finalPrice * ($promo->discount_value / 100);
        } else {
            $discountAmount = $promo->discount_value;
        }

        return min($discountAmount, $finalPrice);
    }

    private function determineStatusByDate(Carbon $checkIn, bool $isPastMonth, bool $isCurrentMonth, bool $isFutureMonth): string
    {
        // For past months (January-August 2025), mostly paid with some failed/cancelled
        if ($isPastMonth) {
            $rand = \fake()->numberBetween(0, 100) / 100; // Generate 0.0 to 1.0
            if ($rand < 0.85) {
                return 'paid';
            } elseif ($rand < 0.92) {
                return 'failed';
            } elseif ($rand < 0.97) {
                return 'cancelled';
            } else {
                return 'pending';
            }
        }
        
        // For current month (September 2025), mix of paid, downpayment, and pending
        if ($isCurrentMonth) {
            $rand = \fake()->numberBetween(0, 100) / 100; // Generate 0.0 to 1.0
            if ($rand < 0.40) {
                return 'paid';
            } elseif ($rand < 0.70) {
                return 'downpayment';
            } elseif ($rand < 0.85) {
                return 'pending';
            } elseif ($rand < 0.95) {
                return 'failed';
            } else {
                return 'cancelled';
            }
        }
        
        // For future months (October-December 2025), more pending and downpayment
        $rand = \fake()->numberBetween(0, 100) / 100; // Generate 0.0 to 1.0
        if ($rand < 0.25) {
            return 'paid';
        } elseif ($rand < 0.55) {
            return 'downpayment';
        } elseif ($rand < 0.80) {
            return 'pending';
        } elseif ($rand < 0.90) {
            return 'failed';
        } else {
            return 'cancelled';
        }
    }

    private function createPayment(Booking $booking, ?float $paymentAmount, string $status): void
    {
        $paymentStatus = 'pending';
        $amount = 0;
        
        switch ($status) {
            case 'paid':
                $paymentStatus = 'paid';
                $amount = $paymentAmount ?? $booking->final_price;
                break;
                
            case 'downpayment':
                $paymentStatus = 'paid';
                $amount = $paymentAmount ?? ($booking->final_price * 0.5);
                break;
                
            case 'failed':
                $paymentStatus = 'failed';
                $amount = $paymentAmount ?? $booking->final_price;
                break;
                
            case 'cancelled':
                $paymentStatus = 'cancelled';
                $amount = 0;
                break;
                
            case 'pending':
            default:
                $paymentStatus = 'pending';
                $amount = 0;
                break;
        }
        
        Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'provider' => 'bank_transfer', // Only bank transfer for now
            'status' => $paymentStatus,
            'transaction_id' => $paymentStatus === 'paid' ? 'TXN' . Str::random(10) : null,
        ]);
    }
}
