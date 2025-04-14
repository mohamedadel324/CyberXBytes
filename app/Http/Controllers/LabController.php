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
        
        // Remove flags from response
        unset($challengeData['flags']);

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
                // Create the submission
                $submission = $challenge->submissions()->create([
                    'flag' => $request->solution,
                    'ip' => $request->getClientIp(),
                    'user_uuid' => auth('api')->user()->uuid,
                    'solved' => true
                ]);
                
                // Calculate points and check for first blood
                $points = $challenge->bytes;
                $firstBloodPoints = 0;
                
                // Get first solver for this challenge - must get correct submission
                $firstSolver = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                // Check if this user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === auth('api')->user()->uuid;
                if ($isFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'The flag is correct',
                    'data' => [
                        'flag_type' => 'single',
                        'points' => $points,
                        'first_blood_points' => $firstBloodPoints,
                        'is_first_blood' => $isFirstBlood
                    ]
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
            $submission = $challenge->submissions()->create([
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
                // Get all flags available for this challenge
                $allFlags = $challenge->flags->pluck('flag')->toArray();
                $totalFlags = count($allFlags);
                
                // Get all flags this specific user has solved
                $userSolvedFlags = $challenge->submissions()
                    ->where('user_uuid', auth('api')->user()->uuid)
                    ->where('solved', true)
                    ->pluck('flag')
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
                // Always award base points for solving the flag
                $points = $matchedFlag->bytes;
                
                // Check if this is first blood for this flag
                $firstSolver = $challenge->submissions()
                    ->where('flag', $matchedFlag->flag)
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                // Check if the current user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === auth('api')->user()->uuid;
                
                if ($isFirstBlood) {
                    $firstBloodPoints = $matchedFlag->firstBloodBytes;
                }
                
                // Also check if all flags are now solved
                $solvedFlags = $challenge->submissions()
                    ->where('user_uuid', auth('api')->user()->uuid)
                    ->where('solved', true)
                    ->pluck('flag')
                    ->toArray();
                
                $allFlags = $challenge->flags->pluck('flag')->toArray();
                $allFlagsSolved = count(array_intersect($solvedFlags, $allFlags)) === count($allFlags);
            } else if ($challenge->flag_type === 'single') {
                // For single flag type, if we get here it means the flag was solved
                $allFlagsSolved = true;
                $points = $challenge->bytes;
                
                // Check if this is first blood
                $firstSolver = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                
                // Check if the current user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === auth('api')->user()->uuid;
                
                if ($isFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
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
                    'is_first_blood' => $firstBloodPoints > 0,
                   
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
                
            // Check if the user was the first solver
            $isFirstBlood = false;
            $firstBloodPoints = 0;
            
            if ($solved) {
                $firstSolver = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                    
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === $user->uuid;
                
                if ($isFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
                }
            }
                
            return response()->json([
                'status' => 'success',
                'solved' => $solved,
                'data' => [
                    'flag_type' => 'single',
                    'points' => $solved ? $challenge->bytes : 0,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood
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
            $allFlagsSolved = count(array_intersect($solvedFlagValues, $allFlagValues)) === count($allFlagValues);
        }
        
        $points = 0;
        $firstBloodPoints = 0;
        
        if ($challenge->flag_type === 'multiple_all') {
            // For multiple_all, points are only awarded when all flags are solved
            if ($allFlagsSolved) {
                // Always award base points if all flags are solved
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
                'debug' => $challenge->flag_type === 'multiple_all' ? [
                    'solved_flags_by_user' => $solvedFlags->pluck('flag')->toArray(),
                    'all_flags_available' => $allFlags->pluck('flag')->toArray()
                ] : null,
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
            $query->where('solved', true)
                  ->with(['challange' => function($q) {
                      $q->with('flags');
                  }]);
        }])
        ->get()
        ->map(function($user) {
            $points = 0;
            $firstBloodCount = 0;
            $processedChallenges = [];
            $solvedChallenges = [];
            
            foreach ($user->submissions as $submission) {
                // Skip if challange relationship is missing
                if (!$submission->challange) {
                    continue;
                }
                
                $challenge = $submission->challange;
                $challengeUuid = $challenge->uuid;
                
                // Handle single flag type
                if ($challenge->flag_type === 'single' || !$challenge->flag_type) {
                    // Only process each challenge once
                    if (in_array($challengeUuid, $processedChallenges)) {
                        continue;
                    }
                    
                    $firstBloodSubmission = $challenge->submissions()
                        ->where('solved', true)
                        ->orderBy('created_at')
                        ->first();
                    
                    if ($firstBloodSubmission && $firstBloodSubmission->user_uuid === $user->uuid) {
                        $points += $challenge->firstBloodBytes ?? 0;
                        $firstBloodCount++;
                    } else {
                        $points += $challenge->bytes ?? 0;
                    }
                    
                    $processedChallenges[] = $challengeUuid;
                    $solvedChallenges[] = $challengeUuid;
                }
                // Handle multiple_all flag type
                else if ($challenge->flag_type === 'multiple_all') {
                    // Only process each challenge once
                    if (in_array($challengeUuid, $processedChallenges)) {
                        continue;
                    }
                    
                    // Get all flags for this challenge
                    if (!$challenge->flags || $challenge->flags->isEmpty()) {
                        continue;
                    }
                    
                    $allFlagCount = $challenge->flags->count();
                    
                    // Get all flags this user has solved for this challenge
                    $userSolvedFlags = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->pluck('flag')
                        ->unique()
                        ->count();
                    
                    // Only award points if the user has solved all flags
                    if ($userSolvedFlags === $allFlagCount) {
                        // Check if the user got first blood for all flags
                        $isFirstBlood = true;
                        foreach ($challenge->flags as $flag) {
                            $firstSolver = $challenge->submissions()
                                ->where('flag', $flag->flag)
                                ->where('solved', true)
                                ->orderBy('created_at')
                                ->first();
                            
                            if (!$firstSolver || $firstSolver->user_uuid !== $user->uuid) {
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
                        
                        $solvedChallenges[] = $challengeUuid;
                    }
                    
                    $processedChallenges[] = $challengeUuid;
                }
                // Handle multiple_individual flag type
                else if ($challenge->flag_type === 'multiple_individual') {
                    // For individual flags, track which flags the user has solved
                    $flagKey = $challengeUuid . '-' . $submission->flag;
                    
                    // Skip if we've already processed this specific flag
                    if (in_array($flagKey, $processedChallenges)) {
                        continue;
                    }
                    
                    // Find the flag in the challenge's flags collection
                    $flag = null;
                    if ($challenge->flags) {
                        $flag = $challenge->flags->firstWhere('flag', $submission->flag);
                    }
                    
                    // If we found a matching flag
                    if ($flag) {
                        // Check if the user was first to solve this flag
                        $firstSolver = $challenge->submissions()
                            ->where('flag', $submission->flag)
                            ->where('solved', true)
                            ->orderBy('created_at')
                            ->first();
                        
                        if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                            $points += $flag->firstBloodBytes ?? 0;
                            $firstBloodCount++;
                        } else {
                            $points += $flag->bytes ?? 0;
                        }
                    } else {
                        // Fallback in case the flag object can't be found
                        $points += $challenge->bytes ?? 0;
                    }
                    
                    // Mark this flag as processed
                    $processedChallenges[] = $flagKey;
                    
                    // Add the challenge to solved challenges if not already there
                    if (!in_array($challengeUuid, $solvedChallenges)) {
                        $solvedChallenges[] = $challengeUuid;
                    }
                }
            }
            
            // Calculate solved challenges count
            $challengesCount = count($solvedChallenges);
            
            return [
                'user_name' => $user->user_name,
                'profile_image' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
                'points' => $points,
                'challenges_solved' => $challengesCount,
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

    /**
     * Get challenge flags and solved status for a specific user
     * 
     * @param string $uuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeStatusAndFlags($uuid)
    {
        $challenge = Challange::where('uuid', $uuid)->with('flags')->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $user = auth('api')->user();
        
        $result = [
            'flag_type' => $challenge->flag_type,
            'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
        ];
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $solved = $challenge->submissions()
                ->where('user_uuid', $user->uuid)
                ->where('solved', true)
                ->exists();
                
            $result['solved'] = $solved;
            
            // Single flag data
            $result['flag_data'] = [
                'bytes' => $challenge->bytes,
                'first_blood_bytes' => $challenge->firstBloodBytes,
                'solved_count' => $challenge->submissions()->where('solved', true)->count(),
            ];
            
            // Add first blood information if solved
            if ($solved) {
                $firstSolver = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->first();
                    
                $result['is_first_blood'] = $firstSolver && $firstSolver->user_uuid === $user->uuid;
                
                $solvedAt = $challenge->submissions()
                    ->where('user_uuid', $user->uuid)
                    ->where('solved', true)
                    ->first()
                    ->created_at;
                    
                $result['solved_at'] = $solvedAt;
            }
        } 
        // For multiple flag types
        else {
            $flagsData = [];
            
            foreach ($challenge->flags as $flag) {
                $isSolved = $challenge->submissions()
                    ->where('user_uuid', $user->uuid)
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->exists();
                
                $flagData = [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved' => $isSolved,
                    'solved_count' => $challenge->submissions()
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->count(),
                ];
                
                // Add additional data if the flag is solved
                if ($isSolved) {
                    $flagSubmission = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->first();
                        
                    $flagData['solved_at'] = $flagSubmission->created_at;
                    
                    // Check if this user got first blood for this flag
                    $firstSolver = $challenge->submissions()
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->orderBy('created_at', 'asc')
                        ->first();
                        
                    $flagData['is_first_blood'] = $firstSolver && $firstSolver->user_uuid === $user->uuid;
                }
                
                $flagsData[] = $flagData;
            }
            
            // Count how many flags are solved
            $solvedCount = count(array_filter($flagsData, function($flag) {
                return $flag['solved'];
            }));
            
            $result['flags'] = $flagsData;
            
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
}