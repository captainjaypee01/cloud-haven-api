<?php

namespace App\Services;

use App\Contracts\Services\DashboardServiceInterface;
use App\Models\Booking;
use App\Models\BookingRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardService implements DashboardServiceInterface
{
    public function getDashboardData(): array
    {
        // Cache dashboard data for 5 minutes to improve performance
        return Cache::remember('dashboard_data', 300, function () {
            return $this->calculateDashboardData();
        });
    }

    private function calculateDashboardData(): array
    {
        $year = now()->year;
        $currentMonth = now()->month;
        
        // 1. Overview metrics (only confirmed bookings) - Single query optimization
        $overviewMetrics = DB::table('bookings')
            ->selectRaw('
                COUNT(*) as total_bookings,
                SUM(total_guests) as total_guests
            ')
            ->whereIn('status', ['paid', 'downpayment'])
            ->first();
        
        $totalBookings = $overviewMetrics->total_bookings ?? 0;
        $totalGuests = $overviewMetrics->total_guests ?? 0;
        
        // Calculate actual revenue from paid payments only (for confirmed bookings)
        $totalRevenue = DB::table('payments')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->where('payments.status', 'paid')
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->sum('payments.amount') ?? 0;
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

        // 3. Monthly stats for current year - Combined query optimization
        $monthlyStatsRaw = DB::table('bookings')
            ->leftJoin('payments', function($join) {
                $join->on('bookings.id', '=', 'payments.booking_id')
                     ->where('payments.status', '=', 'paid');
            })
            ->selectRaw('
                MONTH(bookings.check_in_date) as month,
                COUNT(DISTINCT bookings.id) as bookings,
                SUM(bookings.total_guests) as guests,
                COALESCE(SUM(payments.amount), 0) as revenue
            ')
            ->whereYear('bookings.check_in_date', $year)
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->groupBy(DB::raw('MONTH(bookings.check_in_date)'))
            ->orderBy(DB::raw('MONTH(bookings.check_in_date)'))
            ->get();
        
        // Index by month for faster lookup
        $monthlyDataByMonth = $monthlyStatsRaw->keyBy('month');
        // Prepare full month-by-month data up to current month
        $monthlyStats = [];
        for ($m = 1; $m <= $currentMonth; $m++) {
            $monthName = Carbon::createFromDate($year, $m, 1)->format('M');
            $monthData = $monthlyDataByMonth->get($m);
            
            $monthlyStats[] = [
                'month'    => $monthName,
                'bookings' => $monthData ? (int)$monthData->bookings : 0,
                'guests'   => $monthData ? (int)$monthData->guests : 0,
                'revenue'  => $monthData ? (float)$monthData->revenue : 0.0,
            ];
        }

        // 4. Bookings for today and tomorrow - Optimized with single query
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        
        $bookingsList = DB::table('bookings')
            ->leftJoin('booking_rooms', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->leftJoin('rooms', 'booking_rooms.room_id', '=', 'rooms.id')
            ->leftJoin('payments', function($join) {
                $join->on('bookings.id', '=', 'payments.booking_id')
                     ->where('payments.status', '=', 'paid');
            })
            ->leftJoin('other_charges', 'bookings.id', '=', 'other_charges.booking_id')
            ->selectRaw('
                bookings.id,
                bookings.guest_name,
                bookings.final_price,
                bookings.discount_amount,
                bookings.pwd_senior_discount,
                bookings.special_discount,
                bookings.status,
                bookings.check_in_date,
                bookings.check_out_date,
                GROUP_CONCAT(DISTINCT rooms.name) as room_names,
                COALESCE(SUM(DISTINCT payments.amount), 0) as total_paid,
                COALESCE(SUM(DISTINCT other_charges.amount), 0) as other_charges_total
            ')
            ->where(function ($query) use ($today, $tomorrow) {
                $query->where(function ($q) use ($today) {
                    $q->where('bookings.check_in_date', '<=', $today->toDateString())
                      ->where('bookings.check_out_date', '>', $today->toDateString());
                })
                ->orWhere(function ($q) use ($tomorrow) {
                    $q->where('bookings.check_in_date', '<=', $tomorrow->toDateString())
                      ->where('bookings.check_out_date', '>', $tomorrow->toDateString());
                });
            })
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->groupBy('bookings.id', 'bookings.guest_name', 'bookings.final_price', 'bookings.discount_amount', 
                     'bookings.pwd_senior_discount', 'bookings.special_discount', 'bookings.status', 
                     'bookings.check_in_date', 'bookings.check_out_date')
            ->orderBy('bookings.check_in_date')
            ->get()
            ->map(function ($booking) {
                $actualFinalPrice = $booking->final_price - $booking->discount_amount - $booking->pwd_senior_discount - $booking->special_discount;
                $totalPayable = $actualFinalPrice + $booking->other_charges_total;
                $remainingBalance = max(0, $totalPayable - $booking->total_paid);
                
                return [
                    'id'                    => $booking->id,
                    'guest_name'            => $booking->guest_name,
                    'rooms'                 => $booking->room_names ? explode(',', $booking->room_names) : [],
                    'final_price'           => $booking->final_price,
                    'discount_amount'       => $booking->discount_amount,
                    'pwd_senior_discount'   => $booking->pwd_senior_discount,
                    'special_discount'      => $booking->special_discount,
                    'other_charges_total'   => $booking->other_charges_total,
                    'total_payable'         => $totalPayable,
                    'total_paid'            => $booking->total_paid,
                    'remaining_balance'     => $remainingBalance,
                    'status'                => $booking->status,
                    'check_in_date'         => $booking->check_in_date,
                    'check_out_date'        => $booking->check_out_date,
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

        // 7. Occupancy Rate Trends - Optimized single query
        $totalRooms = DB::table('rooms')->count();
        $occupancyTrends = [];
        
        if ($totalRooms > 0) {
            // Get all monthly booking counts in a single query
            $monthlyBookingCounts = DB::table('bookings')
                ->selectRaw('MONTH(check_in_date) as month, COUNT(*) as bookings')
                ->whereYear('check_in_date', $year)
                ->whereIn('status', ['paid', 'downpayment'])
                ->groupBy(DB::raw('MONTH(check_in_date)'))
                ->get()
                ->keyBy('month');
            
            for ($m = 1; $m <= $currentMonth; $m++) {
                $monthName = Carbon::createFromDate($year, $m, 1)->format('M');
                $monthBookings = $monthlyBookingCounts->get($m)?->bookings ?? 0;
                
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
