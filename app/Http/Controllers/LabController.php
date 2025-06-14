<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Challange;
use App\Models\LabCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function lastThreeChallengesByLabUUID($lab_uuid)
    {
        $lab = Lab::where('uuid', $lab_uuid)->first(['uuid', 'name', 'ar_name', 'description', 'ar_description']);
        
        $challenges = Challange::whereHas('labCategory', function ($query) use ($lab_uuid) {
            $query->where('lab_uuid', $lab_uuid);
        })
        ->with(['category:uuid,icon'])
        ->latest()
        ->take(3)
        ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // Remove any flag values for security
            unset($challenge->flag);
            unset($challenge->flags);
        });

        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count(),
            'lab' => $lab
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
                'ar_title' => $category->ar_title,
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
        $labCategory = LabCategory::where('uuid', $categoryUUID)->first(['uuid', 'title', 'ar_title', 'lab_uuid', 'image','description', 'ar_description']);
        
        if (!$labCategory) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lab category not found'
            ], 404);
        }
        
        // Get lab information
        $lab = Lab::where('uuid', $labCategory->lab_uuid)->first(['uuid', 'name', 'ar_name']);
        
        $challenges = Challange::with(['category:uuid,icon', 'flags'])
            ->where('lab_category_uuid', $categoryUUID)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $totalChallenges = $challenges->count();
        $totalBytes = 0;
        $userSolvedChallenges = 0;
        $userEarnedBytes = 0;
        $user = auth('api')->user();
        
        // First calculate total bytes correctly
        foreach ($challenges as $challenge) {
            if ($challenge->flag_type === 'single' || $challenge->flag_type === 'multiple_all') {
                $totalBytes += $challenge->bytes;
            } else if ($challenge->flag_type === 'multiple_individual' && $challenge->flags) {
                // For multiple_individual, sum up bytes for each flag
                foreach ($challenge->flags as $flag) {
                    $totalBytes += $flag->bytes;
                }
            }
        }
        
        // Debug total bytes
        $userEarnedBytes = 0; // Reset this to ensure we calculate it properly
        
        // Track which challenges and flags have already been processed to prevent double-counting
        $processedChallenges = [];
        $processedFlags = [];
        
        // Now calculate user-specific statistics
        foreach ($challenges as $challenge) {
            $challengeUuid = $challenge->uuid;
            
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // Count distinct users who have solved, not all submissions
            $solvedCount = $challenge->submissions()
                ->where('solved', true)
                ->distinct('user_uuid')
                ->count('user_uuid');
            
            // For multiple_all type, recalculate solved count to only include users who solved all flags
            if ($challenge->flag_type === 'multiple_all' && $challenge->flags && $challenge->flags->count() > 0) {
                $totalFlags = $challenge->flags->count();
                
                // Get all users who have solved at least one flag
                $usersWithSubmissions = $challenge->submissions()
                    ->where('solved', true)
                    ->select('user_uuid')
                    ->distinct()
                    ->get()
                    ->pluck('user_uuid');
                
                // Count only users who have solved all flags
                $usersCompletedAll = 0;
                foreach ($usersWithSubmissions as $userUuid) {
                    $userSolvedFlags = $challenge->submissions()
                        ->where('user_uuid', $userUuid)
                        ->where('solved', true)
                        ->distinct('flag')
                        ->count('flag');
                    
                    if ($userSolvedFlags === $totalFlags) {
                        $usersCompletedAll++;
                    }
                }
                
                $solvedCount = $usersCompletedAll;
            }
            
            // Add solved count for this challenge
            $challenge->solved_count = $solvedCount;
                
            // Add solved status for authenticated user
            if ($user) {
                if ($challenge->flag_type === 'single') {
                    $challenge->solved = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->exists();
                } else if ($challenge->flag_type === 'multiple_all') {
                    $totalFlags = $challenge->flags->count();
                    if ($totalFlags > 0) {
                        $userSolvedFlags = $challenge->submissions()
                            ->where('user_uuid', $user->uuid)
                            ->where('solved', true)
                            ->distinct('flag')
                            ->count('flag');
                        $challenge->solved = ($userSolvedFlags === $totalFlags);
                    } else {
                        $challenge->solved = false;
                    }
                } else if ($challenge->flag_type === 'multiple_individual') {
                    $challenge->solved = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->exists();
                } else {
                    $challenge->solved = false;
                }
            } else {
                $challenge->solved = false;
            }
            
            // Check user solved status and calculate earned bytes
            if ($user) {
                if ($challenge->flag_type === 'single') {
                    // Skip if already processed this challenge
                    if (in_array($challengeUuid, $processedChallenges)) {
                        continue;
                    }
                    
                    // For single flag type, check if user solved it
                    $isSolved = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->exists();
                    
                    // Set the solved status for this challenge
                    $challenge->solved = $isSolved;
                    
                    if ($isSolved) {
                        $userSolvedChallenges++;
                        
                        // Check if user got first blood
                        $firstSolver = $challenge->submissions()
                            ->where('solved', true)
                            ->orderBy('created_at', 'asc')
                            ->first();
                        
                        if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                            // For first blood, add both regular and first blood bytes
                            $userEarnedBytes = ($challenge->firstBloodBytes);
                        } else {
                            // For regular solves, just add the regular bytes
                            $userEarnedBytes = $challenge->bytes;
                        }
                    }
                    
                    // Mark as processed
                    $processedChallenges[] = $challengeUuid;
                } else if ($challenge->flag_type === 'multiple_all') {
                    // Skip if already processed this challenge
                    if (in_array($challengeUuid, $processedChallenges)) {
                        continue;
                    }
                    
                    // For multiple_all, count users who solved all flags
                    $totalFlags = $challenge->flags->count();
                    
                    if ($totalFlags > 0) {
                        // Get the user's submissions for this challenge
                        $userSubmissions = $challenge->submissions()
                            ->where('user_uuid', $user->uuid)
                            ->where('solved', true)
                            ->get();
                            
                        // Count unique flags solved
                        $userSolvedFlags = $userSubmissions->pluck('flag')->unique()->count();
                        
                        // Set the solved status for this challenge - all flags must be solved
                        $challenge->solved = ($userSolvedFlags === $totalFlags);
                        
                        if ($userSolvedFlags === $totalFlags) {
                            $userSolvedChallenges++;
                            
                            // Simple implementation: just add the challenge bytes value
                            // This ensures we use the challenge's own bytes value
                            
                            // First try to get the challenge from DB to ensure we have fresh values
                            $freshChallenge = Challange::where('uuid', $challengeUuid)->first();
                            
                            if ($freshChallenge) {
                                // Use values from freshly loaded challenge
                                $challengeBytes = $freshChallenge->bytes;
                                $challengeFirstBloodBytes = $freshChallenge->firstBloodBytes;
                            } else {
                                // Fallback to current challenge object
                                $challengeBytes = $challenge->bytes;
                                $challengeFirstBloodBytes = $challenge->firstBloodBytes;
                            }
                            
                            // Check if the user got first blood for the entire challenge
                            $isFirstBlood = true;
                            
                            // User must be first to solve ALL flags for first blood
                            foreach ($challenge->flags as $flag) {
                                $firstSolver = $challenge->submissions()
                                    ->where('flag', $flag->flag)
                                    ->where('solved', true)
                                    ->orderBy('created_at', 'asc')
                                    ->first();
                                
                                // If not first for any flag, not first blood overall
                                if (!$firstSolver || $firstSolver->user_uuid !== $user->uuid) {
                                    $isFirstBlood = false;
                                    break;
                                }
                            }
                            
                            if ($isFirstBlood) {
                                // For first blood, add the challenge's firstBloodBytes value
                                $userEarnedBytes += $challengeFirstBloodBytes;
                            } else {
                                // For regular solves, add the challenge's bytes value
                                $userEarnedBytes += $challengeBytes;
                            }
                        }
                    }
                    
                    // Mark as processed
                    $processedChallenges[] = $challengeUuid;
                } else if ($challenge->flag_type === 'multiple_individual') {
                    // For multiple_individual, check each flag individually
                    $hasAtLeastOneFlag = false;
                    
                    foreach ($challenge->flags as $flag) {
                        // Create a unique key for this flag
                        $flagKey = $challengeUuid . '-' . $flag->flag;
                        
                        // Skip if already processed this flag
                        if (in_array($flagKey, $processedFlags)) {
                            continue;
                        }
                        
                        $isFlagSolved = $challenge->submissions()
                            ->where('user_uuid', $user->uuid)
                            ->where('flag', $flag->flag)
                            ->where('solved', true)
                            ->exists();
                        
                        if ($isFlagSolved) {
                            $hasAtLeastOneFlag = true;
                            
                            // Check if user got first blood for this flag
                            $firstSolver = $challenge->submissions()
                                ->where('flag', $flag->flag)
                                ->where('solved', true)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            
                            if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                                // For first blood, add both regular and first blood bytes
                                $userEarnedBytes += ($flag->bytes + $flag->firstBloodBytes);
                            } else {
                                // For regular solves, just add the regular bytes
                                $userEarnedBytes += $flag->bytes;
                            }
                        }
                        
                        // Mark this flag as processed
                        $processedFlags[] = $flagKey;
                    }
                    
                    // For multiple_individual, consider solved if at least one flag is solved
                    $challenge->solved = $hasAtLeastOneFlag;
                    
                    if ($hasAtLeastOneFlag) {
                        $userSolvedChallenges++;
                    }
                    
                    // Mark the challenge as processed
                    $processedChallenges[] = $challengeUuid;
                }
            }
            
            // For multiple flag types, format the flags data
            if ($challenge->flag_type !== 'single' && $challenge->flags) {
                $challenge->flags_count = $challenge->flags->count();
                
                // Only add metadata about flags, not the actual flags
                $challenge->flags_data = $challenge->flags->map(function ($flag) use ($challenge) {
                    // Get solved count for this specific flag
                    $flagSolvedCount = $challenge->submissions()
                        ->where('flag', $flag->flag)
                        ->where('solved', true)
                        ->distinct('user_uuid')
                        ->count('user_uuid');
                        
                    return [
                        'id' => $flag->id,
                        'name' => $flag->name,
                        'description' => $flag->description,
                        'bytes' => $flag->bytes,
                        'first_blood_bytes' => $flag->firstBloodBytes,
                        'solved_count' => $flagSolvedCount
                    ];
                });
            }
            
            // Remove flag data that should not be exposed
            unset($challenge->flag);
            unset($challenge->flags);
        }

        $lastChallenge = $challenges->last();
        
        // Ensure percentage never exceeds 100%
        $solvedPercentage = $totalBytes > 0 ? min(100, round(($userEarnedBytes / $totalBytes) * 100, 2)) : 0;
        
        // Ensure solved challenges never exceeds total challenges
        $userSolvedChallenges = min($userSolvedChallenges, $totalChallenges);
        
        // IMPROVED FIX: Calculate correct bytes for all challenge types
        if ($user) {
            $recalculatedBytes = 0;
            
            // Re-fetch all challenges to ensure we have the latest data
            $userChallenges = Challange::with(['submissions', 'flags'])
                ->where('lab_category_uuid', $categoryUUID)
                ->get();
            
            foreach ($userChallenges as $challenge) {
                // Single flag challenges
                if ($challenge->flag_type === 'single') {
                    $isSolved = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->exists();
                    
                    if ($isSolved) {
                        // Check if user got first blood
                        $firstSolver = $challenge->submissions()
                            ->where('solved', true)
                            ->orderBy('created_at', 'asc')
                            ->first();
                        
                        if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                            $recalculatedBytes += $challenge->firstBloodBytes;
                        } else {
                            $recalculatedBytes += $challenge->bytes;
                        }
                    }
                }
                // Multiple_all challenges - all flags must be solved
                else if ($challenge->flag_type === 'multiple_all' && $challenge->flags && $challenge->flags->count() > 0) {
                    $totalFlags = $challenge->flags->count();
                    $solvedFlags = $challenge->submissions()
                        ->where('user_uuid', $user->uuid)
                        ->where('solved', true)
                        ->distinct('flag')
                        ->count('flag');
                    
                    if ($solvedFlags === $totalFlags) {
                        // Check for first blood on all flags
                        $isFirstBlood = true;
                        
                        foreach ($challenge->flags as $flag) {
                            $firstSolver = $challenge->submissions()
                                ->where('flag', $flag->flag)
                                ->where('solved', true)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            
                            if (!$firstSolver || $firstSolver->user_uuid !== $user->uuid) {
                                $isFirstBlood = false;
                                break;
                            }
                        }
                        
                        if ($isFirstBlood) {
                            $recalculatedBytes += $challenge->firstBloodBytes;
                        } else {
                            $recalculatedBytes += $challenge->bytes;
                        }
                    }
                }
                // Multiple_individual challenges - each flag is a separate challenge
                else if ($challenge->flag_type === 'multiple_individual' && $challenge->flags) {
                    foreach ($challenge->flags as $flag) {
                        $isFlagSolved = $challenge->submissions()
                            ->where('user_uuid', $user->uuid)
                            ->where('flag', $flag->flag)
                            ->where('solved', true)
                            ->exists();
                        
                        if ($isFlagSolved) {
                            // Check if user got first blood for this flag
                            $firstSolver = $challenge->submissions()
                                ->where('flag', $flag->flag)
                                ->where('solved', true)
                                ->orderBy('created_at', 'asc')
                                ->first();
                            
                            if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                                $recalculatedBytes += $flag->firstBloodBytes;
                            } else {
                                $recalculatedBytes += $flag->bytes;
                            }
                        }
                    }
                }
            }
            
            // Use recalculated bytes
            $userEarnedBytes = $recalculatedBytes;
        }
        
        // Ensure earned bytes never exceeds total bytes
        $userEarnedBytes = min($userEarnedBytes, $totalBytes);
        
        return response()->json([
            'status' => 'success',
            'lab' => [
                'uuid' => $lab->uuid,
                'name' => $lab->name,
                'ar_name' => $lab->ar_name
            ],
            'lab_category' => [
                'uuid' => $labCategory->uuid,
                'title' => $labCategory->title,
                'ar_title' => $labCategory->ar_title,
                'description'=> $labCategory->description,
                'ar_description'=> $labCategory->ar_description,
                'image' => $labCategory->image ? asset('storage/' . $labCategory->image) : null
            ],
     
            'stats' => [
                'total_challenges' => $totalChallenges,
                'solved_challenges' => $userSolvedChallenges,
'solved_percentage' => $totalBytes > 0 ? ($userEarnedBytes / $totalBytes) * 100 : 0,
                'total_bytes' => $totalBytes,
                'earned_bytes' => $userEarnedBytes
            ],
            'data' => $challenges,
            'count' => $totalChallenges,
            'last_challenge' => $lastChallenge
        ]);
    }

    public function getChallenge($uuid)
    {
        $challenge = Challange::with(['category:uuid,icon', 'flags', 'labCategory:uuid,title,ar_title,lab_uuid'])
            ->where('uuid', $uuid)
            ->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Get lab data through lab category
        $lab = null;
        if ($challenge->labCategory && $challenge->labCategory->lab_uuid) {
            $lab = Lab::where('uuid', $challenge->labCategory->lab_uuid)
                ->first(['uuid', 'name', 'ar_name']);
        }

        $challenge->category_icon = $challenge->category->icon ?? null;
        unset($challenge->category);
        $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);

        // Convert file path to full URL if file exists
        if ($challenge->file) {
            $challenge->file = asset('storage/' . $challenge->file);
        }

        // Count distinct users who have solved, not all submissions
        $solvedCount = $challenge->submissions()
            ->where('solved', true)
            ->distinct('user_uuid')
            ->count('user_uuid');
        
        // For multiple_all type, recalculate solved count to only include users who solved all flags
        if ($challenge->flag_type === 'multiple_all' && $challenge->flags && $challenge->flags->count() > 0) {
            $totalFlags = $challenge->flags->count();
            
            // Get all users who have solved at least one flag
            $usersWithSubmissions = $challenge->submissions()
                ->where('solved', true)
                ->select('user_uuid')
                ->distinct()
                ->get()
                ->pluck('user_uuid');
            
            // Count only users who have solved all flags
            $usersCompletedAll = 0;
            foreach ($usersWithSubmissions as $userUuid) {
                $userSolvedFlags = $challenge->submissions()
                    ->where('user_uuid', $userUuid)
                    ->where('solved', true)
                    ->distinct('flag')
                    ->count('flag');
                
                if ($userSolvedFlags === $totalFlags) {
                    $usersCompletedAll++;
                }
            }
            
            $solvedCount = $usersCompletedAll;
        }
        
        // Add flag information
        $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
        
        // Get first blood information
        $firstBlood = null;
        if ($solvedCount > 0) {
            if ($challenge->flag_type === 'multiple_all' && $challenge->flags && $challenge->flags->count() > 0) {
                $totalFlags = $challenge->flags->count();
                $allFlagsList = $challenge->flags->pluck('flag')->toArray();
                
                // Find users who solved all flags and get the first one
                $usersWithAllFlags = [];
                $userSubmissions = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->groupBy('user_uuid');
                    
                foreach ($userSubmissions as $userUuid => $submissions) {
                    $userSolvedFlags = $submissions->pluck('flag')->unique()->values()->toArray();
                    if (count(array_intersect($userSolvedFlags, $allFlagsList)) === $totalFlags) {
                        // This user solved all flags, record when they solved the last flag
                        $lastFlagTime = $submissions->sortBy('created_at')->last()->created_at;
                        $usersWithAllFlags[$userUuid] = $lastFlagTime;
                    }
                }
                
                // Find the first user who solved all flags
                if (!empty($usersWithAllFlags)) {
                    asort($usersWithAllFlags); // Sort by timestamp
                    $firstSolverUuid = array_key_first($usersWithAllFlags);
                    $firstBloodUser = User::where('uuid', $firstSolverUuid)->first(['uuid', 'user_name', 'profile_image']);
                    
                    if ($firstBloodUser) {
                        $firstBlood = [
                            'user_name' => $firstBloodUser->user_name,
                            'profile_image' => $firstBloodUser->profile_image ? asset('storage/' . $firstBloodUser->profile_image) : null,
                            'solved_at' => $usersWithAllFlags[$firstSolverUuid],
                        ];
                    }
                }
            } else {
                // Original logic for other challenge types
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
        }
        $challenge->first_blood = $firstBlood;
        
        // Check if challenge is available
        if ($challenge->available === false) {
            // Hide sensitive data if challenge is not available
            $challenge->file = null;
            $challenge->link = null;
            $challenge->flags_data = null;
            $challenge->flags_count = null;
        } else {
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

                        'description'=> $flag->description,
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
        }

        // Convert to array and remove unwanted fields
        $challengeData = $challenge->toArray();
        $challengeData['solved_count'] = $solvedCount;
        
        // Remove flags from response
        unset($challengeData['flags']);
        
        // Hide the flag value for security
        unset($challengeData['flag']);
        
        // Prepare lab category data
        $labCategoryData = null;
        if ($challenge->labCategory) {
            $labCategoryData = [
                'uuid' => $challenge->labCategory->uuid,
                'title' => $challenge->labCategory->title,
                'ar_title' => $challenge->labCategory->ar_title,
            ];
        }
        unset($challengeData['lab_category']);
        
        // Prepare lab data
        $labData = null;
        if ($lab) {
            $labData = [
                'uuid' => $lab->uuid,
                'name' => $lab->name,
                'ar_name' => $lab->ar_name,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $challengeData,
            'lab_category' => $labCategoryData,
            'lab' => $labData
        ]);
    }

    public function lastThreeChallenges()
    {
        $challenges = Challange::with(['category:uuid,icon'])
            ->latest()
            ->take(3)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // Remove any flag values for security
            unset($challenge->flag);
            unset($challenge->flags);
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
                    'all_flags_solved' => $solved,
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
        
        // Use a database transaction to prevent race conditions
        return DB::transaction(function() use ($request) {
            $challenge = Challange::where('uuid', $request->challange_uuid)->lockForUpdate()->first();
            
            if (!$challenge->available) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This challenge is not available',
                ], 400);
            }
            
            // Check if user has already solved this challenge - only for single flag type
            if ($challenge->flag_type === 'single' && 
                $challenge->submissions()->where('user_uuid', auth('api')->user()->uuid)->where('solved', true)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already solved this challenge',
                    'data' => [
                        'is_first_blood' => false
                    ]
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
                    'message' => 'The flag is incorrect',
                    'data' => [
                        'is_first_blood' => false
                    ]
                ], 400);
            } 
            // Handle multiple flag types
            else {
                // Load flags with a lock to prevent race conditions
                $challenge->load(['flags' => function($query) {
                    $query->lockForUpdate();
                }]);
                
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
                            'flag_type' => $challenge->flag_type,
                            'is_first_blood' => false
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
                        'message' => 'You have already solved this flag',
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
                $isFirstBlood = false;  // Initialize the flag for all cases
                
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
                        
                        // Find all users who have solved all flags and when they completed the last flag
                        $usersWithAllFlags = [];
                        $userSubmissions = $challenge->submissions()
                            ->where('solved', true)
                            ->orderBy('created_at', 'asc')
                            ->get()
                            ->groupBy('user_uuid');
                            
                        foreach ($userSubmissions as $userUuid => $submissions) {
                            $userSolvedFlags = $submissions->pluck('flag')->unique()->values()->toArray();
                            if (count(array_intersect($userSolvedFlags, $allFlags)) === $totalFlags) {
                                // This user solved all flags, record when they solved the last flag
                                $lastFlagTime = $submissions->sortBy('created_at')->last()->created_at;
                                $usersWithAllFlags[$userUuid] = $lastFlagTime;
                            }
                        }
                        
                        // Sort by completion time and check if current user was first
                        if (!empty($usersWithAllFlags)) {
                            asort($usersWithAllFlags); // Sort by timestamp
                            $firstSolverUuid = array_key_first($usersWithAllFlags);
                            $isFirstBlood = ($firstSolverUuid === auth('api')->user()->uuid);
                            
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
                                'all_flags_solved' => true,
                                'points' => $points,
                                'first_blood_points' => $firstBloodPoints,
                                'is_first_blood' => $isFirstBlood,
                            ]
                        ], 200);
                    } else {
                        // If not all flags are solved, just return a message without data array
                        return response()->json([
                            'status' => 'success',
                            'message' => 'The flag is correct',
                            'data' => [
                                'flag_type' => $challenge->flag_type,
                                'flag_name' => $matchedFlag->name
                            ]
                        ], 200);
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
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'The flag is correct',
                        'data' => [
                            'flag_type' => $challenge->flag_type,
                            'flag_name' => $matchedFlag->name,
                            'points' => $points,
                            'first_blood_points' => $firstBloodPoints,
                            'is_first_blood' => $firstBloodPoints > 0,
                        ]
                    ], 200);
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
                
                // This should never be reached, but added as a fallback
                return response()->json([
                    'status' => 'success',
                    'message' => 'The flag is correct',
                    'data' => [
                        'flag_type' => $challenge->flag_type,
                        'flag_name' => $matchedFlag->name,
                        'is_first_blood' => false,  // Add is_first_blood in the fallback case
                    ]
                ], 200);
            }
        });
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
                    'all_flags_solved' => $solved,
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
                        'description' => $solvedFlag->description,
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
        ->sortByDesc(function($user) {
            // Create a composite sorting key that prioritizes points first, then first_blood_count
            return [$user['points'], $user['first_blood_count']];
        })
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

    /**
     * Get leaderboard specific to a challenge
     * 
     * @param string $uuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeLeaderboard($uuid)
    {
        $challenge = Challange::where('uuid', $uuid)->with('flags')->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        $usersWhoSolved = User::whereHas('submissions', function($query) use ($uuid) {
                $query->whereHas('challange', function($q) use ($uuid) {
                    $q->where('uuid', $uuid);
                })
                ->where('solved', true);
            })
            ->with(['submissions' => function($query) use ($uuid) {
                $query->whereHas('challange', function($q) use ($uuid) {
                    $q->where('uuid', $uuid);
                })
                ->where('solved', true)
                ->with('challange.flags');
            }])
            ->get()
            ->map(function($user) use ($challenge) {
                $points = 0;
                $firstBloodPoints = 0;
                $isFirstBlood = false;
                $solvedAt = null;
                $solvedFlags = [];
                
                // For single flag type
                if ($challenge->flag_type === 'single' || !$challenge->flag_type) {
                    $submission = $user->submissions->first();
                    if ($submission) {
                        $solvedAt = $submission->created_at;
                        
                        // Check if this user was first blood
                        $firstSolver = $challenge->submissions()
                            ->where('solved', true)
                            ->orderBy('created_at')
                            ->first();
                            
                        if ($firstSolver && $firstSolver->user_uuid === $user->uuid) {
                            $points = $challenge->bytes + $challenge->firstBloodBytes;
                            $firstBloodPoints = $challenge->firstBloodBytes;
                            $isFirstBlood = true;
                        } else {
                            $points = $challenge->bytes;
                        }
                    }
                }
                // Handle multiple_all flag type
                else if ($challenge->flag_type === 'multiple_all') {
                    // Get unique solved flags
                    $solvedFlagsList = $user->submissions
                        ->pluck('flag')
                        ->unique()
                        ->values()
                        ->toArray();
                        
                    // Check if all required flags are solved
                    $requiredFlags = $challenge->flags->pluck('flag')->toArray();
                    $requiredFlagsCount = count($requiredFlags);
                    
                    // Check if user has solved all flags
                    $allFlagsSolved = true;
                    
                    // First check count
                    if (count($solvedFlagsList) < $requiredFlagsCount) {
                        $allFlagsSolved = false;
                    } else {
                        // Then check each required flag is in the solved list
                        foreach ($requiredFlags as $requiredFlag) {
                            if (!in_array($requiredFlag, $solvedFlagsList)) {
                                $allFlagsSolved = false;
                                break;
                            }
                        }
                    }
                    
                    // Add solved flags information (regardless of whether all flags are solved)
                    foreach ($solvedFlagsList as $flag) {
                        $flagObj = $challenge->flags->firstWhere('flag', $flag);
                        if ($flagObj) {
                            $solvedFlags[] = [
                                'id' => $flagObj->id,
                                'name' => $flagObj->name,
                                'solved_at' => $user->submissions
                                    ->where('flag', $flag)
                                    ->first()
                                    ->created_at
                            ];
                        }
                    }
                    
                    // Always add points based on solved flags, full points if all solved
                    if ($allFlagsSolved) {
                        // Get the latest solved time (when all flags were completed)
                        $solvedAt = $user->submissions->max('created_at');
                        
                        // Check if this user was the first to solve all flags
                        // Get all users who have solved all flags and when they completed them
                        $usersWithAllFlags = [];
                        $allUsers = User::whereHas('submissions', function($query) use ($challenge) {
                                $query->where('challange_uuid', $challenge->uuid)
                                    ->where('solved', true);
                            })
                            ->with(['submissions' => function($query) use ($challenge) {
                                $query->where('challange_uuid', $challenge->uuid)
                                    ->where('solved', true);
                            }])
                            ->get();
                            
                        foreach ($allUsers as $checkUser) {
                            // Get unique solved flags for this user
                            $checkUserSolvedFlags = $checkUser->submissions
                                ->pluck('flag')
                                ->unique()
                                ->values()
                                ->toArray();
                                
                            // Check if this user has solved all flags
                            if (count(array_intersect($checkUserSolvedFlags, $requiredFlags)) === $requiredFlagsCount) {
                                // This user solved all flags, record when they solved the last flag
                                $lastFlagTime = $checkUser->submissions->max('created_at');
                                $usersWithAllFlags[$checkUser->uuid] = $lastFlagTime;
                            }
                        }
                        
                        // Sort by completion time and check if current user was first
                        $isFirstBlood = false;
                        if (!empty($usersWithAllFlags)) {
                            asort($usersWithAllFlags); // Sort by timestamp
                            $firstSolverUuid = array_key_first($usersWithAllFlags);
                            $isFirstBlood = ($firstSolverUuid === $user->uuid);
                        }
                        
                        if ($isFirstBlood) {
                            $points = $challenge->bytes + $challenge->firstBloodBytes;
                            $firstBloodPoints = $challenge->firstBloodBytes;
                        } else {
                            $points = $challenge->bytes;
                        }
                    } else {
                        // Add partial points if not all flags are solved
                        // This ensures users show up on the leaderboard with the flags they've solved
                        $points = 0; // We'll still set this to 0 but show the user with their solved flags
                        $solvedAt = $user->submissions->max('created_at');
                    }
                }
                // Handle multiple_individual flag type
                else if ($challenge->flag_type === 'multiple_individual') {
                    // For multiple_individual, create a separate entry for each flag
                    $userEntries = [];
                    $uniqueFlags = $user->submissions->pluck('flag')->unique();
                    
                    foreach ($uniqueFlags as $flagValue) {
                        // Find the flag in the challenge's flags collection
                        $flag = $challenge->flags->firstWhere('flag', $flagValue);
                        
                        if ($flag) {
                            // Get submission for this flag
                            $submission = $user->submissions
                                ->where('flag', $flagValue)
                                ->first();
                                
                            // Check if the user was first to solve this flag
                            $firstSolver = $challenge->submissions()
                                ->where('flag', $flagValue)
                                ->where('solved', true)
                                ->orderBy('created_at')
                                ->first();
                                
                            $flagFirstBlood = $firstSolver && $firstSolver->user_uuid === $user->uuid;
                            
                            // Calculate points for this flag
                            $flagPoints = $flag->bytes;
                            $flagFirstBloodPoints = 0;
                            
                            if ($flagFirstBlood) {
                                $flagFirstBloodPoints = $flag->firstBloodBytes;
                                $flagPoints += $flagFirstBloodPoints;
                            }
                            
                            // Create entry for this flag
                            $userEntries[] = [
                                'user_name' => $user->user_name,
                                'profile_image' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
                                'points' => $flagPoints,
                                'first_blood_points' => $flagFirstBloodPoints,
                                'is_first_blood' => $flagFirstBlood,
                                'solved_at' => $submission->created_at,
                                'flags_count' => 0,
                                'solved_flags' => [],
                                'flag_name' => $flag->name,
                                'flag_name_ar' => $flag->ar_name,
                                'flag_description' => $flag->description
                            ];
                        }
                    }
                    
                    // Return the entries as a collection
                    return $userEntries;
                }
                
                return [
                    'user_name' => $user->user_name,
                    'profile_image' => $user->profile_image ? asset('storage/' . $user->profile_image) : null,
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood,
                    'solved_at' => $solvedAt,
                    'solved_flags' => $solvedFlags,
                    'flags_count' => count($solvedFlags),
                    'flag_name' => $challenge->flag_type === 'single' ? $challenge->title : null,
                    'flag_description' => $challenge->flag_type === 'single' ? $challenge->description : null
                ];
            })
            // Include all users who have attempted the challenge, not just those with full points
            ->filter(function($user) use ($challenge) {
                if ($challenge->flag_type === 'multiple_all') {
                    // Only include users who have solved all flags
                    $requiredFlags = $challenge->flags->count();
                    return $user['points'] > 0; // If they earned points, they solved all flags
                }
                return true; // Include all users for other challenge types
            })
            ->when($challenge->flag_type === 'multiple_individual', function($collection) {
                // Flatten the collection since multiple_individual returns arrays of entries
                return $collection->flatten(1);
            })
            ->sortBy([
                ['points', 'desc'],
                ['solved_at', 'asc'] // Sort by solved time ascending (earliest first) when points are equal
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $usersWhoSolved,
            'challenge' => [
                'uuid' => $challenge->uuid,
                'title' => $challenge->title,
                'flag_type' => $challenge->flag_type,
                'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
                'total_solvers' => $usersWhoSolved->count()
            ]
        ]);
    }
}