<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CacheInvalidationService
{
    /**
     * Clear all room availability related cache when bookings change
     */
    public function clearRoomAvailabilityCache(): void
    {
        // Clear dashboard data cache
        Cache::forget('dashboard_data');
        
        // Clear calendar cache for current and next year
        $this->clearCalendarCache();
    }

    /**
     * Clear calendar cache for current and next year
     */
    public function clearCalendarCache(): void
    {
        $currentYear = now()->year;
        $nextYear = $currentYear + 1;
        
        for ($year = $currentYear; $year <= $nextYear; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $this->clearCalendarCacheForMonth($year, $month);
            }
        }
    }

    /**
     * Clear calendar cache for a specific month and year
     */
    public function clearCalendarCacheForMonth(int $year, int $month): void
    {
        $overnightCacheKey = "room_unit_calendar_{$year}_{$month}";
        $dayTourCacheKey = "day_tour_calendar_{$year}_{$month}";
        
        Cache::forget($overnightCacheKey);
        Cache::forget($dayTourCacheKey);
    }

    /**
     * Clear cache for specific date ranges when bookings change
     */
    public function clearCacheForDateRange(string $startDate, string $endDate): void
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Clear cache for all months that the booking spans
        $current = $start->copy()->startOfMonth();
        while ($current->lte($end->endOfMonth())) {
            $this->clearCalendarCacheForMonth($current->year, $current->month);
            $current->addMonth();
        }
        
        // Also clear dashboard cache
        Cache::forget('dashboard_data');
    }

    /**
     * Clear all cache (nuclear option)
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }
}
