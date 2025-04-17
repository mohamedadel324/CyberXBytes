<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EventRegistrationController extends Controller
{
    use HandlesTimezones;

    public function register($eventUuid)
    {
        $event = Event::where('uuid', $eventUuid)->first();
        
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        // Setup timezone handling
        $userTimezone = Auth::user()->time_zone ?? 'UTC';
        $now = now();
        $nowInUserTz = $now->copy()->setTimezone($userTimezone);
        
        // Get dates from database
        $startDate = $event->registration_start_date;
        $endDate = $event->registration_end_date;
        
        // Ensure dates are Carbon instances with correct timezone
        $startDateInUserTz = $startDate instanceof Carbon ? 
            $startDate->copy()->setTimezone($userTimezone) : 
            Carbon::parse($startDate)->setTimezone($userTimezone);
            
        $endDateInUserTz = $endDate instanceof Carbon ? 
            $endDate->copy()->setTimezone($userTimezone) : 
            Carbon::parse($endDate)->setTimezone($userTimezone);
        
        // Log registration attempt with exact timestamps
        Log::info('Event Registration Attempt', [
            'event_uuid' => $eventUuid,
            'now_exact' => $nowInUserTz->toDateTimeString(),
            'start_exact' => $startDateInUserTz->toDateTimeString(),
            'end_exact' => $endDateInUserTz->toDateTimeString(),
            'now_timestamp' => $nowInUserTz->timestamp,
            'start_timestamp' => $startDateInUserTz->timestamp,
            'timezone' => $userTimezone
        ]);
        
        // STRICT TIME CHECK: Check if current time is before registration start time
        if ($nowInUserTz->timestamp < $startDateInUserTz->timestamp) {
            $secondsUntilStart = $startDateInUserTz->timestamp - $nowInUserTz->timestamp;
            $minutesUntilStart = ceil($secondsUntilStart / 60);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Registration has not started yet. Please try again in ' . $minutesUntilStart . ' minutes.',
                'current_time' => $nowInUserTz->toDateTimeString(),
                'start_time' => $startDateInUserTz->toDateTimeString(),
                'seconds_remaining' => $secondsUntilStart
            ], 400);
        }

        // STRICT TIME CHECK: Check if current time is after registration end time
        if ($nowInUserTz->timestamp > $endDateInUserTz->timestamp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration period has ended',
                'current_time' => $nowInUserTz->toDateTimeString(),
                'end_time' => $endDateInUserTz->toDateTimeString()
            ], 400);
        }

        // Check if user is already registered
        $existingRegistration = EventRegistration::where('event_uuid', $event->uuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already registered for this event'
            ], 400);
        }

        // Create registration
        $registration = EventRegistration::create([
            'event_uuid' => $event->uuid,
            'user_uuid' => Auth::user()->uuid,
            'status' => 'registered'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully registered for the event',
            'data' => $registration
        ]);
    }
    public function checkRegistration($eventUuid)
    {
        $event = Event::where('uuid', $eventUuid)->first();
        
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        $registration = EventRegistration::where('event_uuid', $event->uuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->first();
            
        if (!$registration) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not registered for this event'
            ], 403);
        }
            
        return response()->json([
            'status' => 'success',
            'message' => 'You are registered for this event'
        ]);
    }

    public function unregister($eventUuid)
    {
        $registration = EventRegistration::where('event_uuid', $eventUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->first();

        if (!$registration) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not registered for this event'
            ], 404);
        }

        $registration->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully unregistered from the event'
        ]);
    }

    public function myRegistrations()
    {
        $registrations = EventRegistration::with('event')
            ->where('user_uuid', Auth::user()->uuid)
            ->get()
            ->map(function ($registration) {
                return [
                    'uuid' => $registration->uuid,
                    'event' => [
                        'uuid' => $registration->event->uuid,
                        'title' => $registration->event->title,
                        'description' => $registration->event->description,
                        'image' => url('storage/' . $registration->event->image),
                        'registration_start_date' => $this->formatInUserTimezone($registration->event->registration_start_date),
                        'registration_end_date' => $this->formatInUserTimezone($registration->event->registration_end_date),
                        'team_formation_start_date' => $this->formatInUserTimezone($registration->event->team_formation_start_date),
                        'team_formation_end_date' => $this->formatInUserTimezone($registration->event->team_formation_end_date),
                        'start_date' => $this->formatInUserTimezone($registration->event->start_date),
                        'end_date' => $this->formatInUserTimezone($registration->event->end_date),
                    ],
                    'status' => $registration->status,
                    'registered_at' => $this->formatInUserTimezone($registration->created_at)
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $registrations
        ]);
    }
}
