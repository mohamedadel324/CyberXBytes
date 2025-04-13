<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

trait HandlesTimezones
{
    /**
     * Convert a date to the user's timezone
     *
     * @param mixed $date The date to convert
     * @return Carbon|null
     */
    protected function toUserTimezone($date)
    {
        if (!$date) {
            return null;
        }

        $userTimezone = Auth::user()->time_zone ?? 'UTC';
        
        if ($date instanceof Carbon) {
            return $date->copy()->setTimezone($userTimezone);
        }
        
        return Carbon::parse($date)->setTimezone($userTimezone);
    }

    /**
     * Format a date in the user's timezone
     *
     * @param mixed $date The date to format
     * @param string $format The format to use
     * @return string|null
     */
    protected function formatInUserTimezone($date, $format = 'c')
    {
        $convertedDate = $this->toUserTimezone($date);
        
        if (!$convertedDate) {
            return null;
        }
        
        return $convertedDate->format($format);
    }

    /**
     * Check if the current time is between two dates in the user's timezone
     *
     * @param mixed $startDate The start date
     * @param mixed $endDate The end date
     * @return bool
     */
    protected function isNowBetween($startDate, $endDate)
    {
        $userTimezone = Auth::user()->time_zone ?? 'UTC';
        $now = Carbon::now($userTimezone);
        
        $start = $this->toUserTimezone($startDate);
        $end = $this->toUserTimezone($endDate);
        
        return $now->between($start, $end);
    }
} 