<?php

namespace App\Services;

use App\Contracts\Services\DashboardServiceInterface;
use App\Models\Booking;
use App\Models\BookingRoom;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService implements DashboardServiceInterface
{
    public function getDashboardData(): array
    {
        // 1. Overview metrics (only confirmed bookings)
        $totalBookings = Booking::whereIn('status', ['paid', 'downpayment'])->count();
        $totalGuests   = Booking::whereIn('status', ['paid', 'downpayment'])->sum('total_guests');
        
        // Calculate actual revenue from confirmed bookings only
        $totalRevenue = DB::table('bookings')
            ->whereIn('status', ['paid', 'downpayment'])
            ->selectRaw('
                SUM(CASE 
                    WHEN status = "paid" THEN final_price 
                    WHEN status = "downpayment" THEN downpayment_amount 
                    ELSE 0 
                END) as revenue
            ')
            ->value('revenue') ?? 0;
        $averageRating = null;  // placeholder (no ratings in DB yet)

        // 2. Top 5 most booked rooms (only confirmed bookings)
        $topRoomsQuery = DB::table('booking_rooms')
            ->join('rooms', 'booking_rooms.room_id', '=', 'rooms.id')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->select('rooms.name', DB::raw('COUNT(*) as count'))
            ->groupBy('booking_rooms.room_id', 'rooms.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        $topRooms = $topRoomsQuery->map(fn($r) => [
            'name'  => $r->name,
            'count' => $r->count,
        ])->toArray();

        // 3. Monthly stats for current year (only confirmed bookings)
        $year = now()->year;
        $monthlyRaw = DB::table('bookings')
            ->selectRaw('
                MONTH(check_in_date) as month, 
                COUNT(*) as bookings, 
                SUM(total_guests) as guests, 
                SUM(CASE 
                    WHEN status = "paid" THEN final_price 
                    WHEN status = "downpayment" THEN downpayment_amount 
                    ELSE 0 
                END) as revenue
            ')
            ->whereYear('check_in_date', $year)
            ->whereIn('status', ['paid', 'downpayment'])
            ->groupBy(DB::raw('MONTH(check_in_date)'))
            ->orderBy(DB::raw('MONTH(check_in_date)'))
            ->get();
        // Prepare full month-by-month data up to current month
        $monthlyStats = [];
        $statsByMonth = $monthlyRaw->keyBy('month');
        $currentMonth = now()->month;
        for ($m = 1; $m <= $currentMonth; $m++) {
            $monthName = Carbon::createFromDate($year, $m, 1)->format('M');
            if ($statsByMonth->has($m)) {
                $entry = $statsByMonth->get($m);
                $monthlyStats[] = [
                    'month'    => $monthName,
                    'bookings' => (int)$entry->bookings,
                    'guests'   => (int)$entry->guests,
                    'revenue'  => (float)$entry->revenue,
                ];
            } else {
                // no confirmed bookings this month
                $monthlyStats[] = [
                    'month'    => $monthName,
                    'bookings' => 0,
                    'guests'   => 0,
                    'revenue'  => 0.0,
                ];
            }
        }

        // 4. Bookings for today and tomorrow (including overlapping bookings)
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $upcomingBookings = Booking::with('bookingRooms.room', 'payments', 'otherCharges')
            ->where(function ($query) use ($today, $tomorrow) {
                // Bookings that overlap with today or tomorrow
                // A booking overlaps with a date if:
                // - check_in_date <= date AND check_out_date > date
                $query->where(function ($q) use ($today) {
                    $q->where('check_in_date', '<=', $today->toDateString())
                      ->where('check_out_date', '>', $today->toDateString());
                })
                ->orWhere(function ($q) use ($tomorrow) {
                    $q->where('check_in_date', '<=', $tomorrow->toDateString())
                      ->where('check_out_date', '>', $tomorrow->toDateString());
                });
            })
            ->whereIn('status', ['paid', 'downpayment'])
            ->orderBy('check_in_date')
            ->get();
        $bookingsList = $upcomingBookings->map(function ($booking) {
            // Calculate other charges total
            $otherChargesTotal = $booking->otherCharges->sum('amount');
            
            // Calculate actual final price after discounts
            $actualFinalPrice = $booking->final_price - $booking->discount_amount - $booking->pwd_senior_discount - $booking->special_discount;
            
            // Calculate total payable amount
            $totalPayable = $actualFinalPrice + $otherChargesTotal;
            
            // Calculate total paid amount
            $totalPaid = $booking->payments->where('status', 'paid')->sum('amount');
            
            // Calculate remaining balance
            $remainingBalance = max(0, $totalPayable - $totalPaid);
            
            return [
                'id'                    => $booking->id,
                'guest_name'            => $booking->guest_name,
                'rooms'                 => $booking->bookingRooms->map(fn($br) => $br->room->name)->all(),
                'final_price'           => $booking->final_price,
                'discount_amount'       => $booking->discount_amount,
                'pwd_senior_discount'   => $booking->pwd_senior_discount,
                'special_discount'      => $booking->special_discount,
                'other_charges'         => $booking->otherCharges,
                'other_charges_total'   => $otherChargesTotal,
                'total_payable'         => $totalPayable,
                'total_paid'            => $totalPaid,
                'remaining_balance'     => $remainingBalance,
                'status'                => $booking->status,
                'check_in_date'         => $booking->check_in_date,
                'check_out_date'        => $booking->check_out_date,
                'payments'              => $booking->payments,
            ];
        })->toArray();

        // 5. Booking Status Distribution
        $bookingStatusDistribution = DB::table('bookings')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst($item->status),
                'value' => (int)$item->count,
            ])->toArray();

        // 6. Payment Status Distribution
        $paymentStatusDistribution = DB::table('payments')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst($item->status),
                'value' => (int)$item->count,
            ])->toArray();

        // 7. Occupancy Rate Trends (simplified calculation)
        $occupancyTrends = [];
        $totalRooms = DB::table('rooms')->count(); // Total available rooms
        
        if ($totalRooms > 0) {
            for ($m = 1; $m <= $currentMonth; $m++) {
                $monthName = Carbon::createFromDate($year, $m, 1)->format('M');
                
                // Calculate occupancy rate for this month
                // This is a simplified calculation - in reality, you'd need to consider
                // room units and actual occupancy per day
                $monthBookings = DB::table('bookings')
                    ->whereYear('check_in_date', $year)
                    ->whereMonth('check_in_date', $m)
                    ->whereIn('status', ['paid', 'downpayment'])
                    ->count();
                
                // Rough occupancy calculation (this could be more sophisticated)
                $occupancyRate = min(($monthBookings * 2) / ($totalRooms * 30) * 100, 100);
                
                $occupancyTrends[] = [
                    'month' => $monthName,
                    'occupancy_rate' => round($occupancyRate, 1),
                ];
            }
        }

        // 8. Add expenses and profit margin to monthly stats (placeholder data)
        // In a real application, you'd have an expenses table
        foreach ($monthlyStats as &$month) {
            // Placeholder: assume 30% of revenue goes to expenses
            $month['expenses'] = $month['revenue'] * 0.3;
            $month['profit_margin'] = $month['revenue'] > 0 
                ? round((($month['revenue'] - $month['expenses']) / $month['revenue']) * 100, 1)
                : 0;
        }

        // Compile all parts into a single response structure
        return [
            'metrics' => [
                'totalBookings' => $totalBookings,
                'totalGuests'   => $totalGuests,
                'totalRevenue'  => $totalRevenue,
                'averageRating' => $averageRating,
            ],
            'top_rooms'         => $topRooms,
            'monthly_stats'     => $monthlyStats,
            'bookings_today_tomorrow' => $bookingsList,
            'booking_status_distribution' => $bookingStatusDistribution,
            'payment_status_distribution' => $paymentStatusDistribution,
            'occupancy_trends' => $occupancyTrends,
        ];
    }
}
