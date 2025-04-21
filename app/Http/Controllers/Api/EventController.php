<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    use HandlesTimezones;

    public function index(Request $request)
    {
        $user = $request->user();
        $events = Event::
            all()
            ->map(function ($event) {
                return [
                    'uuid' => $event->uuid,
                    'title' => $event->title,
                    'description' => $event->description,
                    'image' => url('storage/' . $event->image) ?: $event->image,
                    'is_main' => $event->is_main,
                    'registration_start_date' => $this->formatInUserTimezone($event->registration_start_date),
                    'registration_end_date' => $this->formatInUserTimezone($event->registration_end_date),
                    'team_formation_start_date' => $this->formatInUserTimezone($event->team_formation_start_date),
                    'team_formation_end_date' => $this->formatInUserTimezone($event->team_formation_end_date),
                    'start_date' => $this->formatInUserTimezone($event->start_date),
                    'end_date' => $this->formatInUserTimezone($event->end_date),
                    'requires_team' => $event->requires_team,
                    'team_minimum_members' => $event->team_minimum_members,
                    'team_maximum_members' => $event->team_maximum_members,
                    'can_register' => $this->isNowBetween($event->registration_start_date, $event->registration_end_date),
                    'can_form_team' => $this->isNowBetween($event->team_formation_start_date, $event->team_formation_end_date),
                ];
            });

        return response()->json(['events' => $events]);
    }

    public function show(Request $request, $uuid)
    {
        $user = $request->user();
        $event = Event::where('uuid', $uuid)
            ->where(function($query) use ($request) {
                $query->where('is_private', 0)
                    ->orWhereHas('invitations', function($q) use ($request) {
                        $q->where('email', $request->user()->email);
                    });
            })
            ->where(function($query) use ($user) {
                $query->where('registration_start_date', '>', now())
                    ->orWhereHas('registrations', function($q) use ($user) {
                        $q->where('user_uuid', $user->uuid)
                          ->whereColumn('event_uuid', 'events.uuid');
                    });
            })
            ->firstOrFail();

        return response()->json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'description' => $event->description,
                'image' => url('storage/' . $event->image) ?: $event->image,
                'is_main' => $event->is_main,
                'registration_start_date' => $this->formatInUserTimezone($event->registration_start_date),
                'registration_end_date' => $this->formatInUserTimezone($event->registration_end_date),
                'team_formation_start_date' => $this->formatInUserTimezone($event->team_formation_start_date),
                'team_formation_end_date' => $this->formatInUserTimezone($event->team_formation_end_date),
                'start_date' => $this->formatInUserTimezone($event->start_date),
                'end_date' => $this->formatInUserTimezone($event->end_date),
                'requires_team' => $event->requires_team,
                'team_minimum_members' => $event->team_minimum_members,
                'team_maximum_members' => $event->team_maximum_members,
                'can_register' => $this->isNowBetween($event->registration_start_date, $event->registration_end_date),
                'can_form_team' => $this->isNowBetween($event->team_formation_start_date, $event->team_formation_end_date),
            ]
        ]);
    }
    public function checkIfEventStarted($eventUuid) {
        $event = Event::where('uuid', $eventUuid)->first();

        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        $now = now();
        $status = [];

        // Check if before registration
        if ($now < $event->registration_start_date) {
            $status = [
                'phase' => 'pre_registration',
                'message' => 'Registration has not started yet',
                'next_phase' => 'registration',
                'next_phase_starts_in' => $this->formatInUserTimezone($event->registration_start_date),
                'seconds_until_next_phase' => $now->diffInSeconds($event->registration_start_date),
                'current_phase_times' => [
                    'registration_start' => $this->formatInUserTimezone($event->registration_start_date),
                    'registration_end' => $this->formatInUserTimezone($event->registration_end_date),
                    'team_formation_start' => $this->formatInUserTimezone($event->team_formation_start_date),
                    'team_formation_end' => $this->formatInUserTimezone($event->team_formation_end_date),
                    'event_start' => $this->formatInUserTimezone($event->start_date),
                    'event_end' => $this->formatInUserTimezone($event->end_date)
                ]
            ];
        }
        // Check if in registration period
        else if ($now >= $event->registration_start_date && $now <= $event->registration_end_date) {
            $status = [
                'phase' => 'registration',
                'message' => 'Registration is open',
                'next_phase' => 'team_formation',
                'next_phase_starts_in' => $this->formatInUserTimezone($event->team_formation_start_date),
                'seconds_until_next_phase' => $now->diffInSeconds($event->team_formation_start_date),
                'current_phase_times' => [
                    'registration_start' => $this->formatInUserTimezone($event->registration_start_date),
                    'registration_end' => $this->formatInUserTimezone($event->registration_end_date),
                    'team_formation_start' => $this->formatInUserTimezone($event->team_formation_start_date),
                    'team_formation_end' => $this->formatInUserTimezone($event->team_formation_end_date),
                    'event_start' => $this->formatInUserTimezone($event->start_date),
                    'event_end' => $this->formatInUserTimezone($event->end_date)
                ]
            ];
        }
        // Check if in team formation period
        else if ($now >= $event->team_formation_start_date && $now <= $event->team_formation_end_date) {
            $status = [
                'phase' => 'team_formation',
                'message' => 'Team formation is open',
                'next_phase' => 'event_start',
                'next_phase_starts_in' => $this->formatInUserTimezone($event->start_date),
                'seconds_until_next_phase' => $now->diffInSeconds($event->start_date),
                'current_phase_times' => [
                    'registration_start' => $this->formatInUserTimezone($event->registration_start_date),
                    'registration_end' => $this->formatInUserTimezone($event->registration_end_date),
                    'team_formation_start' => $this->formatInUserTimezone($event->team_formation_start_date),
                    'team_formation_end' => $this->formatInUserTimezone($event->team_formation_end_date),
                    'event_start' => $this->formatInUserTimezone($event->start_date),
                    'event_end' => $this->formatInUserTimezone($event->end_date)
                ]
            ];
        }
        // Check if event has started but not ended
        else if ($now >= $event->start_date && $now <= $event->end_date) {
            $status = [
                'phase' => 'event_active',
                'message' => 'Event is currently running',
                'next_phase' => 'event_end',
                'next_phase_starts_in' => $this->formatInUserTimezone($event->end_date),
                'seconds_until_next_phase' => $now->diffInSeconds($event->end_date),
                'current_phase_times' => [
                    'registration_start' => $this->formatInUserTimezone($event->registration_start_date),
                    'registration_end' => $this->formatInUserTimezone($event->registration_end_date),
                    'team_formation_start' => $this->formatInUserTimezone($event->team_formation_start_date),
                    'team_formation_end' => $this->formatInUserTimezone($event->team_formation_end_date),
                    'event_start' => $this->formatInUserTimezone($event->start_date),
                    'event_end' => $this->formatInUserTimezone($event->end_date)
                ]
            ];
        }
        // Event has ended
        else if ($now > $event->end_date) {
            $status = [
                'phase' => 'ended',
                'message' => 'Event has ended',
                'next_phase' => null,
                'next_phase_starts_in' => null,
                'seconds_until_next_phase' => null,
                'current_phase_times' => [
                    'registration_start' => $this->formatInUserTimezone($event->registration_start_date),
                    'registration_end' => $this->formatInUserTimezone($event->registration_end_date),
                    'team_formation_start' => $this->formatInUserTimezone($event->team_formation_start_date),
                    'team_formation_end' => $this->formatInUserTimezone($event->team_formation_end_date),
                    'event_start' => $this->formatInUserTimezone($event->start_date),
                    'event_end' => $this->formatInUserTimezone($event->end_date)
                ]
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $status
        ]);
    }

    public function mainEvent(Request $request)
    {
        $event = Event::main()->first();
        
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'No main event found'
            ], 404);
        }

        // Check if event has ended
        if ($event->end_date < now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has ended'
            ], 404);
        }

        // Calculate can_register status
        $canRegister = $this->isNowBetween($event->registration_start_date, $event->registration_end_date);

        // Determine if the user is registered
        $isRegistered = Auth::check() && $event->registrations()->where('user_uuid', Auth::user()->uuid)->exists();

        // If registration is not possible (either not started yet or already ended)
        if (!$canRegister) {
            return response()->json([
                'event' => [
                    'title' => $event->title,
                    'description' => $event->description,
                    'image' => url('storage/' . $event->image) ?: $event->image,
                    'status' => 'under_working',
                    'registration_start_date' => $this->formatInUserTimezone($event->registration_start_date),
                'registration_end_date' => $this->formatInUserTimezone($event->registration_end_date),
                'team_formation_start_date' => $this->formatInUserTimezone($event->team_formation_start_date),
                'team_formation_end_date' => $this->formatInUserTimezone($event->team_formation_end_date),
                'start_date' => $this->formatInUserTimezone($event->start_date),
                'end_date' => $this->formatInUserTimezone($event->end_date),
                    'team_minimum_members' => $event->team_minimum_members,
                    'team_maximum_members' => $event->team_maximum_members,
                    'is_registered' => $isRegistered,
                ]
            ]);
        }

        // Otherwise return full event details
        return response()->json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'description' => $event->description,
                'image' => url('storage/' . $event->image) ?: $event->image,
                'is_main' => $event->is_main,
                'registration_start_date' => $this->formatInUserTimezone($event->registration_start_date),
                'registration_end_date' => $this->formatInUserTimezone($event->registration_end_date),
                'team_formation_start_date' => $this->formatInUserTimezone($event->team_formation_start_date),
                'team_formation_end_date' => $this->formatInUserTimezone($event->team_formation_end_date),
                'start_date' => $this->formatInUserTimezone($event->start_date),
                'end_date' => $this->formatInUserTimezone($event->end_date),
                'requires_team' => $event->requires_team,
                'team_minimum_members' => $event->team_minimum_members,
                'team_maximum_members' => $event->team_maximum_members,
                'can_register' => $canRegister,
                'can_form_team' => $this->isNowBetween($event->team_formation_start_date, $event->team_formation_end_date),
                'is_registered' => $isRegistered,
            ]
        ]);
    }
}