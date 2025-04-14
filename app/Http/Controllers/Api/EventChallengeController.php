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

class EventChallengeController extends Controller
{
    use HandlesTimezones;

    public function listChallenges($eventUuid)
    {
        $challenges = EventChallange::where('event_uuid', $eventUuid)
            ->with(['category:uuid,name,icon', 'solvedBy' => function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }, 'flags' => function($query) {
                $query->orderBy('order', 'asc');
            }, 'flags.solvedBy' => function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }])
            ->get()
            ->map(function ($challenge) {
                $isSolved = $challenge->solvedBy->isNotEmpty();
                
                // Handle different flag types
                $flagData = [];
                
                if ($challenge->flag_type === 'single') {
                    $flagData = [
                        'type' => 'single',
                        'solved' => $isSolved,
                        'solved_at' => $isSolved ? $this->formatInUserTimezone($challenge->solvedBy->first()->pivot->solved_at) : null,
                        'attempts' => $isSolved ? $challenge->solvedBy->first()->pivot->attempts : 0
                    ];
                } else {
                    // For multiple flags
                    $solvedFlags = $challenge->flags()
                        ->whereHas('solvedBy', function($query) {
                            $query->where('user_uuid', Auth::user()->uuid);
                        })
                        ->get();
                    
                    $allFlagsSolved = $solvedFlags->count() === $challenge->flags->count();
                    
                    $flagData = [
                        'type' => $challenge->flag_type,
                        'total_flags' => $challenge->flags->count(),
                        'solved_flags' => $solvedFlags->count(),
                        'all_solved' => $allFlagsSolved,
                        'flags' => $challenge->flags->map(function($flag) use ($solvedFlags) {
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
                        })
                    ];
                }
                
                return [
                    'id' => $challenge->id,
                    'title' => $challenge->title,
                    'description' => $challenge->description,
                    'category' => [
                        'title' => $challenge->category->name,
                        'icon_url' => $challenge->category_icon_url
                    ],
                    'difficulty' => $challenge->difficulty,
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                    'file' => $challenge->file,
                    'link' => $challenge->link,
                    'solved' => $isSolved,
                    'solved_at' => $isSolved ? $this->formatInUserTimezone($challenge->solvedBy->first()->pivot->solved_at) : null,
                    'attempts' => $isSolved ? $challenge->solvedBy->first()->pivot->attempts : 0,
                    'flag_data' => $flagData
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $challenges
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

        // Get user's team for this event
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('members', function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must be in a team to submit solutions'
            ], 400);
        }

        // Check if event is active
        if (!$this->isNowBetween($challenge->event->start_date, $challenge->event->end_date)) {
            if (now() < $challenge->event->start_date) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Event has not started yet'
                ], 400);
            }

            if (now() > $challenge->event->end_date) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Event has ended'
                ], 400);
            }
        }

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
                if ($isFirstBlood) {
                    $points = $challenge->firstBloodBytes;
                }

                $submission->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Correct! Challenge solved.',
                    'data' => [
                        'points' => $points,
                        'first_blood' => $isFirstBlood,
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
                }
            } else if ($challenge->flag_type === 'multiple_all') {
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
                    }
                }
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
                    'attempts' => $flagSubmission->attempts,
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
        $teams = EventTeam::where('event_uuid', $eventUuid)
            ->with(['members.submissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'members.flagSubmissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallangeFlag.eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'members:uuid,user_name,profile_image'])
            ->get()
            ->map(function($team) {
                $totalPoints = 0;
                $solvedChallenges = collect();

                foreach ($team->members as $member) {
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
                    'members' => $team->members->map(function($member) {
                        return [
                            'username' => $member->user_name,
                            'profile_image' => $member->profile_image ? url('storage/profile_images/' . $member->profile_image) : null
                        ];
                    }),
                    'member_count' => $team->members->count()
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
        $challenge = EventChallange::where('uuid', $eventChallengeUuid)->firstOrFail();
        
        // Get the event
        $event = $challenge->event;
        
        // Check if the event has started
        if (!$event->hasStarted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has not started yet.'
            ], 403);
        }
        
        // Get the team of the current user
        $team = $event->teams()->whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->first();
        
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team in this event.'
            ], 403);
        }
        
        // Get all flags for this challenge
        $allFlags = $challenge->flags;
        
        // Get solved flags for this team
        $solvedFlags = $allFlags->filter(function ($flag) use ($team) {
            return $flag->solvedBy->contains($team->id);
        });
        
        // Check if all flags are solved
        $allFlagsSolved = $solvedFlags->count() === $allFlags->count();
        
        // Calculate points based on flag type
        $points = 0;
        $firstBloodPoints = 0;
        
        if ($challenge->flag_type === 'multiple_all') {
            // For multiple_all, points are only awarded when all flags are solved
            if ($allFlagsSolved) {
                $points = $challenge->bytes;
                
                // Check if this team has first blood for all flags
                $hasFirstBlood = true;
                foreach ($allFlags as $flag) {
                    $firstSolver = $flag->solvedBy()->orderBy('event_flag_solved.solved_at', 'asc')->first();
                    if (!$firstSolver || $firstSolver->id !== $team->id) {
                        $hasFirstBlood = false;
                        break;
                    }
                }
                
                if ($hasFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
                }
            }
        } else if ($challenge->flag_type === 'multiple_individual') {
            // For multiple_individual, points are awarded for each flag
            foreach ($solvedFlags as $flag) {
                $points += $flag->bytes;
                
                // Check if this team has first blood for this flag
                $firstSolver = $flag->solvedBy()->orderBy('event_flag_solved.solved_at', 'asc')->first();
                if ($firstSolver && $firstSolver->id === $team->id) {
                    $firstBloodPoints += $flag->firstBloodBytes;
                }
            }
        } else {
            // For single flag type
            $flag = $allFlags->first();
            if ($flag && $solvedFlags->contains($flag)) {
                $points = $flag->bytes;
                
                // Check if this team has first blood
                $firstSolver = $flag->solvedBy()->orderBy('event_flag_solved.solved_at', 'asc')->first();
                if ($firstSolver && $firstSolver->id === $team->id) {
                    $firstBloodPoints = $flag->firstBloodBytes;
                }
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'is_solved' => $allFlagsSolved || $solvedFlags->isNotEmpty(),
                'flag_type' => $challenge->flag_type,
                'solved_flags' => $solvedFlags->count(),
                'total_flags' => $allFlags->count(),
                'all_flags_solved' => $allFlagsSolved,
                'points' => $points,
                'first_blood_points' => $firstBloodPoints,
                'solved_flags_data' => $solvedFlags->map(function($solvedFlag) use ($team) {
                    $solvedAt = $solvedFlag->solvedBy()->where('team_id', $team->id)->first()->pivot->solved_at;
                    $attempts = $solvedFlag->solvedBy()->where('team_id', $team->id)->first()->pivot->attempts;
                    
                    return [
                        'id' => $solvedFlag->id,
                        'name' => $solvedFlag->name,
                        'bytes' => $solvedFlag->bytes,
                        'first_blood_bytes' => $solvedFlag->firstBloodBytes,
                        'solved_at' => $this->formatInUserTimezone($solvedAt),
                        'attempts' => $attempts,
                        'is_first_blood' => $solvedFlag->solvedBy()->orderBy('event_flag_solved.solved_at', 'asc')->first()->id === $team->id
                    ];
                })
            ]
        ]);
    }


    public function showChallenge($challengeUuid) {
        $challenge = EventChallange::with(['category:uuid,icon', 'flags'])
            ->where('id', $challengeUuid)
            ->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $challenge->category_icon = $challenge->category->icon ?? null;
        unset($challenge->category);

        // Get solved count for the challenge
        $solvedCount = $challenge->submissions()->where('solved', true)->count();
        
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
                        'profile_image' => $firstBloodUser->profile_image ? asset('storage/profile_images/' . $firstBloodUser->profile_image) : null,
                        'solved_at' => $firstSolver->created_at,
                    ];
                }
            }
        }
        $challenge->first_blood = $firstBlood;
        
        // Add flag information
        $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $challenge->flags_data = [[
                'bytes' => $challenge->bytes,
                'first_blood_bytes' => $challenge->firstBloodBytes,
                'solved_count' => $solvedCount,
            ]];
        }
        // For multiple_all type
        else if ($challenge->flag_type === 'multiple_all') {
            $challenge->flags_data = [[
                'bytes' => $challenge->bytes,
                'first_blood_bytes' => $challenge->firstBloodBytes,
                'solved_count' => $solvedCount,
            ]];
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
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                    if ($flagFirstSolver) {
                        $flagFirstBloodUser = User::where('uuid', $flagFirstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                        if ($flagFirstBloodUser) {
                            $flagFirstBlood = [
                                'user_name' => $flagFirstBloodUser->user_name,
                                'profile_image' => $flagFirstBloodUser->profile_image ? asset('storage/profile_images/' . $flagFirstBloodUser->profile_image) : null,
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
        
        // Remove flags from response
        unset($challengeData['flags']);

        return response()->json([
            'status' => 'success',
            'data' => $challengeData
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
}
