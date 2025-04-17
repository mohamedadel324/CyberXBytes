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
        $events = Event::query()
            ->where(function($query) use ($user) {
                $query->where('is_private', 0)
                    ->orWhereHas('invitations', function($q) use ($user) {
                        $q->where('email', $user->email)
                          ->where('status', 'accepted');
                    });
            })
            ->where(function($query) use ($user) {
                $query->where('registration_start_date', '<=', now())
                    ->orWhereHas('registrations', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->orWhereHas('invitations', function($q) use ($user) {
                        $q->where('email', $user->email)
                          ->where('status', 'accepted');
                    });
            })
            ->where('end_date', '>', now())
            ->get()
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
        $event = Event::where('uuid', $uuid)
            ->where(function($query) use ($request) {
                $query->where('is_private', 0)
                    ->orWhereHas('invitations', function($q) use ($request) {
                        $q->where('email', $request->user()->email);
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

        if ($event->start_date > now()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Event has not started yet'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Event has started'
        ], 200);
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

        // If registration is not possible (either not started yet or already ended)
        if (!$canRegister) {
            return response()->json([
                'event' => [
                    'uuid' => $event->uuid,
                    'title' => $event->title,
                    'description' => $event->description,
                    'image' => url('storage/' . $event->image) ?: $event->image,
                    'status' => 'under_working',
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
            ]
        ]);
    }
}