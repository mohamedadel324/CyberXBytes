<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventInvitation;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventRegistrationMail;

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

        // Check if the event is private and if the user is invited
        if ($event->is_private) {
            $userEmail = Auth::user()->email;
            $isInvited = $event->isUserInvited($userEmail);
            
            if (!$isInvited) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This is a private event and you are not invited'
                ], 403);
            }
            
            // Check if there's an invitation that needs to be marked as registered
            $invitation = EventInvitation::where('event_uuid', $event->uuid)
                ->where('email', $userEmail)
                ->whereNull('registered_at')
                ->first();
                
            if ($invitation) {
                $invitation->update(['registered_at' => now()]);
            }
        }

        // STRICT TIME CHECK WITH DIRECT SERVER COMPARISON
        // Get current server time and registration times in UTC to avoid any timezone confusion
        $currentServerTime = time(); // Current server Unix timestamp
        $userTimezone = Auth::user()->time_zone ?? 'UTC';
        
        // Get raw registration times from database and convert to unix timestamps
        $startDateObj = new \DateTime($event->registration_start_date);
        $endDateObj = new \DateTime($event->registration_end_date);
        $startTimestamp = $startDateObj->getTimestamp(); 
        $endTimestamp = $endDateObj->getTimestamp();
        
        // For private events with invitations, skip the time check
        if (!$event->is_private) {
            // ------------ START HARDCODED TIME CHECK --------------
            // Direct server-side time validation with no reliance on Carbon or timezone conversions
            if ($currentServerTime < $startTimestamp) {
                $secondsRemaining = $startTimestamp - $currentServerTime;
                $minutesRemaining = ceil($secondsRemaining / 60);
                
                // Return comprehensive error with detailed time information
                return response()->json([
                    'status' => 'error',
                    'message' => 'Registration has not started yet. Try again in ' . $minutesRemaining . ' minutes.',
                    'time_check_details' => [
                        'current_server_time' => date('Y-m-d H:i:s', $currentServerTime),
                        'registration_start_time' => date('Y-m-d H:i:s', $startTimestamp),
                        'seconds_remaining' => $secondsRemaining,
                        'minutes_remaining' => $minutesRemaining,
                        'raw_server_timestamp' => $currentServerTime,
                        'raw_start_timestamp' => $startTimestamp,
                        'timezone_info' => [
                            'server_timezone' => date_default_timezone_get(),
                            'user_timezone' => $userTimezone,
                            'database_raw_date' => $event->registration_start_date
                        ]
                    ]
                ], 400);
            }
            
            // Check if registration period has ended
            if ($currentServerTime > $endTimestamp) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Registration period has ended',
                    'time_check_details' => [
                        'current_server_time' => date('Y-m-d H:i:s', $currentServerTime),
                        'registration_end_time' => date('Y-m-d H:i:s', $endTimestamp)
                    ]
                ], 400);
            }
            // ------------ END HARDCODED TIME CHECK --------------
        }

        // Check if user is already registered
        $existingRegistration = EventRegistration::where('event_uuid', $event->uuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'status' => 'success',
                'message' => 'You are already registered for this event'
            ], 200);
        }

        // Create registration
        $registration = EventRegistration::create([
            'event_uuid' => $event->uuid,
            'user_uuid' => Auth::user()->uuid,
            'status' => 'registered'
        ]);


        Mail::to(Auth::user()->email)->send(new EventRegistrationMail($event, Auth::user()));

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully registered for the event',
            'data' => $registration,
            'time_verification' => [
                'registration_confirmed_at' => date('Y-m-d H:i:s'),
                'server_timestamp' => time(),
                'registration_allowed_from' => date('Y-m-d H:i:s', $startTimestamp)
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
