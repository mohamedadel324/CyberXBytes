<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $events = Event::query()
            ->where(function($query) use ($user) {
                $query->where('is_private', 0)
                    ->orWhereHas('invitations', function($q) use ($user) {
                        $q->where('email', $user->email);
                    });
            })
            ->where('registration_start_date', '<=', now())
            ->get()
            ->map(function ($event) {
                return [
                    'uuid' => $event->uuid,
                    'title' => $event->title,
                    'description' => $event->description,
                    'image' => url('storage/' . $event->image) ?: $event->image,
                    'registration_start_date' => $event->registration_start_date->format('c'),
                    'registration_end_date' => $event->registration_end_date->format('c'),
                    'team_formation_start_date' => $event->team_formation_start_date->format('c'),
                    'team_formation_end_date' => $event->team_formation_end_date->format('c'),
                    'start_date' => $event->start_date->format('c'),
                    'end_date' => $event->end_date->format('c'),
                    'requires_team' => $event->requires_team,
                    'team_minimum_members' => $event->team_minimum_members,
                    'team_maximum_members' => $event->team_maximum_members,
                    'can_register' => now()->between($event->registration_start_date, $event->registration_end_date),
                    'can_form_team' => now()->between($event->team_formation_start_date, $event->team_formation_end_date),
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
                'registration_start_date' => $event->registration_start_date->format('c'),
                'registration_end_date' => $event->registration_end_date->format('c'),
                'team_formation_start_date' => $event->team_formation_start_date->format('c'),
                'team_formation_end_date' => $event->team_formation_end_date->format('c'),
                'start_date' => $event->start_date->format('c'),
                'end_date' => $event->end_date->format('c'),
                'requires_team' => $event->requires_team,
                'team_minimum_members' => $event->team_minimum_members,
                'team_maximum_members' => $event->team_maximum_members,
                'can_register' => now()->between($event->registration_start_date, $event->registration_end_date),
                'can_form_team' => now()->between($event->team_formation_start_date, $event->team_formation_end_date),
            ]
        ]);
    }
}
