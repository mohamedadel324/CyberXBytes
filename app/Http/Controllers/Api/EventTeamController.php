<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamJoinSecret;
use App\Models\User;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\EventChallangeSubmission;
use App\Models\EventChallangeFlagSubmission;
use App\Models\EventRegistration;
use App\Models\EventInvitation;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class EventTeamController extends Controller
{
    use HandlesTimezones;

    public function create(Request $request, $eventUuid)
    {
        $event = Event::where('uuid', $eventUuid)->first();
        
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        // Check if user is already in a team for this event
        $existingTeam = EventTeam::where('event_uuid', $eventUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if ($existingTeam) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already in a team for this event'
            ], 400);
        }

        // Check if user is registered for the event
        $isRegistered = EventRegistration::where('event_uuid', $eventUuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->exists();

        // If not registered, check if user was invited to a private event
        if (!$isRegistered && $event->is_private) {
            $isInvited = EventInvitation::where('event_uuid', $eventUuid)
                ->where('email', Auth::user()->email)
                ->exists();
                
            if ($isInvited) {
                // Auto-register the user as they were invited
                EventRegistration::create([
                    'event_uuid' => $eventUuid,
                    'user_uuid' => Auth::user()->uuid,
                    'status' => 'registered'
                ]);
                
                // Mark the invitation as registered
                $invitation = EventInvitation::where('event_uuid', $eventUuid)
                    ->where('email', Auth::user()->email)
                    ->first();
                
                if ($invitation && !$invitation->registered_at) {
                    $invitation->update(['registered_at' => now()]);
                    
                    // Send registration email
                    try {
                        Mail::to(Auth::user()->email)->send(new EventRegistrationMail($event, Auth::user()));
                        Log::info('Auto-registered user when creating team and sent email: ' . Auth::user()->email);
                    } catch (\Exception $e) {
                        Log::error('Failed to send registration email: ' . $e->getMessage());
                    }
                }
                
                $isRegistered = true;
                Log::info('Auto-registered invited user for team creation: ' . Auth::user()->email);
            }
        }

        // If still not registered, they can't create a team
        if (!$isRegistered) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must be registered for this event to create a team'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('event_teams', 'name')->where(function ($query) use ($eventUuid) {
                    return $query->where('event_uuid', $eventUuid);
                })
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($event->team_formation_start_date);
        $endTime = strtotime($event->team_formation_end_date);
        
        // Hard debug output - always log every date check
        \Illuminate\Support\Facades\Log::critical('TEAM TIME CHECK', [
            'event_uuid' => $eventUuid,
            'current_time' => date('Y-m-d H:i:s', $currentTime),
            'current_timestamp' => $currentTime,
            'formation_start' => date('Y-m-d H:i:s', $startTime),
            'formation_start_timestamp' => $startTime,
            'formation_end' => date('Y-m-d H:i:s', $endTime),
            'formation_end_timestamp' => $endTime,
            'time_diff' => $startTime - $currentTime,
            'raw_start_date' => $event->team_formation_start_date,
            'raw_end_date' => $event->team_formation_end_date,
            'allowed' => ($currentTime >= $startTime && $currentTime <= $endTime) ? 'YES' : 'NO'
        ]);
        
        // Only proceed if current time is between start and end times
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }

        // Create team
        $team = EventTeam::create([
            'event_uuid' => $event->uuid,
            'name' => $request->name,
            'leader_uuid' => Auth::user()->uuid,
            'is_locked' => false
        ]);

        // Add leader as member
        $team->members()->attach(Auth::user()->uuid, ['role' => 'leader']);

        return response()->json([
            'status' => 'success',
            'message' => 'Team created successfully',
            'data' => $team
        ]);
    }

    public function join($teamUuid)
    {
        $team = EventTeam::with('event')->where('id', $teamUuid)->first();
        
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found'
            ], 404);
        }

        if ($team->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This team is locked'
            ], 400);
        }

        // Check if user is registered for the event
        $isRegistered = EventRegistration::where('event_uuid', $team->event_uuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->exists();

        // If not registered, check if user was invited to a private event
        if (!$isRegistered && $team->event->is_private) {
            $isInvited = EventInvitation::where('event_uuid', $team->event_uuid)
                ->where('email', Auth::user()->email)
                ->exists();
                
            if ($isInvited) {
                // Auto-register the user as they were invited
                EventRegistration::create([
                    'event_uuid' => $team->event_uuid,
                    'user_uuid' => Auth::user()->uuid,
                    'status' => 'registered'
                ]);
                
                // Mark the invitation as registered
                $invitation = EventInvitation::where('event_uuid', $team->event_uuid)
                    ->where('email', Auth::user()->email)
                    ->first();
                
                if ($invitation && !$invitation->registered_at) {
                    $invitation->update(['registered_at' => now()]);
                    
                    // Send registration email
                    try {
                        Mail::to(Auth::user()->email)->send(new EventRegistrationMail($team->event, Auth::user()));
                        Log::info('Auto-registered user when joining team and sent email: ' . Auth::user()->email);
                    } catch (\Exception $e) {
                        Log::error('Failed to send registration email: ' . $e->getMessage());
                    }
                }
                
                $isRegistered = true;
                Log::info('Auto-registered invited user for team joining: ' . Auth::user()->email);
            }
        }

        // If still not registered, they can't join a team
        if (!$isRegistered) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must be registered for this event to join a team'
            ], 403);
        }

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($team->event->team_formation_start_date);
        $endTime = strtotime($team->event->team_formation_end_date);
        
        // Hard debug output - always log every date check
        \Illuminate\Support\Facades\Log::critical('TEAM JOIN TIME CHECK', [
            'team_uuid' => $teamUuid,
            'team_name' => $team->name,
            'current_time' => date('Y-m-d H:i:s', $currentTime),
            'current_timestamp' => $currentTime,
            'formation_start' => date('Y-m-d H:i:s', $startTime),
            'formation_start_timestamp' => $startTime,
            'formation_end' => date('Y-m-d H:i:s', $endTime),
            'formation_end_timestamp' => $endTime,
            'time_diff' => $startTime - $currentTime,
            'raw_start_date' => $team->event->team_formation_start_date,
            'raw_end_date' => $team->event->team_formation_end_date,
            'allowed' => ($currentTime >= $startTime && $currentTime <= $endTime) ? 'YES' : 'NO'
        ]);
        
        // Only proceed if current time is between start and end times
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }

        // Check if user is already in a team
        $existingTeam = EventTeam::where('event_uuid', $team->event_uuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if ($existingTeam) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already in a team for this event'
            ], 400);
        }

        // Check team size
        if ($team->members()->count() >= $team->event->team_maximum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team is full'
            ], 400);
        }

        // Add member to team
        $team->members()->attach(Auth::user()->uuid, ['role' => 'member']);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully joined the team'
        ]);
    }

    public function leave($teamUuid)
    {
        $team = EventTeam::where('id', $teamUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not a member of this team'
            ], 404);
        }

        if ($team->leader_uuid === Auth::user()->uuid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team leader cannot leave the team'
            ], 400);
        }

        $team->members()->detach(Auth::user()->uuid);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully left the team'
        ]);
    }
    public function checkIfInTeam($eventUuid) {
        $team = EventTeam::with(['members', 'event'])
            ->where('event_uuid', $eventUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not in a team for this event'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'You are in a team for this event'
        ]);
    }
    public function myTeam($eventUuid)
    {
        $team = EventTeam::with(['members', 'event'])
            ->where('event_uuid', $eventUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not in a team for this event'
            ], 404);
        }

        // Get event
        $event = $team->event;
        
        // Check if scoreboard is frozen
        $currentTime = time();
        $isFrozen = false;
        $freezeTime = null;
        
        // Use the event.freeze and event.freeze_time fields (not freeze_scoreboard)
        if ($event->freeze && $event->freeze_time) {
            $freezeTime = strtotime($event->freeze_time);
            $isFrozen = $currentTime >= $freezeTime && !$event->freeze_unlocked;
            
            // Enhanced debug logging with more detailed info
            \Illuminate\Support\Facades\Log::critical('SCOREBOARD FREEZE CHECK', [
                'event_uuid' => $eventUuid,
                'event_name' => $event->name,
                'freeze_flag' => $event->freeze ? 'YES' : 'NO', 
                'freeze_time_raw' => $event->freeze_time,
                'current_time' => date('Y-m-d H:i:s', $currentTime),
                'current_timestamp' => $currentTime,
                'freeze_time' => $freezeTime ? date('Y-m-d H:i:s', $freezeTime) : 'NULL',
                'freeze_timestamp' => $freezeTime,
                'time_diff' => $freezeTime ? ($currentTime - $freezeTime) : 'N/A',
                'is_frozen' => $isFrozen ? 'YES' : 'NO',
                'freeze_unlocked' => $event->freeze_unlocked ? 'YES' : 'NO',
                'user' => Auth::user()->email
            ]);
        }
        
        // Create a cut-off date string formatted for database queries
        $freezeDateStr = $freezeTime ? date('Y-m-d H:i:s', $freezeTime) : null;
        
        // Get all team data including submissions after freeze time to handle masking
        $allTeams = EventTeam::where('event_uuid', $eventUuid)
            ->with(['members.eventSubmissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'members.flagSubmissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallangeFlag.eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }])
            ->get();
            
        // Calculate points for each team
        $teamsWithPoints = $allTeams->map(function($teamItem) use ($isFrozen, $freezeTime) {
            $points = 0;
            $solvedChallenges = [];

            foreach ($teamItem->members as $member) {
                // Process challenge submissions
                foreach ($member->eventSubmissions as $submission) {
                    $isBeforeFreezeTime = !$freezeTime || strtotime($submission->solved_at) < $freezeTime;
                    
                    // If frozen, only count points for submissions before freeze time
                    if ($isFrozen && !$isBeforeFreezeTime) {
                        continue;
                    }
                    
                    if (!in_array($submission->event_challange_id, $solvedChallenges)) {
                        $solvedChallenges[] = $submission->event_challange_id;
                        
                        // Check if this was first blood
                        $firstSolver = EventChallangeSubmission::where('event_challange_id', $submission->event_challange_id)
                            ->where('solved', true)
                            ->orderBy('solved_at')
                            ->first();
                            
                        if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                            $points += $submission->eventChallange->firstBloodBytes ?? 0;
                        } else {
                            $points += $submission->eventChallange->bytes ?? 0;
                        }
                    }
                }

                // Process flag submissions
                foreach ($member->flagSubmissions as $flagSubmission) {
                    $isBeforeFreezeTime = !$freezeTime || strtotime($flagSubmission->solved_at) < $freezeTime;
                    
                    // If frozen, only count points for submissions before freeze time
                    if ($isFrozen && !$isBeforeFreezeTime) {
                        continue;
                    }
                    
                    $challenge = $flagSubmission->eventChallangeFlag->eventChallange;
                    
                    if ($challenge->flag_type === 'multiple_individual') {
                        // For individual flags, each flag gives points
                        $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagSubmission->event_challange_flag_id)
                            ->where('solved', true)
                            ->orderBy('solved_at')
                            ->first();
                            
                        if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                            $points += $flagSubmission->eventChallangeFlag->firstBloodBytes ?? 0;
                        } else {
                            $points += $flagSubmission->eventChallangeFlag->bytes ?? 0;
                        }
                    }
                }
            }

            return [
                'id' => $teamItem->id,
                'points' => $points
            ];
        });
        
        // Sort teams by points and determine rank
        $sortedTeams = $teamsWithPoints->sortByDesc('points')->values();
        $teamRank = $sortedTeams->search(function($item) use ($team) {
            return $item['id'] == $team->id;
        }) + 1; // Add 1 as array indices start at 0
        
        // Get members with their challenge completions and bytes
        $membersData = $team->members->map(function ($member) use ($eventUuid, $isFrozen, $freezeTime, $freezeDateStr) {
            // Get all solved challenges for this member - including after freeze time
            $solvedChallenges = [];
            $totalBytes = 0;
            $totalFirstBloodBytes = 0;
            $totalMaskedBytes = 0;
            
            // Get all challenge completions
            $challengeCompletionsQuery = DB::table('event_challange_submissions')
                ->join('event_challanges', 'event_challange_submissions.event_challange_id', '=', 'event_challanges.id')
                ->where('event_challanges.event_uuid', $eventUuid)
                ->where('event_challange_submissions.user_uuid', $member->uuid)
                ->where('event_challange_submissions.solved', true);
            
            $challengeCompletions = $challengeCompletionsQuery->select(
                'event_challanges.id as challenge_uuid',
                'event_challanges.title as challenge_name',
                'event_challange_submissions.solved_at as completed_at',
                'event_challanges.bytes as normal_bytes',
                'event_challanges.firstBloodBytes as first_blood_bytes'
            )->get();
                
            foreach ($challengeCompletions as $completion) {
                $isBeforeFreezeTime = !$freezeTime || strtotime($completion->completed_at) < $freezeTime;
                
                // Check if this was first blood
                $firstSolverQuery = EventChallangeSubmission::where('event_challange_id', $completion->challenge_uuid)
                    ->where('solved', true)
                    ->orderBy('solved_at');
                
                $firstSolver = $firstSolverQuery->first();
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === $member->uuid;
                    
                // If it's first blood, use first_blood_bytes, otherwise use normal_bytes
                $bytes = $isFirstBlood ? 0 : $completion->normal_bytes;
                $firstBloodBytes = $isFirstBlood ? $completion->first_blood_bytes : 0;
                $totalPoints = $bytes + $firstBloodBytes;
                
                if ($isFrozen && !$isBeforeFreezeTime) {
                    // Mask challenge details after freeze time
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => '********',
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => false,
                        'bytes' => '****',
                        'normal_bytes' => '****',
                        'first_blood_bytes' => '****',
                        'masked' => true
                    ];
                    
                    // Only count bytes for ranking if before freeze time
                    $totalMaskedBytes += $totalPoints;
                } else {
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => $completion->challenge_name,
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => $isFirstBlood,
                        'bytes' => $isFirstBlood ? $firstBloodBytes : $bytes,
                        'normal_bytes' => $bytes,
                        'first_blood_bytes' => $firstBloodBytes,
                        'masked' => false
                    ];
                    
                    $totalBytes += $bytes;
                    $totalFirstBloodBytes += $firstBloodBytes;
                }
            }
            
            // Get flag challenge completions
            $flagCompletionsQuery = DB::table('event_challange_flag_submissions')
                ->join('event_challange_flags', 'event_challange_flag_submissions.event_challange_flag_id', '=', 'event_challange_flags.id')
                ->join('event_challanges', 'event_challange_flags.event_challange_id', '=', 'event_challanges.id')
                ->where('event_challanges.event_uuid', $eventUuid)
                ->where('event_challange_flag_submissions.user_uuid', $member->uuid)
                ->where('event_challange_flag_submissions.solved', true);
            
            $flagCompletions = $flagCompletionsQuery->select(
                'event_challanges.id as challenge_uuid',
                'event_challanges.title as challenge_name',
                'event_challange_flag_submissions.solved_at as completed_at',
                'event_challange_flags.bytes as normal_bytes',
                'event_challange_flags.firstBloodBytes as first_blood_bytes',
                'event_challange_flags.id as flag_id',
                'event_challange_flags.name as flag_name'
            )->get();
                
            foreach ($flagCompletions as $completion) {
                $isBeforeFreezeTime = !$freezeTime || strtotime($completion->completed_at) < $freezeTime;
                
                // Check if this was first blood for the flag
                $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $completion->flag_id)
                    ->where('solved', true)
                    ->orderBy('solved_at');
                
                $firstSolver = $firstSolverQuery->first();
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === $member->uuid;
                    
                // If it's first blood, use first_blood_bytes, otherwise use normal_bytes
                $bytes = $isFirstBlood ? 0 : $completion->normal_bytes;
                $firstBloodBytes = $isFirstBlood ? $completion->first_blood_bytes : 0;
                $totalPoints = $bytes + $firstBloodBytes;
                
                if ($isFrozen && !$isBeforeFreezeTime) {
                    // Mask challenge details after freeze time
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => '******** - ********',
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => false,
                        'bytes' => '****',
                        'normal_bytes' => '****',
                        'first_blood_bytes' => '****',
                        'masked' => true
                    ];
                    
                    // Only count bytes for ranking if before freeze time
                    $totalMaskedBytes += $totalPoints;
                } else {
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => $completion->challenge_name . ' - ' . $completion->flag_name,
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => $isFirstBlood,
                        'bytes' => $isFirstBlood ? $firstBloodBytes : $bytes,
                        'normal_bytes' => $bytes,
                        'first_blood_bytes' => $firstBloodBytes,
                        'masked' => false
                    ];
                    
                    $totalBytes += $bytes;
                    $totalFirstBloodBytes += $firstBloodBytes;
                }
            }
            
            return [
                'uuid' => $member->uuid,
                'username' => $member->user_name,
                'profile_image' => $member->profile_image ? url('storage/' . $member->profile_image) : null,
                'role' => $member->pivot->role,
                'total_bytes' => $totalBytes + $totalFirstBloodBytes,
                'total_masked_bytes' => $totalMaskedBytes,
                'challenge_completions' => $solvedChallenges
            ];
        });
        
        // Get first blood times for all challenges
        $firstBloodTimes = [];
        
        // Regular challenges first blood
        $regularFirstBloodsQuery = DB::table('event_challange_submissions')
            ->join('event_challanges', 'event_challange_submissions.event_challange_id', '=', 'event_challanges.id')
            ->join('users', 'event_challange_submissions.user_uuid', '=', 'users.uuid')
            ->where('event_challanges.event_uuid', $eventUuid)
            ->where('event_challange_submissions.solved', true);
            
        // If frozen, only include submissions before freeze time for first blood
        if ($isFrozen && $freezeDateStr) {
            $regularFirstBloodsQuery->where('event_challange_submissions.solved_at', '<', $freezeDateStr);
        }
        
        $regularFirstBloods = $regularFirstBloodsQuery->select(
            'event_challanges.id as challenge_uuid',
            'event_challanges.title as challenge_name',
            'event_challange_submissions.user_uuid',
            'event_challange_submissions.solved_at as first_blood_time',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY event_challange_submissions.event_challange_id ORDER BY event_challange_submissions.solved_at ASC) as row_num')
        )
        ->orderBy('event_challange_submissions.solved_at')
            ->get();
            
        foreach ($regularFirstBloods as $firstBlood) {
            if ($firstBlood->row_num == 1) {
                $firstBloodTimes[] = [
                    'challenge_uuid' => $firstBlood->challenge_uuid,
                    'challenge_name' => $firstBlood->challenge_name,
                    'user_uuid' => $firstBlood->user_uuid,
                    'first_blood_time' => $firstBlood->first_blood_time
                ];
            }
        }
        
        // Flag challenges first blood
        $flagFirstBloodsQuery = DB::table('event_challange_flag_submissions')
            ->join('event_challange_flags', 'event_challange_flag_submissions.event_challange_flag_id', '=', 'event_challange_flags.id')
            ->join('event_challanges', 'event_challange_flags.event_challange_id', '=', 'event_challanges.id')
            ->join('users', 'event_challange_flag_submissions.user_uuid', '=', 'users.uuid')
            ->where('event_challanges.event_uuid', $eventUuid)
            ->where('event_challange_flag_submissions.solved', true);
            
        // If frozen, only include submissions before freeze time for first blood
        if ($isFrozen && $freezeDateStr) {
            $flagFirstBloodsQuery->where('event_challange_flag_submissions.solved_at', '<', $freezeDateStr);
        }
        
        $flagFirstBloods = $flagFirstBloodsQuery->select(
            'event_challanges.id as challenge_uuid',
            'event_challanges.title as challenge_name',
            'event_challange_flags.name as flag_name',
            'event_challange_flag_submissions.user_uuid',
            'event_challange_flag_submissions.solved_at as first_blood_time',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY event_challange_flag_submissions.event_challange_flag_id ORDER BY event_challange_flag_submissions.solved_at ASC) as row_num')
        )
        ->orderBy('event_challange_flag_submissions.solved_at')
        ->get();
            
        foreach ($flagFirstBloods as $firstBlood) {
            if ($firstBlood->row_num == 1) {
                $firstBloodTimes[] = [
                    'challenge_uuid' => $firstBlood->challenge_uuid,
                    'challenge_name' => $firstBlood->challenge_name . ' - ' . $firstBlood->flag_name,
                    'user_uuid' => $firstBlood->user_uuid,
                    'solved_at' => $firstBlood->first_blood_time
                ];
            }
        }

        // Build the response data
        $responseData = [
            'uuid' => $team->id,
            'name' => $team->name,
            'icon_url' => $team->icon_url,
            'rank' => $teamRank,
            'scoreboard_frozen' => $isFrozen,
            'freeze_time' => $event->freeze_time ? $event->freeze_time->format('Y-m-d H:i:s') : null,
            'event' => [
                'team_minimum_members' => $event->team_minimum_members,
                'team_maximum_members' => $event->team_maximum_members,
            ],
            'members' => $membersData,
            'first_blood_times' => $firstBloodTimes,
            'statistics' => [
                'total_bytes' => $membersData->sum('total_bytes'),
                'total_masked_bytes' => $membersData->sum('total_masked_bytes'),
                'total_first_blood_count' => collect($firstBloodTimes)->filter(function($item) use ($team) {
                    return $team->members->pluck('uuid')->contains($item['user_uuid']);
                })->count(),
                'total_challenges_solved' => collect($membersData)->flatMap(function($member) {
                    return collect($member['challenge_completions'])
                        ->where('masked', false)  // Only count non-masked (before freeze) challenges
                        ->pluck('challenge_uuid')
                        ->unique();
                })->unique()->count(),
                'member_stats' => $membersData->map(function($member) {
                    $normalCompletions = collect($member['challenge_completions'])->where('masked', false);
                    return [
                        'username' => $member['username'],
                        'total_bytes' => $member['total_bytes'],
                        'total_masked_bytes' => $member['total_masked_bytes'],
                        'challenges_solved' => count($member['challenge_completions']),
                        'first_blood_count' => $normalCompletions->where('is_first_blood', true)->count(),
                        'normal_bytes' => $normalCompletions->where('is_first_blood', false)->sum('bytes'),
                        'first_blood_bytes' => $normalCompletions->where('is_first_blood', true)->sum('bytes')
                    ];
                }),
                'top_performing_member' => $membersData->sortByDesc('total_bytes')->first()['username'] ?? null,
            ]
        ];
        
        // If frozen, add a freezing message
        if ($isFrozen) {
            $responseData['freeze_message'] = 'Scoreboard is frozen. Challenge details and points after the freeze time are masked.';
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData
        ]);
    }
    
    public function getTeamById($teamUuid)
    {
        $team = EventTeam::with(['members', 'event'])
            ->where('id', $teamUuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found'
            ], 404);
        }
        
        // Get event
        $event = $team->event;
        $eventUuid = $event->uuid;
        
        // Check if scoreboard is frozen
        $currentTime = time();
        $isFrozen = false;
        $freezeTime = null;
        
        // Use the event.freeze and event.freeze_time fields (not freeze_scoreboard)
        if ($event->freeze && $event->freeze_time) {
            $freezeTime = strtotime($event->freeze_time);
            $isFrozen = $currentTime >= $freezeTime && !$event->freeze_unlocked;
            
            // Enhanced debug logging with more detailed info
            \Illuminate\Support\Facades\Log::critical('SCOREBOARD FREEZE CHECK (getTeamById)', [
                'team_uuid' => $teamUuid,
                'event_uuid' => $eventUuid,
                'event_name' => $event->name,
                'freeze_flag' => $event->freeze ? 'YES' : 'NO', 
                'freeze_time_raw' => $event->freeze_time,
                'current_time' => date('Y-m-d H:i:s', $currentTime),
                'current_timestamp' => $currentTime,
                'freeze_time' => $freezeTime ? date('Y-m-d H:i:s', $freezeTime) : 'NULL',
                'freeze_timestamp' => $freezeTime,
                'time_diff' => $freezeTime ? ($currentTime - $freezeTime) : 'N/A',
                'is_frozen' => $isFrozen ? 'YES' : 'NO',
                'freeze_unlocked' => $event->freeze_unlocked ? 'YES' : 'NO',
                'user_id' => Auth::check() ? Auth::user()->uuid : 'none'
            ]);
        }
        
        // Create a cut-off date string formatted for database queries
        $freezeDateStr = $freezeTime ? date('Y-m-d H:i:s', $freezeTime) : null;
        
        // Get all team data including submissions after freeze time
        $allTeams = EventTeam::where('event_uuid', $eventUuid)
            ->with(['members.eventSubmissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'members.flagSubmissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallangeFlag.eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }])
            ->get();
                
        // Calculate points for each team
        $teamsWithPoints = $allTeams->map(function($teamItem) use ($isFrozen, $freezeTime) {
            $points = 0;
            $solvedChallenges = [];

            foreach ($teamItem->members as $member) {
                // Process challenge submissions
                foreach ($member->eventSubmissions as $submission) {
                    $isBeforeFreezeTime = !$freezeTime || strtotime($submission->solved_at) < $freezeTime;
                    
                    // If frozen, only count points for submissions before freeze time
                    if ($isFrozen && !$isBeforeFreezeTime) {
                        continue;
                    }
                    
                    if (!in_array($submission->event_challange_id, $solvedChallenges)) {
                        $solvedChallenges[] = $submission->event_challange_id;
                        
                        // Check if this was first blood
                        $firstSolver = EventChallangeSubmission::where('event_challange_id', $submission->event_challange_id)
                            ->where('solved', true)
                            ->orderBy('solved_at')
                            ->first();
                            
                        if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                            $points += $submission->eventChallange->firstBloodBytes ?? 0;
                        } else {
                            $points += $submission->eventChallange->bytes ?? 0;
                        }
                    }
                }

                // Process flag submissions
                foreach ($member->flagSubmissions as $flagSubmission) {
                    $isBeforeFreezeTime = !$freezeTime || strtotime($flagSubmission->solved_at) < $freezeTime;
                    
                    // If frozen, only count points for submissions before freeze time
                    if ($isFrozen && !$isBeforeFreezeTime) {
                        continue;
                    }
                    
                    $challenge = $flagSubmission->eventChallangeFlag->eventChallange;
                    
                    if ($challenge->flag_type === 'multiple_individual') {
                        // For individual flags, each flag gives points
                        $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagSubmission->event_challange_flag_id)
                            ->where('solved', true)
                            ->orderBy('solved_at')
                            ->first();
                            
                        if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                            $points += $flagSubmission->eventChallangeFlag->firstBloodBytes ?? 0;
                        } else {
                            $points += $flagSubmission->eventChallangeFlag->bytes ?? 0;
                        }
                    }
                }
            }

            return [
                'id' => $teamItem->id,
                'points' => $points
            ];
        });
        
        // Sort teams by points and determine rank
        $sortedTeams = $teamsWithPoints->sortByDesc('points')->values();
        $teamRank = $sortedTeams->search(function($item) use ($team) {
            return $item['id'] == $team->id;
        }) + 1; // Add 1 as array indices start at 0
        
        // Get members with their challenge completions and bytes
        $membersData = $team->members->map(function ($member) use ($eventUuid, $isFrozen, $freezeTime, $freezeDateStr) {
            // Get all solved challenges for this member - including after freeze time
            $solvedChallenges = [];
            $totalBytes = 0;
            $totalFirstBloodBytes = 0;
            $totalMaskedBytes = 0;
            
            // Get all challenge completions
            $challengeCompletionsQuery = DB::table('event_challange_submissions')
                ->join('event_challanges', 'event_challange_submissions.event_challange_id', '=', 'event_challanges.id')
                ->where('event_challanges.event_uuid', $eventUuid)
                ->where('event_challange_submissions.user_uuid', $member->uuid)
                ->where('event_challange_submissions.solved', true);
            
            $challengeCompletions = $challengeCompletionsQuery->select(
                'event_challanges.id as challenge_uuid',
                'event_challanges.title as challenge_name',
                'event_challange_submissions.solved_at as completed_at',
                'event_challanges.bytes as normal_bytes',
                'event_challanges.firstBloodBytes as first_blood_bytes'
            )->get();
                
            foreach ($challengeCompletions as $completion) {
                $isBeforeFreezeTime = !$freezeTime || strtotime($completion->completed_at) < $freezeTime;
                
                // Check if this was first blood
                $firstSolverQuery = EventChallangeSubmission::where('event_challange_id', $completion->challenge_uuid)
                    ->where('solved', true)
                    ->orderBy('solved_at');
                
                $firstSolver = $firstSolverQuery->first();
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === $member->uuid;
                    
                // If it's first blood, use first_blood_bytes, otherwise use normal_bytes
                $bytes = $isFirstBlood ? 0 : $completion->normal_bytes;
                $firstBloodBytes = $isFirstBlood ? $completion->first_blood_bytes : 0;
                $totalPoints = $bytes + $firstBloodBytes;
                
                if ($isFrozen && !$isBeforeFreezeTime) {
                    // Mask challenge details after freeze time
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => '********',
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => false,
                        'bytes' => '****',
                        'normal_bytes' => '****',
                        'first_blood_bytes' => '****',
                        'masked' => true
                    ];
                    
                    // Only count bytes for ranking if before freeze time
                    $totalMaskedBytes += $totalPoints;
                } else {
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => $completion->challenge_name,
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => $isFirstBlood,
                        'bytes' => $isFirstBlood ? $firstBloodBytes : $bytes,
                        'normal_bytes' => $bytes,
                        'first_blood_bytes' => $firstBloodBytes,
                        'masked' => false
                    ];
                    
                    $totalBytes += $bytes;
                    $totalFirstBloodBytes += $firstBloodBytes;
                }
            }
            
            // Get flag challenge completions
            $flagCompletionsQuery = DB::table('event_challange_flag_submissions')
                ->join('event_challange_flags', 'event_challange_flag_submissions.event_challange_flag_id', '=', 'event_challange_flags.id')
                ->join('event_challanges', 'event_challange_flags.event_challange_id', '=', 'event_challanges.id')
                ->where('event_challanges.event_uuid', $eventUuid)
                ->where('event_challange_flag_submissions.user_uuid', $member->uuid)
                ->where('event_challange_flag_submissions.solved', true);
            
            $flagCompletions = $flagCompletionsQuery->select(
                'event_challanges.id as challenge_uuid',
                'event_challanges.title as challenge_name',
                'event_challange_flag_submissions.solved_at as completed_at',
                'event_challange_flags.bytes as normal_bytes',
                'event_challange_flags.firstBloodBytes as first_blood_bytes',
                'event_challange_flags.id as flag_id',
                'event_challange_flags.name as flag_name'
            )->get();
                
            foreach ($flagCompletions as $completion) {
                $isBeforeFreezeTime = !$freezeTime || strtotime($completion->completed_at) < $freezeTime;
                
                // Check if this was first blood for the flag
                $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $completion->flag_id)
                    ->where('solved', true)
                    ->orderBy('solved_at');
                
                $firstSolver = $firstSolverQuery->first();
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === $member->uuid;
                    
                // If it's first blood, use first_blood_bytes, otherwise use normal_bytes
                $bytes = $isFirstBlood ? 0 : $completion->normal_bytes;
                $firstBloodBytes = $isFirstBlood ? $completion->first_blood_bytes : 0;
                $totalPoints = $bytes + $firstBloodBytes;
                
                if ($isFrozen && !$isBeforeFreezeTime) {
                    // Mask challenge details after freeze time
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => '******** - ********',
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => false,
                        'bytes' => '****',
                        'normal_bytes' => '****',
                        'first_blood_bytes' => '****',
                        'masked' => true
                    ];
                    
                    // Only count bytes for ranking if before freeze time
                    $totalMaskedBytes += $totalPoints;
                } else {
                    $solvedChallenges[] = [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => $completion->challenge_name . ' - ' . $completion->flag_name,
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => $isFirstBlood,
                        'bytes' => $isFirstBlood ? $firstBloodBytes : $bytes,
                        'normal_bytes' => $bytes,
                        'first_blood_bytes' => $firstBloodBytes,
                        'masked' => false
                    ];
                    
                    $totalBytes += $bytes;
                    $totalFirstBloodBytes += $firstBloodBytes;
                }
            }
            
            return [
                'uuid' => $member->uuid,
                'username' => $member->user_name,
                'profile_image' => $member->profile_image ? url('storage/' . $member->profile_image) : null,
                'role' => $member->pivot->role,
                'total_bytes' => $totalBytes + $totalFirstBloodBytes,
                'total_masked_bytes' => $totalMaskedBytes,
                'challenge_completions' => $solvedChallenges
            ];
        });
        
        // Get first blood times for all challenges
        $firstBloodTimes = [];
        
        // Regular challenges first blood
        $regularFirstBloodsQuery = DB::table('event_challange_submissions')
            ->join('event_challanges', 'event_challange_submissions.event_challange_id', '=', 'event_challanges.id')
            ->join('users', 'event_challange_submissions.user_uuid', '=', 'users.uuid')
            ->where('event_challanges.event_uuid', $eventUuid)
            ->where('event_challange_submissions.solved', true);
            
        // If frozen, only include submissions before freeze time for first blood
        if ($isFrozen && $freezeDateStr) {
            $regularFirstBloodsQuery->where('event_challange_submissions.solved_at', '<', $freezeDateStr);
        }
        
        $regularFirstBloods = $regularFirstBloodsQuery->select(
            'event_challanges.id as challenge_uuid',
            'event_challanges.title as challenge_name',
            'event_challange_submissions.user_uuid',
            'event_challange_submissions.solved_at as first_blood_time',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY event_challange_submissions.event_challange_id ORDER BY event_challange_submissions.solved_at ASC) as row_num')
        )
        ->orderBy('event_challange_submissions.solved_at')
            ->get();
            
        foreach ($regularFirstBloods as $firstBlood) {
            if ($firstBlood->row_num == 1) {
                $firstBloodTimes[] = [
                    'challenge_uuid' => $firstBlood->challenge_uuid,
                    'challenge_name' => $firstBlood->challenge_name,
                    'user_uuid' => $firstBlood->user_uuid,
                    'first_blood_time' => $firstBlood->first_blood_time
                ];
            }
        }
        
        // Flag challenges first blood
        $flagFirstBloodsQuery = DB::table('event_challange_flag_submissions')
            ->join('event_challange_flags', 'event_challange_flag_submissions.event_challange_flag_id', '=', 'event_challange_flags.id')
            ->join('event_challanges', 'event_challange_flags.event_challange_id', '=', 'event_challanges.id')
            ->join('users', 'event_challange_flag_submissions.user_uuid', '=', 'users.uuid')
            ->where('event_challanges.event_uuid', $eventUuid)
            ->where('event_challange_flag_submissions.solved', true);
            
        // If frozen, only include submissions before freeze time for first blood
        if ($isFrozen && $freezeDateStr) {
            $flagFirstBloodsQuery->where('event_challange_flag_submissions.solved_at', '<', $freezeDateStr);
        }
        
        $flagFirstBloods = $flagFirstBloodsQuery->select(
            'event_challanges.id as challenge_uuid',
            'event_challanges.title as challenge_name',
            'event_challange_flags.name as flag_name',
            'event_challange_flag_submissions.user_uuid',
            'event_challange_flag_submissions.solved_at as first_blood_time',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY event_challange_flag_submissions.event_challange_flag_id ORDER BY event_challange_flag_submissions.solved_at ASC) as row_num')
        )
        ->orderBy('event_challange_flag_submissions.solved_at')
        ->get();
            
        foreach ($flagFirstBloods as $firstBlood) {
            if ($firstBlood->row_num == 1) {
                $firstBloodTimes[] = [
                    'challenge_uuid' => $firstBlood->challenge_uuid,
                    'challenge_name' => $firstBlood->challenge_name . ' - ' . $firstBlood->flag_name,
                    'user_uuid' => $firstBlood->user_uuid,
                    'solved_at' => $firstBlood->first_blood_time
                ];
            }
        }

        // Build the response data
        $responseData = [
            'uuid' => $team->id,
            'name' => $team->name,
            'icon_url' => $team->icon_url,
            'is_locked' => $team->is_locked,
            'rank' => $teamRank,
            'scoreboard_frozen' => $isFrozen,
            'freeze_time' => $event->freeze_time ? $event->freeze_time->format('Y-m-d H:i:s') : null,
            'event' => [
                'team_minimum_members' => $event->team_minimum_members,
                'team_maximum_members' => $event->team_maximum_members,
            ],
            'members' => $membersData,
            'first_blood_times' => $firstBloodTimes,
            'statistics' => [
                'total_bytes' => $membersData->sum('total_bytes'),
                'total_masked_bytes' => $membersData->sum('total_masked_bytes'),
                'total_first_blood_count' => collect($firstBloodTimes)->filter(function($item) use ($team) {
                    return $team->members->pluck('uuid')->contains($item['user_uuid']);
                })->count(),
                'total_challenges_solved' => collect($membersData)->flatMap(function($member) {
                    return collect($member['challenge_completions'])
                        ->where('masked', false)  // Only count non-masked (before freeze) challenges
                        ->pluck('challenge_uuid')
                        ->unique();
                })->unique()->count(),
                'member_stats' => $membersData->map(function($member) {
                    $normalCompletions = collect($member['challenge_completions'])->where('masked', false);
                    return [
                        'username' => $member['username'],
                        'total_bytes' => $member['total_bytes'],
                        'total_masked_bytes' => $member['total_masked_bytes'],
                        'challenges_solved' => count($member['challenge_completions']),
                        'first_blood_count' => $normalCompletions->where('is_first_blood', true)->count(),
                        'normal_bytes' => $normalCompletions->where('is_first_blood', false)->sum('bytes'),
                        'first_blood_bytes' => $normalCompletions->where('is_first_blood', true)->sum('bytes')
                    ];
                }),
                'top_performing_member' => $membersData->sortByDesc('total_bytes')->first()['username'] ?? null,
            ]
        ];
        
        // If frozen, add a freezing message
        if ($isFrozen) {
            $responseData['freeze_message'] = 'Scoreboard is frozen. Challenge details and points after the freeze time are masked.';
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData
        ]);
    }

    public function listTeams($eventUuid)
    {
        $teams = EventTeam::with(['members', 'leader'])
            ->where('event_uuid', $eventUuid)
            ->get()
            ->map(function ($team) {
                return [
                    'uuid' => $team->id,
                    'name' => $team->name,
                    'leader' => $team->leader->only(['uuid', 'user_name']),
                    'member_count' => $team->members->count(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $teams
        ]);
    }

 

    public function removeMember(Request $request, $teamUuid)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Find the team and verify the current user is the leader
        $team = EventTeam::with('event')->where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        // Check if the event has already started
        $currentTime = time(); // Current server Unix timestamp
        $eventStartTime = strtotime($team->event->start_date);

        
        // Prevent removing members after the event has started
        if ($currentTime >= $eventStartTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot remove team members after the event has started',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'event_start_time' => date('Y-m-d H:i:s', $eventStartTime)
                ]
            ], 400);
        }

        // Find the user to remove
        $userToRemove = User::where('user_name', $request->username)->first();

        if (!$userToRemove) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Check if the user is actually in the team
        $isMember = $team->members()
            ->where('user_uuid', $userToRemove->uuid)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'This user is not a member of your team'
            ], 400);
        }

        // Don't allow removing the leader
        if ($userToRemove->uuid === $team->leader_uuid) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot remove the team leader'
            ], 400);
        }

        // Remove the member
        $team->members()->detach($userToRemove->uuid);

        return response()->json([
            'status' => 'success',
            'message' => 'Member removed successfully',
            'data' => [
                'removed_member' => [
                    'username' => $userToRemove->user_name,
                    'name' => $userToRemove->name
                ],
                'remaining_members' => $team->members->map(function ($member) {
                    return [
                        'username' => $member->user_name,
                        'name' => $member->name,
                        'role' => $member->pivot->role
                    ];
                })
            ]
        ]);
    }

    public function generateJoinSecret($teamUuid)
    {
        // Find the team and verify the current user is the leader
        $team = EventTeam::with(['members', 'event'])->where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($team->event->team_formation_start_date);
        $endTime = strtotime($team->event->team_formation_end_date);
        $eventStartTime = strtotime($team->event->start_date);
        
        
        // Only proceed if current time is between start and end times of team formation
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }
        
        // Check if the event has already started
        if ($currentTime >= $eventStartTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot generate join secrets after the event has started',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'event_start_time' => date('Y-m-d H:i:s', $eventStartTime)
                ]
            ], 400);
        }

        // Check if team is full
        if ($team->members->count() >= $team->event->team_maximum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team is full'
            ], 400);
        }

        // Generate a unique 16-character secret
        $maxAttempts = 5;
        $attempt = 0;
        $characterPool = 'cxb1$';
        
        do {
            if ($attempt >= $maxAttempts) {
                // If we've tried too many times with the current character pool, expand it
                $characterPool .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#%^&*';
                $attempt = 0; // Reset attempt counter with new character pool
            }
            
            $secret = substr(str_shuffle(str_repeat($characterPool, 16)), 0, 16);
            $attempt++;
        } while (EventTeamJoinSecret::where('secret', $secret)->exists());

        // Create the join secret
        $joinSecret = EventTeamJoinSecret::create([
            'team_uuid' => $team->id,
            'secret' => $secret
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Join secret generated successfully',
            'data' => [
                'secret' => $secret,
                'remaining_slots' => $team->event->team_maximum_members - $team->members->count()
            ]
        ]);
    }

    public function joinWithSecret(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'secret' => 'required|string|size:16',
            'team_name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Find the unused secret and verify team name
        $joinSecret = EventTeamJoinSecret::with('team.event')
            ->where('secret', $request->secret)
            ->where('used', false)
            ->whereHas('team', function($query) use ($request) {
                $query->where('name', $request->team_name);
            })
            ->first();

        if (!$joinSecret) {
            // Check if the team name exists but doesn't match the secret
            $teamExists = EventTeam::where('name', $request->team_name)
                ->whereHas('joinSecrets', function($q) use($request) {
                    $q->where('secret', '!=', $request->secret);
                })
                ->exists();
                
            if ($teamExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Team name exists but the provided secret is incorrect'
                ], 400);
            }
                
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid secret, team name, or secret already used'
            ], 400);
        }

        $team = $joinSecret->team;
        $event = $team->event;

        // Check if user is registered for the event
        $isRegistered = EventRegistration::where('event_uuid', $event->uuid)
            ->where('user_uuid', Auth::user()->uuid)
            ->exists();

        // If not registered, check if user was invited to a private event
        if (!$isRegistered && $event->is_private) {
            $isInvited = EventInvitation::where('event_uuid', $event->uuid)
                ->where('email', Auth::user()->email)
                ->exists();
                
            if ($isInvited) {
                // Auto-register the user as they were invited
                EventRegistration::create([
                    'event_uuid' => $event->uuid,
                    'user_uuid' => Auth::user()->uuid,
                    'status' => 'registered'
                ]);
                
                // Mark the invitation as registered
                $invitation = EventInvitation::where('event_uuid', $event->uuid)
                    ->where('email', Auth::user()->email)
                    ->first();
                
                if ($invitation && !$invitation->registered_at) {
                    $invitation->update(['registered_at' => now()]);
                    
                    // Send registration email
                    try {
                        Mail::to(Auth::user()->email)->send(new EventRegistrationMail($event, Auth::user()));
                        Log::info('Auto-registered user when joining team with secret and sent email: ' . Auth::user()->email);
                    } catch (\Exception $e) {
                        Log::error('Failed to send registration email: ' . $e->getMessage());
                    }
                }
                
                $isRegistered = true;
                Log::info('Auto-registered invited user for team joining with secret: ' . Auth::user()->email);
            }
        }

        // If still not registered, they can't join a team
        if (!$isRegistered) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must be registered for this event to join a team'
            ], 403);
        }

        // Check if user is already in a team for this event
        $existingTeam = EventTeam::where('event_uuid', $team->event_uuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if ($existingTeam) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already in a team for this event'
            ], 400);
        }

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($team->event->team_formation_start_date);
        $endTime = strtotime($team->event->team_formation_end_date);
        
        // Hard debug output - always log every date check
        \Illuminate\Support\Facades\Log::critical('JOIN WITH SECRET TIME CHECK', [
            'team_uuid' => $team->id,
            'team_name' => $team->name,
            'user_uuid' => Auth::user()->uuid,
            'current_time' => date('Y-m-d H:i:s', $currentTime),
            'current_timestamp' => $currentTime,
            'formation_start' => date('Y-m-d H:i:s', $startTime),
            'formation_start_timestamp' => $startTime,
            'formation_end' => date('Y-m-d H:i:s', $endTime),
            'formation_end_timestamp' => $endTime,
            'time_diff' => $startTime - $currentTime,
            'raw_start_date' => $team->event->team_formation_start_date,
            'raw_end_date' => $team->event->team_formation_end_date,
            'allowed' => ($currentTime >= $startTime && $currentTime <= $endTime) ? 'YES' : 'NO'
        ]);
        
        // Only proceed if current time is between start and end times
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }
        
        if ($team->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This team is locked'
            ], 400);
        }

        // Check team size
        if ($team->members()->count() >= $team->event->team_maximum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team is full'
            ], 400);
        }

        // Add member to team
        $team->members()->attach(Auth::user()->uuid, ['role' => 'member']);

        // Mark secret as used
        $joinSecret->update([
            'used' => true,
            'used_by_uuid' => Auth::user()->uuid,
            'used_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully joined the team',
            'data' => [
                'team' => [
                    'name' => $team->name,
                    'member_count' => $team->members()->count()
                ]
            ]
        ]);
    }

    public function listJoinSecrets($teamUuid)
    {
        // Find the team and verify the current user is the leader
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        $secrets = EventTeamJoinSecret::where('team_uuid', $team->id)
            ->with('usedBy:uuid,user_name,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($secret) {
                return [
                    'secret' => $secret->secret,
                    'created_at' => $secret->created_at->format('c'),
                    'status' => $secret->used ? 'used' : 'available',
                    'used_by' => $secret->used ? [
                        'username' => $secret->usedBy->user_name,
                        'name' => $secret->usedBy->name
                    ] : null,
                    'used_at' => $secret->used_at ? $secret->used_at->format('c') : null
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $secrets
        ]);
    }
    /**
     * @requestMediaType multipart/form-data
     */
    public function updateTeam(Request $request, $teamUuid)
    {
        // Get the team first to use in validation
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('event_teams', 'name')
                    ->where(function ($query) use ($team) {
                        return $query->where('event_uuid', $team->event_uuid);
                    })
                    ->ignore($team->id)
            ],
            'icon' => 'nullable|image|max:5848' // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            if ($team->icon) {
                Storage::delete($team->icon);
            }

            // Store new icon
            $path = $request->file('icon')->store('team-icons', 'public');
            $team->icon = $path;
        }

        if ($request->filled('name')) {
            $team->name = $request->name;
        }

        $team->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Team updated successfully',
            'data' => [
                'name' => $team->name,
                'icon_url' => $team->icon_url
            ]
        ]);
    }
}
