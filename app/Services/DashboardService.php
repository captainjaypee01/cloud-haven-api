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
        // 1. Overview metrics
        $totalBookings = Booking::count();
        $totalGuests   = Booking::sum('total_guests');
        $totalRevenue  = Booking::sum('final_price');
        $averageRating = null;  // placeholder (no ratings in DB yet)

        // 2. Top 5 most booked rooms
        $topRoomsQuery = DB::table('booking_rooms')
            ->join('rooms', 'booking_rooms.room_id', '=', 'rooms.id')
            ->select('rooms.name', DB::raw('COUNT(*) as count'))
            ->groupBy('booking_rooms.room_id', 'rooms.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        $topRooms = $topRoomsQuery->map(fn($r) => [
            'name'  => $r->name,
            'count' => $r->count,
        ])->toArray();

        // 3. Monthly stats for current year (bookings, guests, revenue per month)
        $year = now()->year;
        $monthlyRaw = DB::table('bookings')
            ->selectRaw('MONTH(check_in_date) as month, COUNT(*) as bookings, SUM(total_guests) as guests, SUM(final_price) as revenue')
            ->whereYear('check_in_date', $year)
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
                // no bookings this month
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
            return [
                'id'                    => $booking->id,
                'guest_name'            => $booking->guest_name,
                'rooms'                 => $booking->bookingRooms->map(fn($br)          => $br->room->name)->all(),
                'final_price'           => $booking->final_price,
                'status'                => $booking->status,
                'check_in_date'         => $booking->check_in_date,
                'check_out_date'        => $booking->check_out_date,
                'payments'              => $booking->payments,
                'other_charges'         => $booking->otherCharges,
            ];
        })->toArray();

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
        ];
    }
}
