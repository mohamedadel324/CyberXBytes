<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\EventChallangeSubmission;

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
                        $q->where('email', $user->email);
                    });
            })
            ->where(function($query) use ($user) {
                // Keep events where registration is still open
                $query->where('registration_end_date', '>=', now())
                    // OR events where the user is already registered
                    ->orWhereHas('registrations', function($q) use ($user) {
                        $q->where('user_uuid', $user->uuid);
                    })
                    // OR events where the user is invited (even if registration ended)
                    ->orWhereHas('invitations', function($q) use ($user) {
                        $q->where('email', $user->email);
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($event) use ($user) {
                // Check if user is invited
                $isInvited = $event->invitations()->where('email', $user->email)->exists();
                $isRegistered = $event->registrations()->where('user_uuid', $user->uuid)->exists();
                
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
                    'can_register' => $this->isNowBetween($event->registration_start_date, $event->registration_end_date) || $isInvited,
                    'can_form_team' => $this->isNowBetween($event->team_formation_start_date, $event->team_formation_end_date),
                    'is_ended' => now() > $this->convertToUserTimezone($event->end_date),
                    'is_registered' => $isRegistered,
                    'is_invited' => $isInvited,
                ];
            });

        return response()->json(['events' => $events]);
    }

    public function show(Request $request, $uuid)
    {
        $user = $request->user();
        $event = Event::where('uuid', $uuid)
            ->where(function($query) use ($user) {
                $query->where('is_private', 0)
                    ->orWhereHas('invitations', function($q) use ($user) {
                        $q->where('email', $user->email);
                    });
            })
            ->where(function($query) use ($user) {
                // Keep events where registration is still open
                $query->where('registration_end_date', '>=', now())
                    // OR events where the user is already registered
                    ->orWhereHas('registrations', function($q) use ($user) {
                        $q->where('user_uuid', $user->uuid);
                    })
                    // OR events where the user is invited (even if registration ended)
                    ->orWhereHas('invitations', function($q) use ($user) {
                        $q->where('email', $user->email);
                    });
            })
            ->firstOrFail();

        // Check if user is invited
        $isInvited = $user && $event->invitations()->where('email', $user->email)->exists();
        $isRegistered = $user ? $event->registrations()->where('user_uuid', $user->uuid)->exists() : false;

        return response()->json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'description' => $event->description,
                'background_image' => url('storage/' . $event->background_image) ?: $event->background_image,
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
                'can_register' => $this->isNowBetween($event->registration_start_date, $event->registration_end_date) || $isInvited,
                'can_form_team' => $this->isNowBetween($event->team_formation_start_date, $event->team_formation_end_date),
                'is_registered' => $isRegistered,
                'is_ended' => now() > $this->convertToUserTimezone($event->end_date),
                'is_invited' => $isInvited,
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
        $user = $request->user();
        
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
        
        // Check if user is invited
        $isInvited = $user && $event->invitations()->where('email', $user->email)->exists();

        // If registration is not possible (either not started yet or already ended) AND user is not invited
        if (!$canRegister && !$isInvited) {
            return response()->json([
                'event' => [
                    'uuid' => $event->uuid,
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

        // Return full event details for users who can register or are invited
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
                'can_register' => $canRegister || $isInvited,
                'can_form_team' => $this->isNowBetween($event->team_formation_start_date, $event->team_formation_end_date),
                'is_registered' => $isRegistered,
                'is_ended' => now() > $this->convertToUserTimezone($event->end_date),
                'is_invited' => $isInvited,
            ]
        ]);
    }

    public function userEvents(Request $request)
    {
        $user = $request->user();
        
        // Get all events the user has registered for or has been invited to (private events)
        $events = Event::where(function($query) use ($user) {
            // Events the user has registered for
            $query->whereHas('registrations', function($q) use ($user) {
                $q->where('user_uuid', $user->uuid);
            })
            // OR private events they've been invited to
            ->orWhere(function($q) use ($user) {
                $q->where('is_private', 1)
                  ->whereHas('invitations', function($subQ) use ($user) {
                      $subQ->where('email', $user->email);
                  });
            });
        })->get();
        
        $userEvents = [];
        
        foreach ($events as $event) {
            $userEvents[] = [
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
                'is_ended' => now() > $this->convertToUserTimezone($event->end_date),
            ];
        }
        
        return response()->json([
            'events' => $userEvents
        ]);
    }

    public function recentEventActivities($uuid)
    {
        // Find the event by UUID
        $event = Event::where('uuid', $uuid)->firstOrFail();
        
        // Get the 100 most recent solved submissions for this specific event
        $recentSubmissions = EventChallangeSubmission::whereHas('eventChallange', function($query) use ($uuid) {
                $query->where('event_uuid', $uuid);
            })
            ->where('solved', true)
            ->with(['eventChallange', 'eventChallange.event', 'eventChallange.flags', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(150) // Fetch more than needed to ensure we have 100 valid submissions after filtering
            ->get();
        
        $activities = [];
        $count = 0;
        $processedMultipleAllChallenges = []; // Track processed multiple_all challenges
        $freezeTime = $event->freeze_time ? new \DateTime($event->freeze_time) : null;
        
        foreach ($recentSubmissions as $submission) {
            if (!$submission->eventChallange || !$submission->user) {
                continue;
            }
            
            $challenge = $submission->eventChallange;
            $user = $submission->user;
            $submissionFlag = $submission->submission;
            $solvedAt = new \DateTime($submission->created_at);
            
            // Check if submission is after freeze time
            $isAfterFreeze = $freezeTime && $solvedAt > $freezeTime;
            
            // For single-flag challenges or default flag type
            if ($challenge->flag_type !== 'multiple_individual' && $challenge->flag_type !== 'multiple_all') {
                // Check if this was a first blood
                $isFirstBlood = EventChallangeSubmission::where('event_challange_id', $submission->event_challange_id)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                $activities[] = [
                    'user_name' => $user->user_name,
                    'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                    'team_uuid' => \App\Models\EventTeam::where('event_uuid', $uuid)
                        ->whereHas('members', function($query) use ($user) {
                            $query->where('user_uuid', $user->uuid);
                        })->first()?->id,
                    'team_name' => \App\Models\EventTeam::where('event_uuid', $uuid)
                        ->whereHas('members', function($query) use ($user) {
                            $query->where('user_uuid', $user->uuid);
                        })->first()?->name,
                    'challenge_title' => $isAfterFreeze ? '*****' : $challenge->title,
                    'challenge_uuid' => $isAfterFreeze ? '*****' : $challenge->id,
                    'bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? 0 : $challenge->bytes),
                    'is_first_blood' => $isFirstBlood,
                    'first_blood_bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? $challenge->firstBloodBytes : 0),
                    'total_bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? $challenge->firstBloodBytes : $challenge->bytes),
                    'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                ];
                
                $count++;
                if ($count >= 100) break;
            }
            // For multiple_all challenges
            else if ($challenge->flag_type === 'multiple_all') {
                // Generate a unique key for this user-challenge combination
                $key = $user->uuid . '_' . $challenge->id;
                
                // Skip if we've already processed this challenge for this user
                if (in_array($key, $processedMultipleAllChallenges)) {
                    continue;
                }
                
                // Check if all flags for this challenge have been solved by the user
                $totalFlags = $challenge->flags->count();
                if ($totalFlags == 0) {
                    continue; // Skip if there are no flags
                }
                
                // Get all solved flags for this user and challenge
                $userSolvedSubmissions = EventChallangeSubmission::where('user_uuid', $user->uuid)
                    ->where('event_challange_id', $challenge->id)
                    ->where('solved', true)
                    ->get();
                
                $solvedFlags = [];
                $lastSolvedTime = null;
                
                foreach ($userSolvedSubmissions as $solvedSubmission) {
                    if (!in_array($solvedSubmission->submission, $solvedFlags)) {
                        $solvedFlags[] = $solvedSubmission->submission;
                        
                        // Track the most recent submission time
                        if (!$lastSolvedTime || $solvedSubmission->created_at > $lastSolvedTime) {
                            $lastSolvedTime = $solvedSubmission->created_at;
                        }
                    }
                }
                
                // Only show an entry if ALL flags have been solved
                if (count($solvedFlags) == $totalFlags) {
                    // Mark this user-challenge combination as processed
                    $processedMultipleAllChallenges[] = $key;
                    
                    // Check if this was a first blood for the whole challenge (all flags)
                    $isFirstBlood = true;
                    
                    // For a first blood, the user must be the first to solve ALL flags
                    foreach ($challenge->flags as $flag) {
                        $firstSolver = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                            ->where('submission', $flag->flag)
                            ->where('solved', true)
                            ->orderBy('created_at')
                            ->first();
                        
                        if (!$firstSolver || $firstSolver->user_uuid !== $user->uuid) {
                            $isFirstBlood = false;
                            break;
                        }
                    }
                    
                    $solvedAt = new \DateTime($lastSolvedTime);
                    $isAfterFreeze = $freezeTime && $solvedAt > $freezeTime;
                    
                    $activities[] = [
                        'user_name' => $user->user_name,
                        'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                        'team_uuid' => \App\Models\EventTeam::where('event_uuid', $uuid)
                            ->whereHas('members', function($query) use ($user) {
                                $query->where('user_uuid', $user->uuid);
                            })->first()?->id,
                        'team_name' => \App\Models\EventTeam::where('event_uuid', $uuid)
                            ->whereHas('members', function($query) use ($user) {
                                $query->where('user_uuid', $user->uuid);
                            })->first()?->name,
                        'challenge_title' => $isAfterFreeze ? '*****' : $challenge->title,
                        'challenge_uuid' => $isAfterFreeze ? '*****' : $challenge->id,
                        'bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? 0 : $challenge->bytes),
                        'is_first_blood' => $isFirstBlood,
                        'first_blood_bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? $challenge->firstBloodBytes : 0),
                        'total_bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? $challenge->firstBloodBytes : $challenge->bytes),
                        'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                    ];
                    
                    $count++;
                    if ($count >= 100) break;
                }
            }
            // For multiple_individual challenges, list each flag separately
            else if ($challenge->flag_type === 'multiple_individual') {
                // Find the specific flag this submission corresponds to
                $flag = null;
                foreach ($challenge->flags as $challengeFlag) {
                    if ($challengeFlag->flag === $submissionFlag) {
                        $flag = $challengeFlag;
                        break;
                    }
                }
                
                if (!$flag) {
                    continue; // Skip if we can't find the matching flag
                }
                
                // Check if this was a first blood for this specific flag
                $isFirstBlood = EventChallangeSubmission::where('event_challange_id', $submission->event_challange_id)
                    ->where('submission', $flag->flag)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                $isAfterFreeze = $freezeTime && $solvedAt > $freezeTime;
                
                $activities[] = [
                    'user_name' => $user->user_name,
                    'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                    'team_uuid' => \App\Models\EventTeam::where('event_uuid', $uuid)
                        ->whereHas('members', function($query) use ($user) {
                            $query->where('user_uuid', $user->uuid);
                        })->first()?->id,
                    'team_name' => \App\Models\EventTeam::where('event_uuid', $uuid)
                        ->whereHas('members', function($query) use ($user) {
                            $query->where('user_uuid', $user->uuid);
                        })->first()?->name,
                    'challenge_title' => $isAfterFreeze ? '*****' : $challenge->title . ' - ' . ($flag->name ?? 'Flag'),
                    'challenge_uuid' => $isAfterFreeze ? '*****' : $challenge->id,
                    'bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? 0 : $flag->bytes),
                    'is_first_blood' => $isFirstBlood,
                    'first_blood_bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? $flag->firstBloodBytes : 0),
                    'total_bytes' => $isAfterFreeze ? '*****' : ($isFirstBlood ? $flag->firstBloodBytes : $flag->bytes),
                    'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                    'flag_name' => $isAfterFreeze ? '*****' : ($flag->name ?? 'Flag')
                ];
                
                $count++;
                if ($count >= 100) break;
            }
        }
        
        return response()->json([
            'activities' => $activities
        ]);
    }

}