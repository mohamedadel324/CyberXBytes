<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventChallange;
use App\Models\EventChallangeSubmission;
use App\Models\EventChallangeFlagSubmission;
use App\Models\EventTeam;
use App\Models\User;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Event;
use App\Models\Team;

class EventChallengeController extends Controller
{
    use HandlesTimezones;

    /**
     * Validates event and team requirements before allowing access to APIs
     *
     * @param string $eventUuid
     * @param bool $ignoreEventEnded Whether to bypass the event end date check
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function validateEventAndTeamRequirements($eventUuid, $ignoreEventEnded = false)
    {
        $event = Event::where('uuid', $eventUuid)->first();
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Convert event dates to user timezone
        $userNow = $this->convertToUserTimezone(now());
        $eventStartDate = $this->convertToUserTimezone($event->start_date);
        $eventEndDate = $this->convertToUserTimezone($event->end_date);

        // Check if event has started
        if ($userNow->lt($eventStartDate)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has not started yet',
                'data' => [
                    'start_date' => $eventStartDate->format('Y-m-d H:i:s'),
                ]
            ], 403);
        }

        // Check if event has ended, but only if we're not ignoring this check
        if (!$ignoreEventEnded && $userNow->gt($eventEndDate)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has ended',
                'data' => [
                    'end_date' => $eventEndDate->format('Y-m-d H:i:s')
                ]
            ], 403);
        }

        // Check if user is part of any team in this event
        $team = EventTeam::where('event_uuid', $eventUuid)
            ->where(function($query) use ($user) {
                $query->where('leader_uuid', $user->uuid)
                    ->orWhereHas('members', function($q) use ($user) {
                        $q->where('uuid', $user->uuid);
                    });
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team in this event'
            ], 403);
        }

        return null;
    }

    public function listChallenges($eventUuid)
    {
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        $challenges = EventChallange::with(['category:uuid,icon', 'flags'])
            ->where('event_uuid', $eventUuid)
            ->get();

        $challenges->each(function ($challenge) {
            // Add id to the response
            $challenge->challenge_id = $challenge->id;
            
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // Get solved count for the challenge
            $solvedCount = $challenge->submissions()->where('solved', true)->count();
            $challenge->solved_count = $solvedCount;
            
            // Get first blood information
            $firstBlood = null;
            if ($solvedCount > 0) {
                $firstSolver = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                if ($firstSolver) {
                    $firstBloodUser = User::where('uuid', $firstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                    if ($firstBloodUser) {
                        $firstBlood = [
                            'user_name' => $firstBloodUser->user_name,
                            'profile_image' => $firstBloodUser->profile_image ? asset('storage/' . $firstBloodUser->profile_image) : null,
                            'solved_at' => $firstSolver->created_at,
                        ];
                    }
                }
            }
            $challenge->first_blood = $firstBlood;
            
            // For single flag type
            if ($challenge->flag_type === 'single') {
                $challenge->flag_data = [
                    'flag' => $challenge->flag,
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                    'solved_count' => $solvedCount,
                ];
            }
            // For multiple flag types
            else if ($challenge->flags) {
                $flagsData = [];
                
                foreach ($challenge->flags as $flag) {
                    // Get solved count for this flag
                    $flagSolvedCount = $challenge->submissions()
                        ->where('submission', $flag->flag)
                        ->where('solved', true)
                        ->count();
                    
                    // Get first blood for this flag
                    $flagFirstBlood = null;
                    if ($flagSolvedCount > 0) {
                        $flagFirstSolver = $challenge->submissions()
                            ->where('submission', $flag->flag)
                            ->where('solved', true)
                            ->orderBy('solved_at', 'asc')
                            ->first();
                        
                        if ($flagFirstSolver) {
                            $flagFirstBloodUser = User::where('uuid', $flagFirstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                            if ($flagFirstBloodUser) {
                                $flagFirstBlood = [
                                    'user_name' => $flagFirstBloodUser->user_name,
                                    'profile_image' => $flagFirstBloodUser->profile_image ? asset('storage/' . $flagFirstBloodUser->profile_image) : null,
                                    'solved_at' => $flagFirstSolver->solved_at,
                                ];
                            }
                        }
                    }
                    
                    $flagsData[] = [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                        'solved_count' => $flagSolvedCount,
                        'first_blood' => $flagFirstBlood,
                    ];
                }
                
                $challenge->flags_data = $flagsData;
                $challenge->flags_count = $challenge->flags->count();
                
                // For multiple_all, add total bytes and first blood bytes
                if ($challenge->flag_type === 'multiple_all') {
                    $challenge->total_bytes = $challenge->bytes;
                    $challenge->total_first_blood_bytes = $challenge->firstBloodBytes;
                }
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
    }

    public function submit(Request $request, $eventChallengeUuid)
    {
        $validator = Validator::make($request->all(), [
            'submission' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $challenge = EventChallange::with(['event', 'solvedBy', 'flags'])->find($eventChallengeUuid);
        
        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get user's team for this event - already verified in validateEventAndTeamRequirements
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('members', function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        // Handle single flag type
        if ($challenge->flag_type === 'single') {
            // Check if user has already solved this challenge
            if ($challenge->solvedBy->contains('uuid', Auth::user()->uuid)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already solved this challenge',
                    'data' => [
                        'is_first_blood' => false
                    ]
                ], 400);
            }

            if($challenge->flag == $request->submission) {
                // Create the submission
                $submission = EventChallangeSubmission::create([
                'event_challange_id' => $challenge->id,
                    'user_uuid' => Auth::user()->uuid,
                    'submission' => $request->submission,
                    'solved' => true,
                    'solved_at' => now(),
                    'ip' => $request->ip(),
                ]);
                
                // Calculate points and check for first blood
                $points = $challenge->bytes;
                $firstBloodPoints = 0;
                
                // Get first solver for this challenge
                $firstSolver = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc')
                    ->first();
                
                // Check if this user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === Auth::user()->uuid;
                if ($isFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'The flag is correct',
                    'data' => [
                        'flag_type' => 'single',
                        'flag' => $challenge->flag,
                        'points' => $points,
                        'first_blood_points' => $firstBloodPoints,
                        'is_first_blood' => $isFirstBlood
                    ]
                ], 200);
            }

            // Record failed attempt
            EventChallangeSubmission::create([
                'event_challange_id' => $challenge->id,
                'user_uuid' => Auth::user()->uuid,
                'submission' => $request->submission,
                'solved' => false,
                'ip' => $request->ip(),

            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'The flag is incorrect',
                'data' => [
                    'is_first_blood' => false
                ]
            ], 400);
        } 
        // Handle multiple flag types
        else {
            // Find the flag that matches the submission
            $matchedFlag = null;
            foreach ($challenge->flags as $flag) {
                if ($request->submission === $flag->flag) {
                    $matchedFlag = $flag;
                    break;
                }
            }
            
            if (!$matchedFlag) {
                // No matching flag found, record the attempt
                EventChallangeFlagSubmission::create([
                    'event_challange_flag_id' => $challenge->flags->first()->id,
                    'user_uuid' => Auth::user()->uuid,
                    'submission' => $request->submission,
                    'solved' => false,
                    'attempts' => 1
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'The flag is incorrect',
                    'data' => [
                        'flag_type' => $challenge->flag_type,
                        'is_first_blood' => false
                    ]
                ], 400);
            }
            
            // Check if user has already solved this flag
            $flagSubmission = EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                ->where('user_uuid', Auth::user()->uuid)
                ->where('solved', true)
                ->first();

            if ($flagSubmission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already solved this flag',
                ], 400);
            }

            // Record the solved flag
            $submission = EventChallangeFlagSubmission::create([
                'event_challange_flag_id' => $matchedFlag->id,
                'user_uuid' => Auth::user()->uuid,
                'submission' => $request->submission,
                'solved' => true,
                'solved_at' => now(),
                'attempts' => 1
            ]);

            // Check if all flags are solved for multiple_all type
            $allFlagsSolved = false;
            $points = 0;
            $firstBloodPoints = 0;
            $isFirstBlood = false;
            
            if ($challenge->flag_type === 'multiple_all') {
                // Get all flags available for this challenge
                $allFlags = $challenge->flags->pluck('id')->toArray();
                $totalFlags = count($allFlags);
                
                // Get all flags this specific user has solved
                $userSolvedFlags = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlags)
                    ->where('user_uuid', Auth::user()->uuid)
                    ->where('solved', true)
                    ->pluck('event_challange_flag_id')
                    ->unique()
                    ->values()
                    ->toArray();
                
                $userSolvedCount = count($userSolvedFlags);
                
                // Check if this user has solved all flags
                $allFlagsSolved = count(array_intersect($userSolvedFlags, $allFlags)) === $totalFlags;
                
                // Only award points if ALL flags are solved
                if ($allFlagsSolved) {
                    // Always award base points for solving all flags
                        $points = $challenge->bytes;
                        
                    // Check if this user was the first to solve all flags
                    $isFirstBlood = true;
                    
                    foreach ($allFlags as $flagId) {
                        $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagId)
                                ->where('solved', true)
                                ->orderBy('solved_at', 'asc')
                                ->first();
                                
                            if (!$firstSolver || $firstSolver->user_uuid !== Auth::user()->uuid) {
                            $isFirstBlood = false;
                                break;
                            }
                        }
                        
                    if ($isFirstBlood) {
                            $firstBloodPoints = $challenge->firstBloodBytes;
                    }
                        
                        return response()->json([
                            'status' => 'success',
                        'message' => 'The flag is correct',
                            'data' => [
                                'flag_type' => $challenge->flag_type,
                                'flag_name' => $matchedFlag->name,
                                'flag' => $matchedFlag->flag,
                                'all_flags_solved' => true,
                                'points' => $points,
                                'first_blood_points' => $firstBloodPoints,
                            'is_first_blood' => $firstBloodPoints > 0,
                        ]
                    ], 200);
                } else {
                    // If not all flags are solved, just return a message without data array
                    return response()->json([
                        'status' => 'success',
                        'message' => 'The flag is correct',
                        'flag_type' => $challenge->flag_type,
                    ], 200);
                }
            } else if ($challenge->flag_type === 'multiple_individual') {
                // For multiple_individual, points are awarded immediately for each flag
                // Always award base points for solving the flag
                $points = $matchedFlag->bytes;
                
                // Check if this is first blood for this flag
                $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc')
                    ->first();
                
                // Check if the current user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === Auth::user()->uuid;
                
                if ($isFirstBlood) {
                    $firstBloodPoints = $matchedFlag->firstBloodBytes;
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'The flag is correct',
                    'data' => [
                        'flag_type' => $challenge->flag_type,
                        'flag_name' => $matchedFlag->name,
                        'flag' => $matchedFlag->flag,

                        'points' => $points,
                        'first_blood_points' => $firstBloodPoints,
                        'is_first_blood' => $firstBloodPoints > 0,
                    ]
                ], 200);
            }
            
            // This should never be reached, but added as a fallback
            return response()->json([
                'status' => 'success',
                'message' => 'The flag is correct',
                'data' => [
                    'flag_type' => $challenge->flag_type,
                    'flag_name' => $matchedFlag->name,
                    'flag' => $matchedFlag->flag,
                    'is_first_blood' => false,
                ]
            ], 200);
        }
    }

    public function scoreboard($eventUuid)
    {
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the event
        $event = Event::where('uuid', $eventUuid)->first();
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        // Check if we need to use a frozen timestamp
        $freezeTime = null;
        if ($event->freeze && $event->freeze_time) {
            $freezeTime = $event->freeze_time;
        }

        $teams = EventTeam::where('event_uuid', $eventUuid)
            ->with(['members.eventSubmissions' => function($query) use ($eventUuid, $freezeTime) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
                
                // If frozen, only include submissions before freeze time
                if ($freezeTime) {
                    $query->where('created_at', '<', $freezeTime);
                }
            }, 'members.flagSubmissions' => function($query) use ($eventUuid, $freezeTime) {
                $query->whereHas('eventChallangeFlag.eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
                
                // If frozen, only include submissions before freeze time
                if ($freezeTime) {
                    $query->where('created_at', '<', $freezeTime);
                }
            }, 'members:uuid,user_name,profile_image'])
            ->get()
            ->map(function($team) use ($freezeTime) {
                $points = 0;
                $firstBloodCount = 0;
                $solvedChallenges = [];

                foreach ($team->members as $member) {
                    // Process challenge submissions
                    foreach ($member->eventSubmissions as $submission) {
                        if (!in_array($submission->event_challange_id, $solvedChallenges)) {
                            $solvedChallenges[] = $submission->event_challange_id;
                            
                            // Check if this was first blood
                            $firstSolverQuery = EventChallangeSubmission::where('event_challange_id', $submission->event_challange_id)
                                ->where('solved', true)
                                ->orderBy('solved_at');
                                
                            // Apply freeze time filter if needed
                            if ($freezeTime) {
                                $firstSolverQuery->where('created_at', '<', $freezeTime);
                            }
                            
                            $firstSolver = $firstSolverQuery->first();
                                
                            if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                                $points += $submission->eventChallange->firstBloodBytes ?? 0;
                                $firstBloodCount++;
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
                            $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagSubmission->event_challange_flag_id)
                                ->where('solved', true)
                                ->orderBy('solved_at');
                                
                            // Apply freeze time filter if needed
                            if ($freezeTime) {
                                $firstSolverQuery->where('created_at', '<', $freezeTime);
                            }
                            
                            $firstSolver = $firstSolverQuery->first();
                                
                            if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                                $points += $flagSubmission->eventChallangeFlag->firstBloodBytes ?? 0;
                                $firstBloodCount++;
                            } else {
                                $points += $flagSubmission->eventChallangeFlag->bytes ?? 0;
                            }
                        } else if ($challenge->flag_type === 'multiple_all') {
                            // For multiple_all, only count if all flags are solved
                            if (!in_array($challenge->id, $solvedChallenges)) {
                                $allFlags = $challenge->flags->count();
                                
                                $solvedFlagsQuery = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $challenge->flags->pluck('id'))
                                    ->where('user_uuid', $member->uuid)
                                    ->where('solved', true);
                                    
                                // Apply freeze time filter if needed
                                if ($freezeTime) {
                                    $solvedFlagsQuery->where('created_at', '<', $freezeTime);
                                }
                                
                                $solvedFlags = $solvedFlagsQuery->count();
                                    
                                if ($allFlags === $solvedFlags) {
                                    $solvedChallenges[] = $challenge->id;
                                    
                                    // Check if this was first blood for all flags
                                    $isFirstBlood = true;
                                    foreach ($challenge->flags as $flag) {
                                        $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                            ->where('solved', true)
                                            ->orderBy('solved_at');
                                            
                                        // Apply freeze time filter if needed
                                        if ($freezeTime) {
                                            $firstSolverQuery->where('created_at', '<', $freezeTime);
                                        }
                                        
                                        $firstSolver = $firstSolverQuery->first();
                                            
                                        if (!$firstSolver || $firstSolver->user_uuid !== $member->uuid) {
                                            $isFirstBlood = false;
                                            break;
                                        }
                                    }
                                    
                                    if ($isFirstBlood) {
                                        $points += $challenge->firstBloodBytes ?? 0;
                                        $firstBloodCount++;
                                    } else {
                                        $points += $challenge->bytes ?? 0;
                                    }
                                }
                            }
                        }
                    }
                }

                return [
                    'team_uuid' => $team->id,
                    'team_name' => $team->name,
                    'team_icon' => $team->icon_url,
                    'points' => $points,
                    'challenges_solved' => count($solvedChallenges),
                    'first_blood_count' => $firstBloodCount,
                    'members' => $team->members->map(function($member) {
                        return [
                            'user_name' => $member->user_name,
                            'profile_image' => $member->profile_image ? asset('storage/' . $member->profile_image) : null
                        ];
                    })
                ];
            })
            ->sortByDesc('points')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $teams,
            'frozen' => $event->freeze,
            'freeze_time' => $event->freeze_time ? $event->freeze_time->format('Y-m-d H:i:s') : null
        ]);
    }
    

    public function getSolvedFlags($eventChallengeUuid)
    {
        $challenge = EventChallange::with([
            'flags' => function($query) {
                $query->orderBy('order', 'asc');
            },
            'flags.solvedBy' => function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }
        ])->where('id', $eventChallengeUuid)->first();
        
        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // For single flag challenges
        if ($challenge->flag_type === 'single') {
            $isSolved = $challenge->solvedBy->contains('uuid', Auth::user()->uuid);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'challenge_id' => $challenge->id,
                    'challenge_title' => $challenge->title,
                    'flag_type' => 'single',
                    'solved' => $isSolved,
                    'solved_at' => $isSolved ? $this->formatInUserTimezone($challenge->solvedBy->first()->pivot->solved_at) : null
                ]
            ]);
        }
        
        // For multiple flag challenges
        $flags = $challenge->flags->map(function($flag) {
            $isFlagSolved = $flag->solvedBy->isNotEmpty();
            $solvedAt = $isFlagSolved ? $this->formatInUserTimezone($flag->solvedBy->first()->pivot->solved_at) : null;
            
            // Base flag data
            $flagData = [
                'id' => $flag->id,
                'solved' => $isFlagSolved,
                'solved_at' => $solvedAt
            ];

            // For multiple_all type, include name and description
            if ($flag->eventChallange->flag_type === 'multiple_all') {
                $flagData['name'] = $flag->name;
                $flagData['description'] = $flag->description;
            }
            // For multiple_individual type, include only name
            else if ($flag->eventChallange->flag_type === 'multiple_individual') {
                $flagData['name'] = $flag->name;
            }
            
            return $flagData;
        });
        
        $solvedCount = $flags->where('solved', true)->count();
        $allFlagsSolved = $solvedCount === $challenge->flags->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'challenge_id' => $challenge->id,
                'challenge_title' => $challenge->title,
                'flag_type' => $challenge->flag_type,
                'total_flags' => $challenge->flags->count(),
                'solved_flags' => $solvedCount,
                'all_flags_solved' => $allFlagsSolved,
                'flags' => $flags
            ]
        ]);
    }

    /**
     * Check if a challenge is solved by the current team
     *
     * @param string $eventChallengeUuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkIfSolved($eventChallengeUuid)
    {
        $user = Auth::user();
        
        // Get the event challenge
        $challenge = EventChallange::with(['flags', 'solvedBy', 'flags.solvedBy'])->where('id', $eventChallengeUuid)->firstOrFail();
        
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }
        
        // For single flag challenges
        if ($challenge->flag_type === 'single') {
            $isSolved = $challenge->solvedBy->contains('uuid', $user->uuid);
            $submission = null;
            
            if ($isSolved) {
                $submission = $challenge->solvedBy->where('uuid', $user->uuid)->first()->pivot;
            }
            
            // Get first blood if there are any solvers
            $firstBlood = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                ->where('solved', true)
                ->oldest('solved_at')
                ->first();
            
            $isFirstBlood = false;
            if ($isSolved && $submission && $firstBlood) {
                // Convert to date objects if needed
                $solvedAt = $submission->solved_at instanceof \Carbon\Carbon 
                    ? $submission->solved_at 
                    : \Carbon\Carbon::parse($submission->solved_at);
                
                $firstBloodSolvedAt = $firstBlood->solved_at instanceof \Carbon\Carbon 
                    ? $firstBlood->solved_at 
                    : \Carbon\Carbon::parse($firstBlood->solved_at);
                
                $isFirstBlood = $solvedAt->eq($firstBloodSolvedAt);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_solved' => $isSolved,
                    'flag_type' => 'single',
                    'solved_flags' => $isSolved ? 1 : 0,
                    'total_flags' => 1,
                    'all_flags_solved' => $isSolved,
                    'points' => $isSolved ? $challenge->bytes : 0,
                    'first_blood_points' => $isFirstBlood ? $challenge->firstBloodBytes : 0,
                    'solved_flags_data' => $isSolved ? [
                        [
                            'id' => $challenge->id,
                            'name' => $challenge->title,
                            'bytes' => $challenge->bytes,
                            'first_blood_bytes' => $challenge->firstBloodBytes,
                            'solved_at' => $submission->solved_at ? $this->formatInUserTimezone($submission->solved_at) : null,
                            'is_first_blood' => $isFirstBlood
                        ]
                    ] : []
                ]
            ]);
        }
        
        // For multiple flag challenges
        $solvedFlags = collect();
        $points = 0;
        $firstBloodPoints = 0;
        
        foreach ($challenge->flags as $flag) {
            $isFlagSolved = $flag->solvedBy->contains('uuid', $user->uuid);
            
            if ($isFlagSolved) {
                $flagSubmission = $flag->solvedBy->where('uuid', $user->uuid)->first()->pivot;
                
                // Get first blood information
                $firstBlood = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true)
                    ->oldest('solved_at')
                    ->first();
                
                $isFirstBlood = false;
                if ($flagSubmission && $firstBlood) {
                    // Convert to date objects if needed
                    $solvedAt = $flagSubmission->solved_at instanceof \Carbon\Carbon 
                        ? $flagSubmission->solved_at 
                        : \Carbon\Carbon::parse($flagSubmission->solved_at);
                    
                    $firstBloodSolvedAt = $firstBlood->solved_at instanceof \Carbon\Carbon 
                        ? $firstBlood->solved_at 
                        : \Carbon\Carbon::parse($firstBlood->solved_at);
                    
                    $isFirstBlood = $solvedAt->eq($firstBloodSolvedAt);
                }
                
                if ($challenge->flag_type === 'multiple_individual') {
                    $points += $flag->bytes;
                    if ($isFirstBlood) {
                        $firstBloodPoints += $flag->firstBloodBytes;
                    }
                }
                
                $solvedFlags->push([
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved_at' => $flagSubmission->solved_at ? $this->formatInUserTimezone($flagSubmission->solved_at) : null,
                    'is_first_blood' => $isFirstBlood
                ]);
            }
        }
        
        $allFlagsSolved = count($solvedFlags) === $challenge->flags->count() && $challenge->flags->count() > 0;
        
        // For multiple_all type, add points only if all flags are solved
        if ($challenge->flag_type === 'multiple_all' && $allFlagsSolved) {
            $points = $challenge->bytes;
            
            // Check if user has first blood for all flags
            $hasAllFirstBlood = true;
            foreach ($challenge->flags as $flag) {
                $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true)
                    ->oldest('solved_at')
                    ->first();
                
                if (!$firstSolver || $firstSolver->user_uuid !== $user->uuid) {
                    $hasAllFirstBlood = false;
                    break;
                }
            }
            
            if ($hasAllFirstBlood) {
                $firstBloodPoints = $challenge->firstBloodBytes;
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'is_solved' => $solvedFlags->isNotEmpty(),
                'flag_type' => $challenge->flag_type,
                'solved_flags' => $solvedFlags->count(),
                'total_flags' => $challenge->flags->count(),
                'all_flags_solved' => $allFlagsSolved,
                'points' => $points,
                'first_blood_points' => $firstBloodPoints,
                'solved_flags_data' => $solvedFlags
            ]
        ]);
    }


    public function showChallenge($challengeUuid)
    {
        $challenge = EventChallange::with(['category:uuid,icon', 'flags'])
            ->where('id', $challengeUuid)
            ->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        $challenge->category_icon = $challenge->category->icon ?? null;
        unset($challenge->category);
        $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);

        // Get solved count for the challenge
        $solvedCount = $challenge->submissions()->where('solved', true)->count();
        
        // Add flag information
        $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
        
        // Get first blood information
        $firstBlood = null;
        if ($solvedCount > 0) {
            $firstSolver = $challenge->submissions()
                ->where('solved', true)
                ->orderBy('created_at', 'asc')
                ->first();
            
            if ($firstSolver) {
                $firstBloodUser = User::where('uuid', $firstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                if ($firstBloodUser) {
                    $firstBlood = [
                        'user_name' => $firstBloodUser->user_name,
                        'profile_image' => $firstBloodUser->profile_image ? asset('storage/' . $firstBloodUser->profile_image) : null,
                        'solved_at' => $firstSolver->created_at,
                    ];
                }
            }
        }
        $challenge->first_blood = $firstBlood;
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $challenge->flags_data = [[
                'id' => null,
                'bytes' => $challenge->bytes,
                'first_blood_bytes' => $challenge->firstBloodBytes,
                'solved_count' => $solvedCount,
                'first_blood' => $firstBlood,
            ]];
        }
        // For multiple_all type
        else if ($challenge->flag_type === 'multiple_all') {
            $flagsData = [];
            
            foreach ($challenge->flags as $flag) {
                $flagsData[] = [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'ar_name' => $flag->ar_name,

                    'description' => $flag->description,
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                    'solved_count' => $solvedCount,
                    'first_blood' => $firstBlood,
                ];
            }
            
            $challenge->flags_data = $flagsData;
            $challenge->flags_count = $challenge->flags->count();
        }
        // For multiple_individual type
        else if ($challenge->flag_type === 'multiple_individual' && $challenge->flags) {
            $flagsData = [];
            
            foreach ($challenge->flags as $flag) {
                // Get solved count for this flag
                $flagSolvedCount = $challenge->submissions()
                    ->where('submission', $flag->flag)
                    ->where('solved', true)
                    ->count();
                
                // Get first blood for this flag
                $flagFirstBlood = null;
                if ($flagSolvedCount > 0) {
                    $flagFirstSolver = $challenge->submissions()
                        ->where('submission', $flag->flag)
                        ->where('solved', true)
                        ->orderBy('solved_at', 'asc')
                        ->first();
                    
                    if ($flagFirstSolver) {
                        $flagFirstBloodUser = User::where('uuid', $flagFirstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                        if ($flagFirstBloodUser) {
                            $flagFirstBlood = [
                                'user_name' => $flagFirstBloodUser->user_name,
                                'profile_image' => $flagFirstBloodUser->profile_image ? asset('storage/' . $flagFirstBloodUser->profile_image) : null,
                                'solved_at' => $flagFirstSolver->solved_at,
                            ];
                        }
                    }
                }
                
                $flagsData[] = [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'ar_name' => $flag->ar_name,

                    'description' => $flag->description,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved_count' => $flagSolvedCount,
                    'first_blood' => $flagFirstBlood,
                ];
            }
            
            $challenge->flags_data = $flagsData;
            $challenge->flags_count = $challenge->flags->count();
        }

        // Convert to array and remove unwanted fields
        $challengeData = $challenge->toArray();
        $challengeData['solved_count'] = $solvedCount;
        $challengeData['description'] = $challenge->description;
        $challengeData['file'] = $challenge->file;
        $challengeData['link'] = $challenge->link;
        
        // Remove flags from response
        unset($challengeData['flags']);
        
        // Remove the actual flag value from the response for security
        unset($challengeData['flag']);
        
        // Also ensure no flag values are exposed from the flags collection
        if (isset($challengeData['flags_data'])) {
            foreach ($challengeData['flags_data'] as &$flagData) {
                if (isset($flagData['flag'])) {
                    unset($flagData['flag']);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'event_name' => Event::where('id', $challengeUuid)
            ->first()->title,
            'data' => $challengeData
        ]);
    }
    
    /**
     * Get flags for a specific event challenge
     * 
     * @param string $challengeUuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeFlags($challengeUuid)
    {
        $challenge = EventChallange::where('id', $challengeUuid)->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }
        
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get flag type information
        $flagType = $challenge->flag_type;
        $flagTypeDescription = $this->getFlagTypeDescription($flagType);
        
        // For single flag type, return the flag directly
        if ($flagType === 'single') {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'flag_type' => $flagType,
                    'flag_type_description' => $flagTypeDescription,
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                ]
            ]);
        }
        
        // For multiple flag types, return all flags
        $flags = $challenge->flags()->get()->map(function ($flag) {
            return [
                'id' => $flag->id,
                'name' => $flag->name,
                'description' => $flag->description,
                'bytes' => $flag->bytes,
                'first_blood_bytes' => $flag->firstBloodBytes,
            ];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'flag_type' => $flagType,
                'flag_type_description' => $flagTypeDescription,
                'total_bytes' => $challenge->bytes,
                'total_first_blood_bytes' => $challenge->firstBloodBytes,
                'flags' => $flags,
                'flags_count' => $flags->count(),
            ]
        ]);
    }
    
    /**
     * Get challenge flags and solved status for the authenticated user
     * 
     * @param string $challengeUuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeStatusAndFlags($challengeUuid)
    {
        $challenge = EventChallange::where('id', $challengeUuid)->with('flags')->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }
        
        $user = Auth::user();
        
        $result = [
            'flag_type' => $challenge->flag_type,
            'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
        ];
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $solved = $challenge->solvedBy()->where('user_uuid', $user->uuid)->exists();
                
            $result['solved'] = $solved;
            
            // Single flag data
            $result['flag_data'] = [
                'bytes' => $challenge->bytes,
                'first_blood_bytes' => $challenge->firstBloodBytes,
                'solved_count' => $challenge->solvedBy()->count(),
            ];
            
            // Add first blood information if solved
            if ($solved) {
                $firstSolver = $challenge->solvedBy()->orderBy('event_challange_submission.created_at', 'asc')->first();
                    
                $result['is_first_blood'] = $firstSolver && $firstSolver->uuid === $user->uuid;
                
                $solvedAt = $challenge->solvedBy()->where('user_uuid', $user->uuid)->first()->pivot->solved_at;
                    
                $result['solved_at'] = $this->formatInUserTimezone($solvedAt);
            }
        } 
        // For multiple flag types
        else {
            $flagsData = [];
            
            foreach ($challenge->flags as $flag) {
                $isSolved = $flag->solvedBy()->where('user_uuid', $user->uuid)->exists();
                
                $flagData = [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved' => $isSolved,
                    'solved_count' => $flag->solvedBy()->count(),
                ];
                
                // Add additional data if the flag is solved
                if ($isSolved) {
                    $flagSubmission = $flag->solvedBy()->where('user_uuid', $user->uuid)->first();
                        
                    $flagData['solved_at'] = $this->formatInUserTimezone($flagSubmission->pivot->solved_at);
                    
                    // Check if this user got first blood for this flag
                    $firstSolver = $flag->solvedBy()->orderBy('event_challange_flag_submission.solved_at', 'asc')->first();
                        
                    $flagData['is_first_blood'] = $firstSolver && $firstSolver->uuid === $user->uuid;
                }
                
                $flagsData[] = $flagData;
            }
            
            // Count how many flags are solved
            $solvedCount = count(array_filter($flagsData, function($flag) {
                return $flag['solved'];
            }));
            
            $result['flags'] = $flagsData;
            $result['flags_count'] = count($flagsData);
            $result['solved_flags_count'] = $solvedCount;
            $result['all_flags_solved'] = $solvedCount === count($flagsData) && $solvedCount > 0;
            
            // For multiple_all, include total bytes
            if ($challenge->flag_type === 'multiple_all') {
                $result['total_bytes'] = $challenge->bytes;
                $result['total_first_blood_bytes'] = $challenge->firstBloodBytes;
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }
    

    
    /**
     * Get team-specific leaderboard for an event
     * 
     * @param string $eventUuid Event UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamLeaderboard($eventUuid)
    {
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the current user's team
        $user = Auth::user();
        $team = EventTeam::where('event_uuid', $eventUuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['members'])
            ->first();
            
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team for this event'
            ], 404);
        }
        
        // Get all challenges for this event
        $challenges = EventChallange::where('event_uuid', $eventUuid)
            ->with(['flags'])
            ->get();
            
        // Get members' data with their solved challenges and flags
        $teamMembers = User::whereIn('uuid', $team->members->pluck('user_uuid'))
            ->with(['solvedChallenges' => function($query) use ($eventUuid) {
                $query->where('event_uuid', $eventUuid);
            }, 'solvedFlags' => function($query) use ($challenges) {
                $query->whereIn('id', $challenges->flatMap->flags->pluck('id'));
            }])
            ->get()
            ->map(function($member) use ($challenges) {
                $totalPoints = 0;
                $totalFirstBloodPoints = 0;
                $solvedChallenges = [];
                
                // Process single flag type challenges
                foreach ($challenges->where('flag_type', 'single') as $challenge) {
                    $submission = $member->solvedChallenges
                        ->where('id', $challenge->id)
                        ->first();
                        
                    if ($submission) {
                        $points = $challenge->bytes;
                        $firstBloodPoints = 0;
                        
                        // Check if this was first blood
                        $firstSolver = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                            ->where('solved', true)
                            ->orderBy('solved_at')
                            ->first();
                            
                            if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                                $firstBloodPoints = $challenge->firstBloodBytes;
                                $points += $firstBloodPoints;
                            }
                            
                            $totalPoints += $points;
                            $totalFirstBloodPoints += $firstBloodPoints;
                            
                            $solvedChallenges[] = [
                                'id' => $challenge->id,
                                'title' => $challenge->title,
                                'type' => 'single',
                                'points' => $points,
                                'first_blood_points' => $firstBloodPoints,
                                'is_first_blood' => $firstBloodPoints > 0,
                                'solved_at' => $this->formatInUserTimezone($submission->pivot->solved_at)
                            ];
                    }
                }
                
                // Process multiple flags challenges
                foreach ($challenges->whereIn('flag_type', ['multiple_all', 'multiple_individual']) as $challenge) {
                    // Get solved flags for this challenge
                    $solvedFlags = $member->solvedFlags
                        ->filter(function($flag) use ($challenge) {
                            return $challenge->flags->contains('id', $flag->id);
                        });
                        
                    if ($solvedFlags->isEmpty()) {
                        continue;
                    }
                    
                    $challengePoints = 0;
                    $challengeFirstBloodPoints = 0;
                    $flagsData = [];
                    
                    // For multiple_all type
                    if ($challenge->flag_type === 'multiple_all') {
                        // Check if all flags are solved
                        $allFlagsSolved = $solvedFlags->count() === $challenge->flags->count();
                        
                        if ($allFlagsSolved) {
                            $points = $challenge->bytes;
                            
                            // Check if this was first blood for all flags
                            $isFirstBlood = true;
                            foreach ($challenge->flags as $flag) {
                                $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                    ->where('solved', true)
                                    ->orderBy('solved_at');
                                
                                // Apply freeze time filter if frozen
                                if ($isFrozen) {
                                    $firstSolverQuery->where('solved_at', '<=', $freezeTime);
                                }
                                
                                $firstSolver = $firstSolverQuery->first();
                                
                                if (!$firstSolver || $firstSolver->user_uuid !== $member->uuid) {
                                    $isFirstBlood = false;
                                    break;
                                }
                            }
                            
                            if ($isFirstBlood) {
                                $challengeFirstBloodPoints = $challenge->firstBloodBytes;
                                $points += $challengeFirstBloodPoints;
                            }
                            
                            $challengePoints += $points;
                            
                            foreach ($solvedFlags as $flag) {
                                $flagsData[] = [
                                    'id' => $flag->id,
                                    'name' => $flag->name,
                                    'solved_at' => $this->formatInUserTimezone($flag->pivot->solved_at)
                                ];
                            }
                            
                            $solvedChallenges[] = [
                                'id' => $challenge->id,
                                'title' => $challenge->title,
                                'type' => 'multiple_all',
                                'points' => $challengePoints,
                                'first_blood_points' => $challengeFirstBloodPoints,
                                'is_first_blood' => $isFirstBlood,
                                'flags' => $flagsData,
                                'solved_at' => collect($solvedFlags->pluck('pivot.solved_at'))->max()
                            ];
                            
                            $totalPoints += $challengePoints;
                            $totalFirstBloodPoints += $challengeFirstBloodPoints;
                        }
                    }
                    // For multiple_individual type
                    else if ($challenge->flag_type === 'multiple_individual') {
                        foreach ($solvedFlags as $flag) {
                            $flagPoints = $flag->bytes;
                            $flagFirstBloodPoints = 0;
                            
                            // Check if this was first blood for this flag
                            $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                ->where('solved', true)
                                ->orderBy('solved_at');
                                
                            // Apply freeze time filter if frozen
                            if ($isFrozen) {
                                $firstSolver->where('solved_at', '<=', $freezeTime);
                            }
                            
                            $firstSolver = $firstSolver->first();
                                
                            if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                                $flagFirstBloodPoints = $flag->firstBloodBytes;
                                $flagPoints += $flagFirstBloodPoints;
                            }
                            
                            $challengePoints += $flagPoints;
                            $challengeFirstBloodPoints += $flagFirstBloodPoints;
                            
                            $flagsData[] = [
                                'id' => $flag->id,
                                'name' => $flag->name,
                                'points' => $flagPoints,
                            'is_first_blood' => $flagFirstBloodPoints > 0,
                            'solved_at' => $this->formatInUserTimezone($flag->pivot->solved_at)
                            ];
                        }
                        
                        if (!empty($flagsData)) {
                            $solvedChallenges[] = [
                                'id' => $challenge->id,
                                'title' => $challenge->title,
                                'type' => 'multiple_individual',
                                'points' => $challengePoints,
                                'first_blood_points' => $challengeFirstBloodPoints,
                                'is_first_blood' => $challengeFirstBloodPoints > 0,
                                'flags' => $flagsData,
                                'solved_at' => collect($solvedFlags->pluck('pivot.solved_at'))->min()
                            ];
                            
                            $totalPoints += $challengePoints;
                            $totalFirstBloodPoints += $challengeFirstBloodPoints;
                        }
                    }
                }
                
                return [
                    'user_uuid' => $member->uuid,
                    'user_name' => $member->user_name,
                    'profile_image' => $member->profile_image ? asset('storage/' . $member->profile_image) : null,
                    'total_points' => $totalPoints,
                    'first_blood_points' => $totalFirstBloodPoints,
                    'challenges' => collect($solvedChallenges)->sortByDesc('solved_at')->values()->all(),
                    'solved_challenges_count' => count($solvedChallenges)
                ];
            })
            ->sortByDesc('total_points')
            ->values();
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'team' => [
                    'name' => $team->name,
                    'icon' => $team->icon ? url('storage/team-icons/' . $team->icon) : null,
                    'member_count' => $team->members->count()
                ],
                'members' => $teamMembers,
                'total_team_points' => $teamMembers->sum('total_points'),
                'total_challenges' => $challenges->count(),
                'last_updated' => now()->format('c')
            ]
        ]);
    }
    
    /**
     * Get leaderboard for a specific challenge showing only team members
     * 
     * @param string $challengeUuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamChallengeLeaderboard($challengeUuid)
    {
        $challenge = EventChallange::with(['flags'])->find($challengeUuid);

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the event to check if it's frozen
        $event = Event::where('uuid', $challenge->event_uuid)->first();
        $isFrozen = false;
        $freezeTime = null;

        if ($event && $event->freeze && $event->freeze_time) {
            $isFrozen = true;
            $freezeTime = $event->freeze_time;
        }

        // Get the current user's team
        $user = Auth::user();
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['members'])
            ->first();
            
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team for this event'
            ], 404);
        }
        
        // Get team member IDs
        $teamMemberUuids = $team->members->pluck('uuid')->toArray();
        
        // Collect results
        $results = collect();

        // SINGLE FLAG TYPE
        if ($challenge->flag_type === 'single') {
            // Get all submissions for this challenge from team members
            $submissions = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                ->whereIn('user_uuid', $teamMemberUuids)
                ->where('solved', true)
                ->get();

            // Find first solver (respecting freeze time)
            $firstBloodQuery = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                ->where('solved', true)
                ->orderBy('solved_at', 'asc');
                
            if ($isFrozen) {
                $firstBloodQuery->where('solved_at', '<=', $freezeTime);
            }
            
            $firstBloodSubmission = $firstBloodQuery->first();
            
            // Process each team member's submission
            foreach ($submissions as $submission) {
                $member = $team->members->firstWhere('uuid', $submission->user_uuid);
                if (!$member) continue;
                
                // Check if solved after freeze
                $solvedAfterFreeze = $isFrozen && $submission->solved_at > $freezeTime;
                
                // Check if first blood
                $isFirstBlood = $firstBloodSubmission && $firstBloodSubmission->user_uuid === $submission->user_uuid;
                
                // Calculate points
                $points = $challenge->bytes;
                $firstBloodPoints = $isFirstBlood ? $challenge->firstBloodBytes : 0;
                
                // Add to results
                $results->push([
                    'user_uuid' => $solvedAfterFreeze ? 'hidden' : $member->uuid,
                    'user_name' => $solvedAfterFreeze ? '*****' : $member->user_name,
                    'profile_image' => $solvedAfterFreeze ? null : ($member->profile_image ? url('storage/' . $member->profile_image) : null),
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood,
                    'solved_at' => $this->formatInUserTimezone($submission->solved_at),
                    'solved_flags' => [],
                    'flags_count' => 0,
                    'all_flags_solved' => true,
                    'solved_after_freeze' => $solvedAfterFreeze
                ]);
            }
        }
        // MULTIPLE FLAGS TYPE
        else if ($challenge->flags->count() > 0) {
            // Get flag IDs for this challenge
            $flagIds = $challenge->flags->pluck('id')->toArray();
            
            // Get all submissions for these flags by team members
            $flagSubmissions = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $flagIds)
                ->whereIn('user_uuid', $teamMemberUuids)
                ->where('solved', true)
                ->get();
                
            if ($flagSubmissions->isEmpty()) {
                // Return empty result
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'team' => [
                            'name' => $team->name,
                            'icon' => $team->icon_url,
                            'member_count' => $team->members->count()
                        ],
                        'challenge' => [
                            'id' => $challenge->id,
                            'title' => $challenge->title,
                            'flag_type' => $challenge->flag_type,
                            'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
                            'bytes' => $challenge->bytes,
                            'first_blood_bytes' => $challenge->firstBloodBytes,
                            'total_flags' => $challenge->flags->count()
                        ],
                        'members' => [],
                        'total_solvers' => 0,
                        'frozen' => $isFrozen,
                        'freeze_time' => $freezeTime ? $this->formatInUserTimezone($freezeTime) : null,
                        'last_updated' => $this->formatInUserTimezone(now())
                    ]
                ]);
            }
            
            // Prepare first blood information for each flag
            $firstBloodByFlagId = [];
            foreach ($flagIds as $flagId) {
                $query = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagId)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc');
                    
                if ($isFrozen) {
                    $query->where('solved_at', '<=', $freezeTime);
                }
                
                $firstBloodByFlagId[$flagId] = $query->first();
            }
            
            // Group submissions by user
            $submissionsByUser = $flagSubmissions->groupBy('user_uuid');
            
            // Process each user's submissions
            foreach ($submissionsByUser as $userUuid => $userSubmissions) {
                $member = $team->members->firstWhere('uuid', $userUuid);
                if (!$member) continue;
                
                $points = 0;
                $firstBloodPoints = 0;
                $isFirstBlood = false;
                $solvedFlags = [];
                
                // Check if any submissions were after freeze time
                $solvedAfterFreeze = false;
                if ($isFrozen) {
                    foreach ($userSubmissions as $submission) {
                        if ($submission->solved_at > $freezeTime) {
                            $solvedAfterFreeze = true;
                            break;
                        }
                    }
                }
                
                // Find earliest submission time for sorting
                $earliestSolvedAt = $userSubmissions->min('solved_at');
                
                // Handle Multiple ALL flag type
                if ($challenge->flag_type === 'multiple_all') {
                    // Check if all flags are solved
                    $solvedFlagIds = $userSubmissions->pluck('event_challange_flag_id')->unique()->values()->toArray();
                    $allFlagsSolved = count($solvedFlagIds) === count($flagIds);
                    
                    if ($allFlagsSolved) {
                        // Award points for solving all flags
                        $points = $challenge->bytes;
                        
                        // Check for first blood
                        $allFirstBlood = true;
                        foreach ($flagIds as $flagId) {
                            $firstSolver = $firstBloodByFlagId[$flagId] ?? null;
                            if (!$firstSolver || $firstSolver->user_uuid !== $userUuid) {
                                $allFirstBlood = false;
                                break;
                            }
                        }
                        
                        if ($allFirstBlood) {
                            $firstBloodPoints = $challenge->firstBloodBytes;
                            $isFirstBlood = true;
                        }
                    }
                    
                    // Add flag data (no individual points)
                    foreach ($userSubmissions as $submission) {
                        $flag = $challenge->flags->firstWhere('id', $submission->event_challange_flag_id);
                        if (!$flag) continue;
                        
                        $solvedFlags[] = [
                            'id' => $flag->id,
                            'name' => $flag->name,
                            'points' => 0, // Points only awarded for all flags
                            'is_first_blood' => false,
                            'solved_at' => $this->formatInUserTimezone($submission->solved_at)
                        ];
                    }
                }
                // Handle Multiple INDIVIDUAL flag type
                else if ($challenge->flag_type === 'multiple_individual') {
                    foreach ($userSubmissions as $submission) {
                        $flag = $challenge->flags->firstWhere('id', $submission->event_challange_flag_id);
                        if (!$flag) continue;
                        
                        // Initialize flag data
                        $flagPoints = 0;
                        $flagFirstBlood = false;
                        
                        // Count points only for flags solved before freeze
                        if (!$isFrozen || $submission->solved_at <= $freezeTime) {
                            $flagPoints = $flag->bytes;
                            
                            // Check for first blood
                            $firstSolver = $firstBloodByFlagId[$submission->event_challange_flag_id] ?? null;
                            if ($firstSolver && $firstSolver->user_uuid === $userUuid) {
                                $flagPoints += $flag->firstBloodBytes;
                                $firstBloodPoints += $flag->firstBloodBytes;
                                $flagFirstBlood = true;
                            }
                            
                            $points += $flagPoints;
                        }
                        
                        // Add flag data
                        $solvedFlags[] = [
                            'id' => $flag->id,
                            'name' => $flag->name,
                            'points' => $flagPoints,
                            'is_first_blood' => $flagFirstBlood,
                            'solved_at' => $this->formatInUserTimezone($submission->solved_at)
                        ];
                    }
                    
                    // Set first blood flag if any
                    $isFirstBlood = $firstBloodPoints > 0;
                }
                
                // Add to results
                $results->push([
                    'user_uuid' => $solvedAfterFreeze ? 'hidden' : $member->uuid,
                    'user_name' => $solvedAfterFreeze ? '*****' : $member->user_name,
                    'profile_image' => $solvedAfterFreeze ? null : ($member->profile_image ? url('storage/' . $member->profile_image) : null),
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood,
                    'solved_at' => $this->formatInUserTimezone($earliestSolvedAt),
                    'solved_flags' => $solvedFlags,
                    'flags_count' => count($solvedFlags),
                    'all_flags_solved' => $challenge->flag_type !== 'multiple_all' || 
                                         (count($flagIds) === count($solvedFlagIds ?? [])),
                    'solved_after_freeze' => $solvedAfterFreeze
                ]);
            }
        }
        
        // Return full response with all data
        return response()->json([
            'status' => 'success',
            'data' => [
                'team' => [
                    'name' => $team->name,
                    'icon' => $team->icon_url,
                    'member_count' => $team->members->count()
                ],
                'challenge' => [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'flag_type' => $challenge->flag_type,
                    'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                    'total_flags' => $challenge->flags->count()
                ],
                'members' => $results->sortByDesc('solved_at')->values()->all(),
                'total_solvers' => $results->count(),
                'frozen' => $isFrozen,
                'freeze_time' => $freezeTime ? $this->formatInUserTimezone($freezeTime) : null,
                'last_updated' => $this->formatInUserTimezone(now())
            ]
        ]);
    }
    
    /**
     * Get flag type description 
     * 
     * @param string $flagType
     * @return string
     */
    private function getFlagTypeDescription($flagType)
    {
        $descriptions = [
            'single' => 'Single flag challenge - solve one flag to complete',
            'multiple_all' => 'Multiple flags challenge - solve all flags to get points',
            'multiple_individual' => 'Multiple flags challenge - get points for each flag solved',
        ];
        
        return $descriptions[$flagType] ?? 'Unknown flag type';
    }

    private function translateDifficulty($difficulty)
    {
        $translations = [
            'easy' => 'سهل',
            'medium' => 'متوسط',
            'hard' => 'صعب',
            'very_hard' => 'صعب جدا'
        ];

        return $translations[$difficulty] ?? $difficulty;
    }
}
