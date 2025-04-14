<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventChallange;
use App\Models\EventChallangeSubmission;
use App\Models\EventChallangeFlagSubmission;
use App\Models\EventTeam;
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

        // Handle different flag types
        if ($challenge->flag_type === 'single') {
            // Single flag logic
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
        } else {
            // Multiple flags logic - check against all flags
            $submission = $request->submission;
            $matchedFlag = null;
            
            // Find the flag that matches the submission
            foreach ($challenge->flags as $flag) {
                if ($submission === $flag->flag) {
                    $matchedFlag = $flag;
                    break;
                }
            }
            
            if (!$matchedFlag) {
                // No matching flag found, record the attempt
                $flagSubmission = EventChallangeFlagSubmission::create([
                    'event_challange_flag_id' => $challenge->flags->first()->id, // Use first flag for tracking attempts
                    'user_uuid' => Auth::user()->uuid,
                    'submission' => $submission,
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

            // Get or create flag submission record
            $flagSubmission = EventChallangeFlagSubmission::firstOrNew([
                'event_challange_flag_id' => $matchedFlag->id,
                'user_uuid' => Auth::user()->uuid
            ]);

            $flagSubmission->attempts += 1;
            $flagSubmission->submission = $submission;
            $flagSubmission->solved = true;
            $flagSubmission->solved_at = now();

            // Check for first blood
            $isFirstBlood = !EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                ->where('solved', true)
                ->exists();

            $points = 0;
            $allFlagsSolved = false;
            
            if ($challenge->flag_type === 'multiple_individual') {
                // Individual points for each flag
                $points = $matchedFlag->bytes;
                if ($isFirstBlood) {
                    $points = $matchedFlag->firstBloodBytes;
                }
            } else {
                // Points only after all flags are solved
                $allFlagsSolved = $challenge->flags()
                    ->whereDoesntHave('solvedBy', function($query) {
                        $query->where('user_uuid', Auth::user()->uuid);
                    })
                    ->count() === 0;
                
                if ($allFlagsSolved) {
                    // Check if this is the first time all flags are solved
                    $isFirstAllSolved = !EventChallangeSubmission::where('event_challange_id', $challenge->id)
                        ->where('solved', true)
                        ->exists();
                    
                    if ($isFirstAllSolved) {
                        // Create a submission record for the challenge
                        $challengeSubmission = new EventChallangeSubmission([
                            'event_challange_id' => $challenge->id,
                            'user_uuid' => Auth::user()->uuid,
                            'solved' => true,
                            'solved_at' => now(),
                            'attempts' => 0
                        ]);
                        $challengeSubmission->save();
                        
                        $points = $isFirstAllSolved ? $challenge->firstBloodBytes : $challenge->bytes;
                    }
                }
            }

            $flagSubmission->save();

            // Get all solved flags for this challenge
            $solvedFlags = $challenge->flags()
                ->whereHas('solvedBy', function($query) {
                    $query->where('user_uuid', Auth::user()->uuid);
                })
                ->get();

            // Get all flags for this challenge
            $allFlags = $challenge->flags()->get();

            // Check if all flags are solved
            $allFlagsSolved = $solvedFlags->count() === $allFlags->count();

            return response()->json([
                'status' => 'success',
                'message' => 'Correct! Flag solved.',
                'data' => [
                    'points' => $points,
                    'first_blood' => $isFirstBlood,
                    'attempts' => $flagSubmission->attempts,
                    'flag_type' => $challenge->flag_type,
                    'flag_id' => $matchedFlag->id,
                    'flag_name' => $matchedFlag->name,
                    'solved_flags' => $solvedFlags->count(),
                    'total_flags' => $allFlags->count(),
                    'all_flags_solved' => $allFlagsSolved,
                    'total_points' => $challenge->flag_type === 'multiple_all' && $allFlagsSolved ? $challenge->bytes : ($challenge->flag_type === 'multiple_individual' ? $points : 0),
                    'first_blood_points' => $challenge->flag_type === 'multiple_all' && $allFlagsSolved ? $challenge->firstBloodBytes : ($challenge->flag_type === 'multiple_individual' && $isFirstBlood ? $matchedFlag->firstBloodBytes : 0),
                    'solved_flags_data' => $solvedFlags->map(function($solvedFlag) {
                        return [
                            'id' => $solvedFlag->id,
                            'name' => $solvedFlag->name,
                            'bytes' => $solvedFlag->bytes,
                            'first_blood_bytes' => $solvedFlag->firstBloodBytes,
                            'solved_at' => $this->formatInUserTimezone($solvedFlag->solvedBy->first()->pivot->solved_at),
                            'attempts' => $solvedFlag->solvedBy->first()->pivot->attempts
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
    public function show ($eventChallengeUuid)
    {
        return response()->json([
            'status' => 'success',
            'data' => '555'
        ]);
    }
    public function getChallengeDetails($eventChallengeUuid)
    {
        return response()->json([
            'status' => 'success',
            'data' => '555'
        ]);
        $challenge = EventChallange::where('id', $eventChallengeUuid)
            ->with([
                'category:uuid,name,icon', 
                'solvedBy' => function($query) {
                    $query->where('user_uuid', Auth::user()->uuid);
                }, 
                'flags' => function($query) {
                    $query->orderBy('order', 'asc');
                }, 
                'flags.solvedBy' => function($query) {
                    $query->where('user_uuid', Auth::user()->uuid);
                },
                'submissions' => function($query) {
                    $query->where('user_uuid', Auth::user()->uuid);
                }
            ])
            ->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $isSolved = $challenge->solvedBy->isNotEmpty();
        
        // Handle different flag types
        $flagData = [];
        
        if ($challenge->flag_type === 'single') {
            $flagData = [
                'type' => 'single',
                'solved' => $isSolved,
                'solved_at' => $isSolved ? $this->formatInUserTimezone($challenge->solvedBy->first()->pivot->solved_at) : null,
                'attempts' => $isSolved ? $challenge->solvedBy->first()->pivot->attempts : 0,
                'submissions' => $challenge->submissions->map(function($submission) {
                    return [
                        'submission' => $submission->submission,
                        'solved' => $submission->solved,
                        'solved_at' => $submission->solved ? $this->formatInUserTimezone($submission->solved_at) : null,
                        'attempts' => $submission->attempts,
                        'created_at' => $this->formatInUserTimezone($submission->created_at)
                    ];
                })
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
                    
                    // Get flag submissions
                    $flagSubmissions = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                        ->where('user_uuid', Auth::user()->uuid)
                        ->orderBy('created_at', 'desc')
                        ->get();
                    
                    return [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                        'solved' => $isFlagSolved,
                        'solved_at' => $solvedByUser ? $this->formatInUserTimezone($flag->solvedBy->first()->pivot->solved_at) : null,
                        'attempts' => $solvedByUser ? $flag->solvedBy->first()->pivot->attempts : 0,
                        'submissions' => $flagSubmissions->map(function($submission) {
                            return [
                                'submission' => $submission->submission,
                                'solved' => $submission->solved,
                                'solved_at' => $submission->solved ? $this->formatInUserTimezone($submission->solved_at) : null,
                                'attempts' => $submission->attempts,
                                'created_at' => $this->formatInUserTimezone($submission->created_at)
                            ];
                        })
                    ];
                })
            ];
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
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
}
