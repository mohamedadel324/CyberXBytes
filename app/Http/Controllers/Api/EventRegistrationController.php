<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        // Check if registration is open using timezone-aware method
        $now = now();
        $startDate = $event->registration_start_date;
        $endDate = $event->registration_end_date;
        
        // Debug information to troubleshoot timezone issues
        Log::info('Registration check', [
            'event_uuid' => $eventUuid,
            'now' => $now->toIso8601String(),
            'registration_start' => $startDate,
            'registration_end' => $endDate,
            'user_timezone' => Auth::user()->time_zone ?? 'UTC'
        ]);
        
        if ($now < $startDate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration has not started yet',
                'now' => $now->toIso8601String(),
                'start_date' => $startDate
            ], 400);
        }

        if ($now > $endDate) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration period has ended'
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
