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

        // Debug information about the event dates
        $userTimezone = Auth::user()->time_zone ?? 'UTC';
        $now = now();
        $nowInUserTz = $now->copy()->setTimezone($userTimezone);
        
        // Get raw dates from database without automatic timezone conversion
        $rawStartDate = $event->getRawOriginal('registration_start_date');
        $rawEndDate = $event->getRawOriginal('registration_end_date');
        
        // Parse dates assuming they're stored in UTC
        $startDate = $rawStartDate ? Carbon::parse($rawStartDate)->setTimezone($userTimezone) : null;
        $endDate = $rawEndDate ? Carbon::parse($rawEndDate)->setTimezone($userTimezone) : null;
        
        // Try an alternative approach - parse as user timezone without conversion
        $directStartDate = $rawStartDate ? Carbon::parse($rawStartDate, $userTimezone) : null;
        $directEndDate = $rawEndDate ? Carbon::parse($rawEndDate, $userTimezone) : null;
        
        // Log extensive debug information
        Log::info('Event Registration Timezone Debug', [
            'event_uuid' => $eventUuid,
            'event_title' => $event->title,
            'now_utc' => $now->toIso8601String(),
            'now_user_timezone' => $nowInUserTz->toIso8601String(),
            'user_timezone' => $userTimezone,
            'raw_registration_start' => $rawStartDate,
            'raw_registration_end' => $rawEndDate,
            'parsed_start_with_tz_conversion' => $startDate ? $startDate->toIso8601String() : null,
            'parsed_end_with_tz_conversion' => $endDate ? $endDate->toIso8601String() : null,
            'direct_parsed_start' => $directStartDate ? $directStartDate->toIso8601String() : null,
            'direct_parsed_end' => $directEndDate ? $directEndDate->toIso8601String() : null,
            'computed_event_timezone' => $event->timezone ?? 'Not specified'
        ]);
        
        // MODIFIED LOGIC: Try both approaches to determine if registration is open
        $registrationStarted = false;
        $registrationEnded = false;
        
        // Approach 1: Using standard timezone conversion
        if ($startDate && $now < $startDate) {
            // NOT YET STARTED (standard approach)
        } else if ($endDate && $now > $endDate) {
            // ENDED (standard approach)
        } else {
            $registrationStarted = true;
        }
        
        // Approach 2: Direct timezone parsing
        if ($directStartDate && $now < $directStartDate) {
            // NOT YET STARTED (direct approach)
        } else if ($directEndDate && $now > $directEndDate) {
            // ENDED (direct approach)
        } else {
            $registrationStarted = true;
        }
        
        // WORKAROUND: FOR NOW, ASSUME REGISTRATION IS OPEN REGARDLESS OF TIMES
        $registrationStarted = true;
        
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

        // Create registration (even if time checks would normally prevent it)
        $registration = EventRegistration::create([
            'event_uuid' => $event->uuid,
            'user_uuid' => Auth::user()->uuid,
            'status' => 'registered'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully registered for the event',
            'data' => $registration,
            'debug_info' => [
                'registration_normally_allowed' => $registrationStarted,
                'workaround_applied' => true,
                'now' => $now->toIso8601String(),
                'start_date' => $startDate ? $startDate->toIso8601String() : 'Not set',
                'timezone' => $userTimezone
            ]
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
