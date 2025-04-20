<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

trait HandlesTimezones
{
    /**
     * Convert a datetime to user's timezone
     *
     * @param mixed $datetime The datetime to convert (string, Carbon instance, or null)
     * @return \Carbon\Carbon|null
     */
    protected function convertToUserTimezone($datetime)
    {
        if (!$datetime) {
            return null;
        }

        // Convert to Carbon if not already
        if (!($datetime instanceof Carbon)) {
            $datetime = Carbon::parse($datetime);
        }

        // Get user's timezone, default to UTC if not set
        $userTimezone = Auth::user()->time_zone ?? 'UTC';

        // Convert to user's timezone
        return $datetime->setTimezone($userTimezone);
    }

    /**
     * Format a datetime in user's timezone
     *
     * @param mixed $datetime The datetime to format (string, Carbon instance, or null)
     * @param string $format The format to use (default: Y-m-d H:i:s)
     * @return string|null
     */
    protected function formatInUserTimezone($datetime, $format = 'Y-m-d H:i:s')
    {
        $converted = $this->convertToUserTimezone($datetime);
        return $converted ? $converted->format($format) : null;
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
        $userTimezone = Auth::user()->timezone ?? 'UTC';
        $now = Carbon::now($userTimezone);
        
        $start = $this->convertToUserTimezone($startDate);
        $end = $this->convertToUserTimezone($endDate);
        
        return $now->between($start, $end);
    }
} 