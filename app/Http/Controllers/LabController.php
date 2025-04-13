<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Challange;
use App\Models\LabCategory;
use App\Models\User;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function getAllLabs()
    {
        $labs = Lab::whereHas('labCategories.challanges')
            ->withCount(['labCategories', 'labCategories as challenge_count' => function ($query) {
                $query->withCount('challanges');
            }])
            ->get()
            ->map(function ($lab) {
                return [
                    'uuid' => $lab->uuid,
                    'name' => $lab->name,
                    'ar_name' => $lab->ar_name,
                    'category_count' => $lab->lab_categories_count,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $labs,
        ]);
    }
    public function getallLabCategoriesByLabUUID($uuid)
    {
        $lab = Lab::where('uuid', $uuid)->first(['uuid', 'name', 'ar_name', 'description', 'ar_description']);

        $categories = LabCategory::where('lab_uuid', $uuid)
            ->withCount('challanges')
            ->get();

        $lastThreeChallenges = Challange::whereHas('labCategory', function ($query) use ($uuid) {
            $query->where('lab_uuid', $uuid);
        })
        ->with('category:uuid,icon')
        ->latest()
        ->take(3)
        ->get();

        $categoriesWithCount = $categories->map(function ($category) {
            return [
                'uuid' => $category->uuid,
                'title' => $category->title,
                'image' => $category->image ? asset('storage/' . $category->image) : null,
                'challenges_count' => $category->challanges_count,
            ];
        });

        $lastThreeChallengesData = $lastThreeChallenges->map(function ($challenge) {
            return [
                'title' => $challenge->title,
                'description' => $challenge->description,
                'difficulty' => $this->translateDifficulty($challenge->difficulty),
                'category_icon' => $challenge->category->icon ? asset('storage/' . $challenge->category->icon) : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'lab' => $lab,
            'data' => $categoriesWithCount,
            'challenges_count' => $categories->sum('challanges_count'),
            'last_three_challenges' => $lastThreeChallengesData
        ]);
    }
    public function getAllChallenges()
    {
        $challenges = Challange::with(['category:uuid,icon', 'flags'])->get();
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // For multiple flag types, format the flags data
            if ($challenge->flag_type !== 'single' && $challenge->flags) {
                $challenge->flags_data = $challenge->flags->map(function ($flag) {
                    return [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                    ];
                });
                $challenge->flags_count = $challenge->flags->count();
            }
        });
        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
    }

    public function getallLabCategories()
    {
        $categories = LabCategory::all();
        return response()->json([
            'status' => 'success',
            'data' => $categories,
            'challenges_count' => $categories->sum(function($category) {
                return $category->challanges()->count();
            })
        ]);
    }

    public function getChallengesByLabCategoryUUID($categoryUUID)
    {
        $challenges = Challange::with(['category:uuid,icon', 'flags'])
            ->where('lab_category_uuid', $categoryUUID)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // For multiple flag types, format the flags data
            if ($challenge->flag_type !== 'single' && $challenge->flags) {
                $challenge->flags_data = $challenge->flags->map(function ($flag) {
                    return [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                    ];
                });
                $challenge->flags_count = $challenge->flags->count();
            }
        });

        $lastChallenge = $challenges->last();
        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count(),
            'last_challenge' => $lastChallenge
        ]);
    }

    public function getChallengesByDifficulty($difficulty)
    {
        $challenges = Challange::with(['category:uuid,icon', 'flags'])
            ->where('difficulty', $difficulty)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // For multiple flag types, format the flags data
            if ($challenge->flag_type !== 'single' && $challenge->flags) {
                $challenge->flags_data = $challenge->flags->map(function ($flag) {
                    return [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                    ];
                });
                $challenge->flags_count = $challenge->flags->count();
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
    }

    public function getChallenge($uuid)
    {
        $challenge = Challange::with(['category:uuid,icon', 'flags'])
            ->where('uuid', $uuid)
            ->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $challenge->category_icon = $challenge->category->icon ?? null;
        unset($challenge->category);
        $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);

        $solvedCount = $challenge->submissions()->where('solved', true)->count();
        
        // Add flag information
        $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
        
        // For multiple flag types, format the flags data
        if ($challenge->flag_type !== 'single' && $challenge->flags) {
            $challenge->flags_data = $challenge->flags->map(function ($flag) {
                return [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                ];
            });
            $challenge->flags_count = $challenge->flags->count();
        }

        $challengeData = $challenge->toArray();
        $challengeData['solved_count'] = $solvedCount;

        return response()->json([
            'status' => 'success',
            'data' => $challengeData
        ]);
    }

    public function lastThreeChallenges()
    {
        $challenges = Challange::with(['category:uuid,icon', 'flags'])
            ->latest()
            ->take(3)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // For multiple flag types, format the flags data
            if ($challenge->flag_type !== 'single' && $challenge->flags) {
                $challenge->flags_data = $challenge->flags->map(function ($flag) {
                    return [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                    ];
                });
                $challenge->flags_count = $challenge->flags->count();
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
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

    /**
     * Get flags for a specific challenge
     * 
     * @param string $uuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeFlags($uuid)
    {
        $challenge = Challange::where('uuid', $uuid)->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
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
                    'flag' => $challenge->flag,
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
     * Get description for flag type
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

    /**
     * Get flags solved by the authenticated user for a specific challenge
     * 
     * @param string $uuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserSolvedFlags($uuid)
    {
        $challenge = Challange::where('uuid', $uuid)->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $user = auth('api')->user();
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $solved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('solved', true)
                ->exists();
                
            return response()->json([
                'status' => 'success',
                'data' => [
                    'flag_type' => 'single',
                    'solved' => $solved,
                    'flag' => $solved ? $challenge->flag : null,
                    'solved_at' => $solved ? $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->first()
                        ->created_at : null,
                ]
            ]);
        }
        
        // For multiple flag types
        $solvedFlags = collect();
        
        foreach ($challenge->flags as $flag) {
            $isSolved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('flag', $flag->flag)
                ->where('solved', true)
                ->exists();
                
            if ($isSolved) {
                $solvedAt = $challenge->submissions()
                    ->where('user_uuid', $user->uuid)
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->first()
                    ->created_at;
                    
                $solvedFlags->push([
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved_at' => $solvedAt,
                ]);
            }
        }
        
        // Check if all flags are solved
        $allFlagsSolved = $solvedFlags->count() === $challenge->flags->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'flag_type' => $challenge->flag_type,
                'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
                'all_flags_solved' => $allFlagsSolved,
                'solved_flags_count' => $solvedFlags->count(),
                'total_flags_count' => $challenge->flags->count(),
                'solved_flags' => $solvedFlags,
            ]
        ]);
    }

    /**
     * Check if the authenticated user has solved specific flags for a challenge
     * 
     * @param string $uuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkUserSolvedFlags($uuid)
    {
        $challenge = Challange::where('uuid', $uuid)->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $user = auth('api')->user();
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $solved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('solved', true)
                ->exists();
                
            return response()->json([
                'status' => 'success',
                'data' => [
                    'flag_type' => 'single',
                    'solved' => $solved,
                    'solved_at' => $solved ? $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->first()
                        ->created_at : null,
                ]
            ]);
        }
        
        // For multiple flag types
        $solvedFlags = collect();
        
        foreach ($challenge->flags as $flag) {
            $isSolved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('flag', $flag->flag)
                ->where('solved', true)
                ->exists();
                
            if ($isSolved) {
                $solvedAt = $challenge->submissions()
                    ->where('user_uuid', $user->uuid)
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->first()
                    ->created_at;
                    
                $solvedFlags->push([
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'solved_at' => $solvedAt,
                ]);
            }
        }
        
        // Check if all flags are solved
        $allFlagsSolved = $solvedFlags->count() === $challenge->flags->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'flag_type' => $challenge->flag_type,
                'all_flags_solved' => $allFlagsSolved,
                'solved_flags_count' => $solvedFlags->count(),
                'total_flags_count' => $challenge->flags->count(),
                'solved_flags' => $solvedFlags,
            ]
        ]);
    }

    public function SubmitChallange(Request $request)
    {
        $request->validate([
            'challange_uuid' => 'required|exists:challanges,uuid',
            'solution' => 'required|string',
        ]);
        $challenge = Challange::where('uuid', $request->challange_uuid)->first();
        
        // Check if user has already solved this challenge - only for single flag type
        if ($challenge->flag_type === 'single' && 
            $challenge->submissions()->where('user_uuid', auth('api')->user()->uuid)->where('solved', true)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already solved this challenge'
            ], 400);
        }
        
        // Handle single flag type
        if ($challenge->flag_type === 'single') {
            if($challenge->flag == $request->solution) {
                $challenge->submissions()->create([
                    'flag' => $request->solution,
                    'ip' => $request->getClientIp(),
                    'user_uuid' => auth('api')->user()->uuid,
                    'solved' => true
                ]);
                return response()->json([
                    'status' => 'success',
                    'message' => 'The flag is correct'
                ], 200);
            }

            $challenge->submissions()->create([
                'flag' => $request->solution,
                'ip' => $request->getClientIp(),
                'user_uuid' => auth('api')->user()->uuid,
                'solved' => false
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'The flag is incorrect'
            ], 400);
        } 
        // Handle multiple flag types
        else {
            // Find the flag that matches the submission
            $matchedFlag = null;
            foreach ($challenge->flags as $flag) {
                if ($request->solution === $flag->flag) {
                    $matchedFlag = $flag;
                    break;
                }
            }
            
            if (!$matchedFlag) {
                // No matching flag found, record the attempt
                $challenge->submissions()->create([
                    'flag' => $request->solution,
                    'ip' => $request->getClientIp(),
                    'user_uuid' => auth('api')->user()->uuid,
                    'solved' => false
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'The flag is incorrect',
                    'data' => [
                        'flag_type' => $challenge->flag_type
                    ]
                ], 400);
            }
            
            // Check if user has already solved this flag
            $flagSubmission = $challenge->submissions()
                ->where('user_uuid', auth('api')->user()->uuid)
                ->where('flag', $matchedFlag->flag)
                ->where('solved', true)
                ->first();

            if ($flagSubmission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already solved this flag'
                ], 400);
            }

            // Record the solved flag
            $challenge->submissions()->create([
                'flag' => $request->solution,
                'ip' => $request->getClientIp(),
                'user_uuid' => auth('api')->user()->uuid,
                'solved' => true
            ]);
            
            // Check if all flags are solved for multiple_all type
            $allFlagsSolved = false;
            $points = 0;
            $firstBloodPoints = 0;
            
            if ($challenge->flag_type === 'multiple_all') {
                $solvedFlags = $challenge->submissions()
                    ->where('user_uuid', auth('api')->user()->uuid)
                    ->where('solved', true)
                    ->pluck('flag')
                    ->toArray();
                
                $allFlags = $challenge->flags->pluck('flag')->toArray();
                
                // Fix: Check if all flags are solved by comparing arrays
                $allFlagsSolved = count(array_intersect($allFlags, $solvedFlags)) === count($allFlags);
                
                // For multiple_all, points are only awarded when all flags are solved
                if ($allFlagsSolved) {
                    $points = $challenge->bytes;
                    
                    // Check if this is first blood for all flags
                    $isFirstBlood = true;
                    foreach ($allFlags as $flag) {
                        $firstSolver = $challenge->submissions()
                            ->where('flag', $flag)
                            ->where('solved', true)
                            ->orderBy('created_at', 'asc')
                            ->first();
                            
                        if (!$firstSolver || $firstSolver->user_uuid !== auth('api')->user()->uuid) {
                            $isFirstBlood = false;
                            break;
                        }
                    }
                    
                    if ($isFirstBlood) {
                        $firstBloodPoints = $challenge->firstBloodBytes;
                    }
                }
            } else if ($challenge->flag_type === 'multiple_individual') {
                // For multiple_individual, points are awarded immediately for each flag
                $points = $matchedFlag->bytes;
                
                // Check if this is first blood for this flag
                $firstSolver = $challenge->submissions()
                    ->where('flag', $matchedFlag->flag)
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                    
                if ($firstSolver && $firstSolver->user_uuid === auth('api')->user()->uuid) {
                    $firstBloodPoints = $matchedFlag->firstBloodBytes;
                }
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'The flag is correct',
                'data' => [
                    'flag_type' => $challenge->flag_type,
                    'flag_name' => $matchedFlag->name,
                    'all_flags_solved' => $allFlagsSolved,
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $firstBloodPoints > 0
                ]
            ], 200);
        }
    }

    public function checkIfSolved(Request $request)
    {
        $request->validate([
            'challange_uuid' => 'required|exists:challanges,uuid',
        ]);
        
        $challenge = Challange::where('uuid', $request->challange_uuid)->first();
        $user = auth('api')->user();
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $solved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('solved', true)
                ->exists();
                
            return response()->json([
                'status' => 'success',
                'solved' => $solved,
                'data' => [
                    'flag_type' => 'single',
                    'points' => $solved ? $challenge->bytes : 0,
                    'first_blood_points' => $solved ? $challenge->firstBloodBytes : 0
                ]
            ]);
        }
        
        // For multiple flag types
        $allFlags = $challenge->flags;
        $solvedFlags = collect();
        
        foreach ($allFlags as $flag) {
            $isSolved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('flag', $flag->flag)
                ->where('solved', true)
                ->exists();
                
            if ($isSolved) {
                $solvedFlags->push($flag);
            }
        }
        
        // Fix: Ensure we're correctly determining if all flags are solved
        $allFlagsSolved = $solvedFlags->count() === $allFlags->count();
        
        // Double-check by comparing arrays of flags
        if ($allFlagsSolved) {
            $solvedFlagValues = $solvedFlags->pluck('flag')->toArray();
            $allFlagValues = $allFlags->pluck('flag')->toArray();
            $allFlagsSolved = count(array_intersect($allFlagValues, $solvedFlagValues)) === count($allFlagValues);
        }
        
        $points = 0;
        $firstBloodPoints = 0;
        
        if ($challenge->flag_type === 'multiple_all') {
            // For multiple_all, points are only awarded when all flags are solved
            if ($allFlagsSolved) {
                $points = $challenge->bytes;
                
                // Check if this user has first blood for all flags
                $hasFirstBlood = true;
                foreach ($allFlags as $flag) {
                    $firstSolver = $challenge->submissions()
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->orderBy('created_at', 'asc')
                        ->first();
                        
                    if (!$firstSolver || $firstSolver->user_uuid !== $user->uuid) {
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
                
                // Check if this user has first blood for this flag
                $firstSolver = $challenge->submissions()
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                    
                if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                    $firstBloodPoints += $flag->firstBloodBytes;
                }
            }
        }
        
        return response()->json([
            'status' => 'success',
            'solved' => $allFlagsSolved || $solvedFlags->isNotEmpty(),
            'data' => [
                'flag_type' => $challenge->flag_type,
                'solved_flags' => $solvedFlags->count(),
                'total_flags' => $allFlags->count(),
                'all_flags_solved' => $allFlagsSolved,
                'points' => $points,
                'first_blood_points' => $firstBloodPoints,
                'solved_flags_data' => $solvedFlags->map(function($solvedFlag) use ($user, $challenge) {
                    $solvedAt = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('flag', $solvedFlag->flag)
                        ->where('solved', true)
                        ->first()
                        ->created_at;
                        
                    $attempts = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('flag', $solvedFlag->flag)
                        ->count();
                        
                    $isFirstBlood = $challenge->submissions()
                        ->where('flag', $solvedFlag->flag)
                        ->where('solved', true)
                        ->orderBy('created_at', 'asc')
                        ->first()
                        ->user_uuid === $user->uuid;
                        
                    return [
                        'id' => $solvedFlag->id,
                        'name' => $solvedFlag->name,
                        'bytes' => $solvedFlag->bytes,
                        'first_blood_bytes' => $solvedFlag->firstBloodBytes,
                        'solved_at' => $solvedAt,
                        'attempts' => $attempts,
                        'is_first_blood' => $isFirstBlood
                    ];
                })
            ]
        ]);
    }

    public function getLeaderBoard()
    {
        $leaderboard = User::with(['submissions' => function($query) {
            $query->where('solved', true)->with('challange:uuid,firstBloodBytes,bytes');
        }])
        ->withCount(['submissions as challenges_solved' => function($query) {
            $query->where('solved', true);
        }])
        ->get()
        ->map(function($user) {
            $points = 0;
            $firstBloodCount = 0;
            
            foreach ($user->submissions as $submission) {
                $firstBloodSubmission = $submission->challange->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first();

                if ($firstBloodSubmission && $firstBloodSubmission->user_uuid === $submission->user_uuid) {
                    $points += $submission->challange->firstBloodBytes;
                    $firstBloodCount++;
                } else {
                    $points += $submission->challange->bytes;
                }
            }

            return [
                'user_name' => $user->user_name,
                'profile_image' => $user->profile_image,
                'points' => $points,
                'challenges_solved' => $user->challenges_solved,
                'first_blood_count' => $firstBloodCount,
            ];
        })
        ->sortByDesc('points')
        ->take(100)
        ->values();

        return response()->json([
            'status' => 'success',
            'data' => $leaderboard
        ]);
    }
}