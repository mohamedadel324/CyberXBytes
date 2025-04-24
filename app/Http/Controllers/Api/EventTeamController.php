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

class EventTeamController extends Controller
{
    use HandlesTimezones;

    public function create(Request $request, $eventUuid)
    {
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:event_teams,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $event = Event::where('uuid', $eventUuid)->first();
        
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
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
        $team = EventTeam::where('uuid', $teamUuid)
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
        
        // Get team ranking - first get all teams with their points
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
        $teamsWithPoints = $allTeams->map(function($teamItem) {
            $points = 0;
            $solvedChallenges = [];

            foreach ($teamItem->members as $member) {
                // Process challenge submissions
                foreach ($member->eventSubmissions as $submission) {
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
        $membersData = $team->members->map(function ($member) use ($eventUuid) {
            // Get all solved challenges for this member
            $solvedChallenges = [];
            $totalBytes = 0;
            $totalFirstBloodBytes = 0;
            
            // Get regular challenge completions
            $challengeCompletions = DB::table('event_challange_submissions')
                ->join('event_challanges', 'event_challange_submissions.event_challange_id', '=', 'event_challanges.id')
                ->where('event_challanges.event_uuid', $eventUuid)
                ->where('event_challange_submissions.user_uuid', $member->uuid)
                ->where('event_challange_submissions.solved', true)
                ->select(
                    'event_challanges.id as challenge_uuid',
                    'event_challanges.title as challenge_name',
                    'event_challange_submissions.solved_at as completed_at',
                    'event_challanges.bytes as normal_bytes',
                    'event_challanges.firstBloodBytes as first_blood_bytes'
                )
                ->get();
                
            foreach ($challengeCompletions as $completion) {
                // Check if this was first blood
                $isFirstBlood = EventChallangeSubmission::where('event_challange_id', $completion->challenge_uuid)
                    ->where('solved', true)
                    ->orderBy('solved_at')
                    ->first()->user_uuid === $member->uuid;
                    
                // If it's first blood, use first_blood_bytes, otherwise use normal_bytes
                $bytes = $isFirstBlood ? 0 : $completion->normal_bytes;
                $firstBloodBytes = $isFirstBlood ? $completion->first_blood_bytes : 0;
                
                $solvedChallenges[] = [
                    'challenge_uuid' => $completion->challenge_uuid,
                    'challenge_name' => $completion->challenge_name,
                    'completed_at' => $completion->completed_at,
                    'is_first_blood' => $isFirstBlood,
                    'bytes' => $isFirstBlood ? $firstBloodBytes : $bytes,
                    'normal_bytes' => $bytes,
                    'first_blood_bytes' => $firstBloodBytes
                ];
                
                $totalBytes += $bytes;
                $totalFirstBloodBytes += $firstBloodBytes;
            }
            
            // Get flag challenge completions
            $flagCompletions = DB::table('event_challange_flag_submissions')
                ->join('event_challange_flags', 'event_challange_flag_submissions.event_challange_flag_id', '=', 'event_challange_flags.id')
                ->join('event_challanges', 'event_challange_flags.event_challange_id', '=', 'event_challanges.id')
                ->where('event_challanges.event_uuid', $eventUuid)
                ->where('event_challange_flag_submissions.user_uuid', $member->uuid)
                ->where('event_challange_flag_submissions.solved', true)
                ->select(
                    'event_challanges.id as challenge_uuid',
                    'event_challanges.title as challenge_name',
                    'event_challange_flag_submissions.solved_at as completed_at',
                    'event_challange_flags.bytes as normal_bytes',
                    'event_challange_flags.firstBloodBytes as first_blood_bytes',
                    'event_challange_flags.id as flag_id',
                    'event_challange_flags.name as flag_name'
                )
                ->get();
                
            foreach ($flagCompletions as $completion) {
                // Check if this was first blood for the flag
                $isFirstBlood = EventChallangeFlagSubmission::where('event_challange_flag_id', $completion->flag_id)
                    ->where('solved', true)
                    ->orderBy('solved_at')
                    ->first()->user_uuid === $member->uuid;
                    
                // If it's first blood, use first_blood_bytes, otherwise use normal_bytes
                $bytes = $isFirstBlood ? 0 : $completion->normal_bytes;
                $firstBloodBytes = $isFirstBlood ? $completion->first_blood_bytes : 0;
                
                $solvedChallenges[] = [
                    'challenge_uuid' => $completion->challenge_uuid,
                    'challenge_name' => $completion->challenge_name . ' - ' . $completion->flag_name,
                    'completed_at' => $completion->completed_at,
                    'is_first_blood' => $isFirstBlood,
                    'bytes' => $isFirstBlood ? $firstBloodBytes : $bytes,
                    'normal_bytes' => $bytes,
                    'first_blood_bytes' => $firstBloodBytes
                ];
                
                $totalBytes += $bytes;
                $totalFirstBloodBytes += $firstBloodBytes;
            }
            
            return [
                'uuid' => $member->uuid,
                'username' => $member->user_name,
                'profile_image' => $member->profile_image ? url('storage/' . $member->profile_image) : null,
                'role' => $member->pivot->role,
                'total_bytes' => $totalBytes + $totalFirstBloodBytes,
                'challenge_completions' => $solvedChallenges
            ];
        });
        
        // Get first blood times for all challenges
        $firstBloodTimes = [];
        
        // Regular challenges first blood
        $regularFirstBloods = DB::table('event_challange_submissions')
            ->join('event_challanges', 'event_challange_submissions.event_challange_id', '=', 'event_challanges.id')
            ->join('users', 'event_challange_submissions.user_uuid', '=', 'users.uuid')
            ->where('event_challanges.event_uuid', $eventUuid)
            ->where('event_challange_submissions.solved', true)
            ->select(
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
        $flagFirstBloods = DB::table('event_challange_flag_submissions')
            ->join('event_challange_flags', 'event_challange_flag_submissions.event_challange_flag_id', '=', 'event_challange_flags.id')
            ->join('event_challanges', 'event_challange_flags.event_challange_id', '=', 'event_challanges.id')
            ->join('users', 'event_challange_flag_submissions.user_uuid', '=', 'users.uuid')
            ->where('event_challanges.event_uuid', $eventUuid)
            ->where('event_challange_flag_submissions.solved', true)
            ->select(
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
                    'first_blood_time' => $firstBlood->first_blood_time
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'uuid' => $team->id,
                'name' => $team->name,
                'icon_url' => $team->icon ? url('storage/' . $team->icon) : null,
                'is_locked' => $team->is_locked,
                'rank' => $teamRank,
                'event' => [
                    'team_minimum_members' => $event->team_minimum_members,
                    'team_maximum_members' => $event->team_maximum_members,
                ],
                'members' => $membersData,
                'first_blood_times' => $firstBloodTimes,
                'statistics' => [
                    'total_bytes' => $membersData->sum('total_bytes'),
                    'total_first_blood_count' => collect($firstBloodTimes)->filter(function($item) use ($team) {
                        return $team->members->pluck('uuid')->contains($item['user_uuid']);
                    })->count(),
                    'total_challenges_solved' => collect($membersData)->flatMap(function($member) {
                        return collect($member['challenge_completions'])->pluck('challenge_uuid')->unique();
                    })->unique()->count(),
                    'member_stats' => $membersData->map(function($member) {
                        return [
                            'username' => $member['username'],
                            'total_bytes' => $member['total_bytes'],
                            'challenges_solved' => count($member['challenge_completions']),
                            'first_blood_count' => collect($member['challenge_completions'])->where('is_first_blood', true)->count(),
                            'normal_bytes' => collect($member['challenge_completions'])->where('is_first_blood', false)->sum('bytes'),
                            'first_blood_bytes' => collect($member['challenge_completions'])->where('is_first_blood', true)->sum('bytes')
                        ];
                    }),
                    'top_performing_member' => $membersData->sortByDesc('total_bytes')->first()['username'] ?? null,
                ]
            ]
        ]);
    }
    
    public function getTeamChallengeData($eventUuid)
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
        
        // Get event challenges
        $event = $team->event;
        
        // Get team rank
        $teamRank = EventTeam::where('event_uuid', $eventUuid)
            ->join('event_team_scoreboard', 'event_teams.id', '=', 'event_team_scoreboard.team_uuid')
            ->orderBy('event_team_scoreboard.points', 'desc')
            ->pluck('event_teams.id')
            ->search($team->id) + 1;
        
        // Get members with their challenge completions
        $members = $team->members->map(function ($member) use ($eventUuid) {
            // Get all challenge completions for this member
            $completions = DB::table('event_challenge_completions')
                ->join('event_challenges', 'event_challenge_completions.challenge_uuid', '=', 'event_challenges.uuid')
                ->where('event_challenges.event_uuid', $eventUuid)
                ->where('event_challenge_completions.user_uuid', $member->uuid)
                ->select(
                    'event_challenges.uuid as challenge_uuid',
                    'event_challenges.name as challenge_name',
                    'event_challenge_completions.completed_at',
                    'event_challenge_completions.is_first_blood',
                    'event_challenges.points as normal_bytes',
                    'event_challenges.first_blood_points as first_blood_bytes'
                )
                ->get();
                
            // Calculate total bytes
            $totalBytes = $completions->sum(function ($completion) {
                return $completion->normal_bytes + ($completion->is_first_blood ? $completion->first_blood_bytes : 0);
            });
            
            return [
                'uuid' => $member->uuid,
                'username' => $member->user_name,
                'profile_image' => $member->profile_image ? url('storage/' . $member->profile_image) : null,
                'role' => $member->pivot->role,
                'total_bytes' => $totalBytes,
                'challenge_completions' => $completions->map(function ($completion) {
                    return [
                        'challenge_uuid' => $completion->challenge_uuid,
                        'challenge_name' => $completion->challenge_name,
                        'completed_at' => $completion->completed_at,
                        'is_first_blood' => $completion->is_first_blood,
                        'bytes' => $completion->normal_bytes + ($completion->is_first_blood ? $completion->first_blood_bytes : 0),
                        'normal_bytes' => $completion->normal_bytes,
                        'first_blood_bytes' => $completion->is_first_blood ? $completion->first_blood_bytes : 0
                    ];
                })
            ];
        });
        
        // Get first blood times for all challenges
        $firstBloodTimes = DB::table('event_challenge_completions')
            ->join('event_challenges', 'event_challenge_completions.challenge_uuid', '=', 'event_challenges.uuid')
            ->where('event_challenges.event_uuid', $eventUuid)
            ->where('event_challenge_completions.is_first_blood', true)
            ->select(
                'event_challenges.uuid as challenge_uuid',
                'event_challenges.name as challenge_name',
                'event_challenge_completions.user_uuid',
                'event_challenge_completions.completed_at as first_blood_time'
            )
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'team' => [
                    'uuid' => $team->id,
                    'name' => $team->name,
                    'is_locked' => $team->is_locked,
                    'rank' => $teamRank,
                ],
                'event' => [
                    'uuid' => $event->uuid,
                    'name' => $event->name,
                    'team_minimum_members' => $event->team_minimum_members,
                    'team_maximum_members' => $event->team_maximum_members,
                ],
                'members' => $members,
                'first_blood_times' => $firstBloodTimes
            ]
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
                    'is_locked' => $team->is_locked,
                    'leader' => $team->leader->only(['uuid', 'user_name']),
                    'member_count' => $team->members->count(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $teams
        ]);
    }

    public function lock($teamUuid)
    {
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        if ($team->members()->count() < $team->event->team_minimum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team does not have minimum required members'
            ], 400);
        }

        $team->update(['is_locked' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Team has been locked'
        ]);
    }

    public function unlock($teamUuid)
    {
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        $team->update(['is_locked' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Team has been unlocked'
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
                'message' => 'Team is already at maximum capacity'
            ], 400);
        }

        // Generate a unique 16-character secret
        do {
            $secret = Str::random(16);
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
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid secret, team name, or secret already used'
            ], 400);
        }

        $team = $joinSecret->team;

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
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'icon' => 'nullable|image|max:2048' // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

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
