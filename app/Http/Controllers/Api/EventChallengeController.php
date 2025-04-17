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
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function validateEventAndTeamRequirements($eventUuid)
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

        // Check if user is part of any team in this event
        $team = Team::where('event_uuid', $eventUuid)
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
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
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid);
        if ($validationResponse) {
            return $validationResponse;
        }

        $challenges = EventChallange::with(['category:uuid,icon', 'flags'])
            ->where('event_uuid', $eventUuid)
            ->get();

        $challenges->each(function ($challenge) {
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
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->count();
                    
                    // Get first blood for this flag
                    $flagFirstBlood = null;
                    if ($flagSolvedCount > 0) {
                        $flagFirstSolver = $challenge->submissions()
                            ->where('flag', $flag->flag)
                            ->where('solved', true)
                            ->orderBy('created_at', 'asc')
                            ->first();
                        
                        if ($flagFirstSolver) {
                            $flagFirstBloodUser = User::where('uuid', $flagFirstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                            if ($flagFirstBloodUser) {
                                $flagFirstBlood = [
                                    'user_name' => $flagFirstBloodUser->user_name,
                                    'profile_image' => $flagFirstBloodUser->profile_image ? asset('storage/' . $flagFirstBloodUser->profile_image) : null,
                                    'solved_at' => $flagFirstSolver->created_at,
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
            ->whereHas('users', function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        // Handle single flag type
        if ($challenge->flag_type === 'single') {
            // Check if user has already solved this challenge
            if ($challenge->solvedBy->contains('uuid', Auth::user()->uuid)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already solved this challenge'
                ], 400);
            }

            // Get or create submission record
            $submission = EventChallangeSubmission::firstOrNew([
                'event_challange_id' => $challenge->id,
                'user_uuid' => Auth::user()->uuid
            ]);

            $submission->attempts += 1;
            $submission->submission = $request->submission;

            // Check if solution is correct
            if ($request->submission === $challenge->flag) {
                $submission->solved = true;
                $submission->solved_at = now();

                // Check for first blood
                $isFirstBlood = !EventChallangeSubmission::where('event_challange_id', $challenge->id)
                    ->where('solved', true)
                    ->exists();

                $points = $challenge->bytes;
                $firstBloodPoints = 0;
                
                if ($isFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
                    $points += $firstBloodPoints;
                }

                $submission->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Correct! Challenge solved.',
                    'data' => [
                        'points' => $points,
                        'first_blood_points' => $firstBloodPoints,
                        'is_first_blood' => $isFirstBlood,
                        'attempts' => $submission->attempts,
                        'flag_type' => 'single',
                        'solved' => true
                    ]
                ]);
            }

            $submission->save();

            return response()->json([
                'status' => 'error',
                'message' => 'Incorrect solution',
                'data' => [
                    'attempts' => $submission->attempts,
                    'flag_type' => 'single'
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
                $flagSubmission = EventChallangeFlagSubmission::create([
                    'event_challange_flag_id' => $challenge->flags->first()->id, // Use first flag for tracking attempts
                    'user_uuid' => Auth::user()->uuid,
                    'submission' => $request->submission,
                    'solved' => false,
                    'attempts' => 1
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Incorrect solution',
                    'data' => [
                        'attempts' => 1,
                        'flag_type' => $challenge->flag_type
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
                    'message' => 'You have already solved this flag'
                ], 400);
            }

            // Create flag submission record
            $flagSubmission = EventChallangeFlagSubmission::create([
                'event_challange_flag_id' => $matchedFlag->id,
                'user_uuid' => Auth::user()->uuid,
                'submission' => $request->submission,
                'solved' => true,
                'solved_at' => now(),
                'attempts' => 1
            ]);

            // Check for first blood
            $isFirstBlood = EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                ->where('solved', true)
                ->count() === 1;

            $points = 0;
            $firstBloodPoints = 0;
            $allFlagsSolved = false;
            
            if ($challenge->flag_type === 'multiple_individual') {
                // Individual points for each flag
                $points = $matchedFlag->bytes;
                if ($isFirstBlood) {
                    $firstBloodPoints = $matchedFlag->firstBloodBytes;
                    $points += $firstBloodPoints;
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Correct! Flag solved.',
                    'data' => [
                        'flag_type' => $challenge->flag_type,
                        'flag_name' => $matchedFlag->name,
                        'points' => $points,
                        'first_blood_points' => $firstBloodPoints,
                        'is_first_blood' => $isFirstBlood
                    ]
                ]);
            } 
            else if ($challenge->flag_type === 'multiple_all') {
                // Get all solved flags for this user
                $solvedFlags = $challenge->flags()
                    ->whereHas('solvedBy', function($query) {
                        $query->where('user_uuid', Auth::user()->uuid);
                    })
                    ->get();

                // Get all flags for this challenge
                $allFlags = $challenge->flags()->get();

                // Check if all flags are solved by comparing counts
                $allFlagsSolved = $solvedFlags->count() === $allFlags->count();
                
                // Double-check by comparing flag IDs
                if ($allFlagsSolved) {
                    $solvedFlagIds = $solvedFlags->pluck('id')->toArray();
                    $allFlagIds = $allFlags->pluck('id')->toArray();
                    $allFlagsSolved = count(array_intersect($solvedFlagIds, $allFlagIds)) === count($allFlagIds);
                    
                    // For multiple_all, points are only awarded when all flags are solved
                    if ($allFlagsSolved) {
                        $points = $challenge->bytes;
                        
                        // Check if this is first blood for all flags
                        $hasFirstBlood = true;
                        foreach ($allFlags as $flag) {
                            $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                ->where('solved', true)
                                ->orderBy('solved_at', 'asc')
                                ->first();
                                
                            if (!$firstSolver || $firstSolver->user_uuid !== Auth::user()->uuid) {
                                $hasFirstBlood = false;
                                break;
                            }
                        }
                        
                        if ($hasFirstBlood) {
                            $firstBloodPoints = $challenge->firstBloodBytes;
                            $points += $firstBloodPoints;
                        }
                        
                        // Create a submission record for the challenge
                        $challengeSubmission = EventChallangeSubmission::firstOrCreate(
                            [
                                'event_challange_id' => $challenge->id,
                                'user_uuid' => Auth::user()->uuid,
                                'solved' => true
                            ],
                            [
                                'solved_at' => now(),
                                'attempts' => 0,
                                'submission' => $request->submission
                            ]
                        );
                        
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Correct! All flags solved.',
                            'data' => [
                                'flag_type' => $challenge->flag_type,
                                'flag_name' => $matchedFlag->name,
                                'all_flags_solved' => true,
                                'points' => $points,
                                'first_blood_points' => $firstBloodPoints,
                                'is_first_blood' => $hasFirstBlood
                            ]
                        ]);
                    }
                }
                
                // If not all flags solved yet, return success without points
                return response()->json([
                    'status' => 'success',
                    'message' => 'Correct! Flag solved.',
                    'data' => [
                        'flag_type' => $challenge->flag_type,
                        'flag_name' => $matchedFlag->name,
                        'all_flags_solved' => false,
                        'solved_flags_count' => $solvedFlags->count(),
                        'total_flags_count' => $allFlags->count()
                    ]
                ]);
            }

            // Get all solved flags data for the response
            $solvedFlagsList = $challenge->flags()
                ->whereHas('solvedBy', function($query) {
                    $query->where('user_uuid', Auth::user()->uuid);
                })
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Correct! Flag solved.',
                'data' => [
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood,
                    'flag_type' => $challenge->flag_type,
                    'flag_id' => $matchedFlag->id,
                    'flag_name' => $matchedFlag->name,
                    'solved_flags' => $solvedFlagsList->count(),
                    'total_flags' => $challenge->flags->count(),
                    'all_flags_solved' => $allFlagsSolved,
                    'solved_flags_data' => $solvedFlagsList->map(function($solvedFlag) {
                        $solvedData = $solvedFlag->solvedBy->first()->pivot;
                        return [
                            'id' => $solvedFlag->id,
                            'name' => $solvedFlag->name,
                            'bytes' => $solvedFlag->bytes,
                            'first_blood_bytes' => $solvedFlag->firstBloodBytes,
                            'solved_at' => $this->formatInUserTimezone($solvedData->solved_at),
                            'attempts' => $solvedData->attempts
                        ];
                    })
                ]
            ]);
        }
    }

    public function scoreboard($eventUuid)
    {
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid);
        if ($validationResponse) {
            return $validationResponse;
        }

        $teams = EventTeam::where('event_uuid', $eventUuid)
            ->with(['users.submissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'users.flagSubmissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallangeFlag.eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'users:uuid,user_name,profile_image'])
            ->get()
            ->map(function($team) {
                $totalPoints = 0;
                $solvedChallenges = collect();

                foreach ($team->users as $member) {
                    // Handle single flag challenges
                    foreach ($member->submissions as $submission) {
                        $challenge = $submission->eventChallange;
                        
                        // Skip if this is a multiple flag challenge
                        if ($challenge->usesMultipleFlags()) {
                            continue;
                        }
                        
                        $points = $challenge->bytes;

                        // Check if this was first blood
                        $isFirstBlood = $submission->solved_at->eq(
                            EventChallangeSubmission::where('event_challange_id', $challenge->id)
                                ->where('solved', true)
                                ->oldest('solved_at')
                                ->first()
                                ->solved_at
                        );

                        if ($isFirstBlood) {
                            $points = $challenge->firstBloodBytes;
                        }

                        $solvedChallenges->push([
                            'title' => $challenge->title,
                            'points' => $points,
                            'solved_at' => $submission->solved_at->format('c'),
                            'solved_by' => [
                                'username' => $member->user_name,
                                'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null,
                            ],
                            'first_blood' => $isFirstBlood,
                            'flag_type' => 'single'
                        ]);

                        $totalPoints += $points;
                    }
                    
                    // Handle multiple flag challenges
                    $flagSubmissions = $member->flagSubmissions->groupBy('event_challange_flag.event_challange_id');
                    
                    foreach ($flagSubmissions as $challengeId => $submissions) {
                        $challenge = $submissions->first()->eventChallangeFlag->eventChallange;
                        
                        if ($challenge->flag_type === 'multiple_all') {
                            // Only count points if all flags are solved
                            $allFlagsSolved = $challenge->flags->count() === $submissions->count();
                            
                            if ($allFlagsSolved) {
                                // Check if this was first time all flags were solved
                                $isFirstAllSolved = $submissions->min('solved_at')->eq(
                                    EventChallangeSubmission::where('event_challange_id', $challenge->id)
                                        ->where('solved', true)
                                        ->oldest('solved_at')
                                        ->first()
                                        ->solved_at
                                );
                                
                                // Use first blood points if this was the first team to solve all flags
                                $points = $isFirstAllSolved ? $challenge->firstBloodBytes : $challenge->bytes;
                                
                                $solvedChallenges->push([
                                    'title' => $challenge->title,
                                    'points' => $points,
                                    'solved_at' => $submissions->min('solved_at')->format('c'),
                                    'solved_by' => [
                                        'username' => $member->user_name,
                                        'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null,
                                    ],
                                    'first_blood' => $isFirstAllSolved,
                                    'flag_type' => 'multiple_all',
                                    'flags_solved' => $submissions->count()
                                ]);
                                
                                $totalPoints += $points;
                            }
                        } else if ($challenge->flag_type === 'multiple_individual') {
                            // Count points for each flag individually
                            foreach ($submissions as $flagSubmission) {
                                $flag = $flagSubmission->eventChallangeFlag;
                                $points = $flag->bytes;
                                
                                // Check if this was first blood for this flag
                                $isFirstBlood = $flagSubmission->solved_at->eq(
                                    EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                        ->where('solved', true)
                                        ->oldest('solved_at')
                                        ->first()
                                        ->solved_at
                                );
                                
                                if ($isFirstBlood) {
                                    $points += $flag->firstBloodBytes;
                                }
                                
                                $solvedChallenges->push([
                                    'title' => $challenge->title . ' - ' . $flag->name,
                                    'points' => $points,
                                    'solved_at' => $flagSubmission->solved_at->format('c'),
                                    'solved_by' => [
                                        'username' => $member->user_name,
                                        'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null,
                                    ],
                                    'first_blood' => $isFirstBlood,
                                    'flag_type' => 'multiple_individual'
                                ]);
                                
                                $totalPoints += $points;
                            }
                        }
                    }
                }

                return [
                    'team_name' => $team->name,
                    'team_icon_url' => $team->icon ? url('storage/team-icons/' . $team->icon) : null,
                    'total_points' => $totalPoints,
                    'solved_challenges' => $solvedChallenges->sortByDesc('solved_at')->values(),
                    'members' => $team->users->map(function($member) {
                        return [
                            'username' => $member->user_name,
                            'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null
                        ];
                    }),
                    'member_count' => $team->users->count()
                ];
            })
            ->sortByDesc('total_points')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'teams' => $teams,
                'last_updated' => now()->format('c')
            ]
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
        ])->find($eventChallengeUuid);
        
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
                    'solved_at' => $isSolved ? $this->formatInUserTimezone($challenge->solvedBy->first()->pivot->solved_at) : null,
                    'attempts' => $isSolved ? $challenge->solvedBy->first()->pivot->attempts : 0
                ]
            ]);
        }
        
        // For multiple flag challenges
        $solvedFlags = $challenge->flags()
            ->whereHas('solvedBy', function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->get();
        
        $allFlagsSolved = $solvedFlags->count() === $challenge->flags->count();
        
        $flags = $challenge->flags->map(function($flag) use ($solvedFlags) {
            $isFlagSolved = $solvedFlags->contains('id', $flag->id);
            $solvedByUser = $flag->solvedBy->isNotEmpty();
            
            return [
                'id' => $flag->id,
                'name' => $flag->name,
                'description' => $flag->description,
                'bytes' => $flag->bytes,
                'first_blood_bytes' => $flag->firstBloodBytes,
                'solved' => $isFlagSolved,
                'solved_at' => $solvedByUser ? $this->formatInUserTimezone($flag->solvedBy->first()->pivot->solved_at) : null,
                'attempts' => $solvedByUser ? $flag->solvedBy->first()->pivot->attempts : 0
            ];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'challenge_id' => $challenge->id,
                'challenge_title' => $challenge->title,
                'flag_type' => $challenge->flag_type,
                'total_flags' => $challenge->flags->count(),
                'solved_flags' => $solvedFlags->count(),
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
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
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
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
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
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->count();
                
                // Get first blood for this flag
                $flagFirstBlood = null;
                if ($flagSolvedCount > 0) {
                    $flagFirstSolver = $challenge->submissions()
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                    if ($flagFirstSolver) {
                        $flagFirstBloodUser = User::where('uuid', $flagFirstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                        if ($flagFirstBloodUser) {
                            $flagFirstBlood = [
                                'user_name' => $flagFirstBloodUser->user_name,
                                'profile_image' => $flagFirstBloodUser->profile_image ? asset('storage/' . $flagFirstBloodUser->profile_image) : null,
                                'solved_at' => $flagFirstSolver->created_at,
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
        }

        // Convert to array and remove unwanted fields
        $challengeData = $challenge->toArray();
        $challengeData['solved_count'] = $solvedCount;
        $challengeData['description'] = $challenge->description;
        $challengeData['file'] = $challenge->file;
        $challengeData['link'] = $challenge->link;
        
        // Remove flags from response
        unset($challengeData['flags']);

        return response()->json([
            'status' => 'success',
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
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
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
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the current user's team
        $user = Auth::user();
        $team = EventTeam::where('event_uuid', $eventUuid)
            ->whereHas('users', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['users'])
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
        $teamMembers = User::whereIn('uuid', $team->users->pluck('user_uuid'))
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
                                $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                    ->where('solved', true)
                                    ->orderBy('solved_at')
                                    ->first();
                                
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
                                ->orderBy('solved_at')
                                ->first();
                                
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
                                'first_blood_points' => $flagFirstBloodPoints,
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
                    'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null,
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
                    'member_count' => $team->users->count()
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
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the current user's team
        $user = Auth::user();
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('users', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['users'])
            ->first();
            
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team for this event'
            ], 404);
        }
        
        // Get team members who solved this challenge
        $teamMembers = collect();
        
        foreach ($team->users as $member) {
            // Get if the user solved the challenge (for single flag type)
            $solvedChallenge = null;
            $solvedFlags = collect();
            $points = 0;
            $firstBloodPoints = 0;
            $solvedFlagsData = [];
            $solvedAt = null;
            $isFirstBlood = false;
            $hasSolved = false;
            
            // Check if member solved the challenge (single flag type)
            if ($challenge->flag_type === 'single') {
                $solvedChallenge = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                    ->where('user_uuid', $member->uuid)
                    ->where('solved', true)
                    ->first();
                
                if ($solvedChallenge) {
                    $hasSolved = true;
                    $solvedAt = $solvedChallenge->solved_at;
                    $points = $challenge->bytes;
                    
                    // Check if this was first blood
                    $firstSolver = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                        ->where('solved', true)
                        ->orderBy('solved_at')
                        ->first();
                        
                    if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                        $firstBloodPoints = $challenge->firstBloodBytes;
                        $points += $firstBloodPoints;
                        $isFirstBlood = true;
                    }
                }
            } 
            // For multiple flag types
            else {
                // Get solved flags for this challenge
                $solvedFlags = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $challenge->flags->pluck('id'))
                    ->where('user_uuid', $member->uuid)
                    ->where('solved', true)
                    ->get();
                
                if ($solvedFlags->isNotEmpty()) {
                    $hasSolved = true;
                }
                
                // For multiple_all type
                if ($challenge->flag_type === 'multiple_all') {
                    // Check if all flags are solved
                    $allFlagsSolved = $solvedFlags->pluck('event_challange_flag_id')->unique()->count() === $challenge->flags->count();
                    
                    if ($allFlagsSolved) {
                        $points = $challenge->bytes;
                        $solvedAt = $solvedFlags->max('solved_at');
                        
                        // Check if this was first blood for all flags
                        $isFirstBlood = true;
                        foreach ($challenge->flags as $flag) {
                            $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                ->where('solved', true)
                                ->orderBy('solved_at')
                                ->first();
                            
                            if (!$firstSolver || $firstSolver->user_uuid !== $member->uuid) {
                                $isFirstBlood = false;
                                break;
                            }
                        }
                        
                        if ($isFirstBlood) {
                            $firstBloodPoints = $challenge->firstBloodBytes;
                            $points += $firstBloodPoints;
                        }
                    }
                }
                // For multiple_individual type
                else if ($challenge->flag_type === 'multiple_individual') {
                    if ($solvedFlags->isNotEmpty()) {
                        $solvedAt = $solvedFlags->min('solved_at');
                    }
                    
                    foreach ($solvedFlags as $flagSubmission) {
                        $flag = $challenge->flags->firstWhere('id', $flagSubmission->event_challange_flag_id);
                        
                        if ($flag) {
                            $flagPoints = $flag->bytes;
                            $flagFirstBlood = false;
                            
                            // Check if this was first blood for this flag
                            $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                ->where('solved', true)
                                ->orderBy('solved_at')
                                ->first();
                                
                            if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                                $flagFirstBlood = true;
                                $flagPoints += $flag->firstBloodBytes;
                                $firstBloodPoints += $flag->firstBloodBytes;
                            }
                            
                            $points += $flagPoints;
                            
                            $solvedFlagsData[] = [
                                'id' => $flag->id,
                                'name' => $flag->name,
                                'points' => $flagPoints,
                                'is_first_blood' => $flagFirstBlood,
                                'solved_at' => $this->formatInUserTimezone($flagSubmission->solved_at)
                            ];
                        }
                    }
                    
                    // Set first blood flag if any flag was first blood
                    $isFirstBlood = $firstBloodPoints > 0;
                }
            }
            
            // Only include members who have solved the challenge or at least one flag
            if (!$hasSolved && empty($solvedFlagsData)) {
                continue;
            }
            
            $teamMembers->push([
                'user_uuid' => $member->uuid,
                'user_name' => $member->user_name,
                'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null,
                'points' => $points,
                'first_blood_points' => $firstBloodPoints,
                'is_first_blood' => $isFirstBlood,
                'solved_at' => $solvedAt ? $this->formatInUserTimezone($solvedAt) : null,
                'solved_flags' => $solvedFlagsData,
                'flags_count' => count($solvedFlagsData),
                'all_flags_solved' => $challenge->flag_type !== 'multiple_all' || 
                                     ($challenge->flags->count() > 0 && count($solvedFlagsData) === $challenge->flags->count())
            ]);
        }
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'team' => [
                    'name' => $team->name,
                    'icon' => $team->icon ? url('storage/team-icons/' . $team->icon) : null,
                    'member_count' => $team->users->count()
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
                'members' => $teamMembers->sortByDesc('solved_at')->values(),
                'total_solvers' => $teamMembers->count(),
                'last_updated' => now()->format('c')
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
