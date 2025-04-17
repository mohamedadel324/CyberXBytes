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

        // Enhanced debug information
        $userTimezone = Auth::user()->time_zone ?? 'UTC';
        $now = now()->setTimezone($userTimezone);
        $rawStartDate = $event->registration_start_date;
        $rawEndDate = $event->registration_end_date;
        
        // Convert to Carbon instances explicitly
        $startDate = $rawStartDate ? Carbon::parse($rawStartDate)->setTimezone($userTimezone) : null;
        $endDate = $rawEndDate ? Carbon::parse($rawEndDate)->setTimezone($userTimezone) : null;
        
        // Extensive debug information
        Log::info('Event Registration Debug', [
            'event_uuid' => $eventUuid,
            'event_title' => $event->title,
            'now' => $now->toIso8601String(),
            'now_formatted' => $now->format('Y-m-d H:i:s'),
            'raw_start_date' => $rawStartDate,
            'raw_end_date' => $rawEndDate,
            'parsed_start_date' => $startDate ? $startDate->toIso8601String() : null,
            'parsed_start_date_formatted' => $startDate ? $startDate->format('Y-m-d H:i:s') : null,
            'parsed_end_date' => $endDate ? $endDate->toIso8601String() : null,
            'user_timezone' => $userTimezone,
            'now_timestamp' => $now->timestamp,
            'start_timestamp' => $startDate ? $startDate->timestamp : null,
            'comparison_result' => $startDate ? ($now->timestamp < $startDate->timestamp ? 'now is before start' : 'now is after start') : 'no start date'
        ]);
        
        // Use timestamp comparison for more reliable results
        if ($startDate && $now->timestamp < $startDate->timestamp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration has not started yet',
                'debug_info' => [
                    'now' => $now->format('Y-m-d H:i:s'),
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'now_timestamp' => $now->timestamp,
                    'start_timestamp' => $startDate->timestamp,
                    'timezone' => $userTimezone,
                    'raw_start_date' => $rawStartDate
                ]
            ], 400);
        }

        if ($endDate && $now->timestamp > $endDate->timestamp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration period has ended',
                'debug_info' => [
                    'now' => $now->format('Y-m-d H:i:s'),
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                    'timezone' => $userTimezone
                ]
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
