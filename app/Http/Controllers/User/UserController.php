<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserSocialMedia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\RegistrationOtpMail;
use Illuminate\Validation\ValidationException;
use App\Models\Challange;
use App\Models\Submission;
use App\Models\PlayerTitle;
use App\Models\Lab;
use App\Models\ChallangeCategory;
use App\Events\UsersOnlineEvent;
class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        if ($user->profile_image) {
            $user->profile_image = url('storage/' . $user->profile_image);
        }
        $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id']);
        $user->socialMedia = $user->socialMedia()->first();
        return response()->json(['user' => $user]);
    }
    public function profileByUserName($user_name)
    {
        $user = User::where('user_name', $user_name)->firstOrFail();
        if ($user->profile_image) {
            $user->profile_image = url('storage/' . $user->profile_image);
        }
        $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id']);
        $user->socialMedia = $user->socialMedia()->first();
        return response()->json(['user' => $user]);
    }

    public function changeProfileData(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'user_name' => 'sometimes|required|string|min:2|max:15|regex:/^[a-zA-Z0-9]+$/|unique:users,user_name,' . auth('api')->user()->id,
            'country' => 'sometimes|required|string|max:255|regex:/^[a-zA-Z0-9\s\-\,\.\']+$/',
            'time_zone' => 'sometimes|required|string|timezone',
        ]);

        
        $request->user()->update($validatedData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $request->user()->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id'])
        ]);
    }

    public function changePassword(Request $request)
    {
        $validatedData = $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'],
        ]);

        $user = $request->user();

        if (!Hash::check($validatedData['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        $user->update(['password' => Hash::make($validatedData['new_password'])]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function changeSocialMediaLinks(Request $request)
    {
        $validatedData = $request->validate([
            'discord' => ['nullable', 'string', 'regex:/^https?:\/\/(?:www\.)?discord\.(?:gg|com)/'],
            'instagram' => ['nullable', 'string', 'regex:/^https?:\/\/(?:www\.)?instagram\.com/'],
            'twitter' => ['nullable', 'string', 'regex:/^https?:\/\/(?:www\.)?x\.com/'],
            'tiktok' => ['nullable', 'string', 'regex:/^https?:\/\/(?:www\.)?tiktok\.com/'],
            'youtube' => ['nullable', 'string', 'regex:/^https?:\/\/(?:www\.)?youtube\.com/'],
            'linkedIn' => ['nullable', 'string', 'regex:/^https?:\/\/(?:www\.)?linkedin\.com/'],
        ]);

        $socialMedia = $request->user()->socialMedia()->updateOrCreate(
            ['user_uuid' => $request->user()->uuid],
            $validatedData
        );

        return response()->json([
            'message' => 'Social media links updated successfully',
            'social_media' => $socialMedia
        ]);
    }

    public function unlinkSocialMedia(Request $request)
    {

        if(!$request->user()->socialMedia[0]) {
            return response()->json([
                'message' => 'No social media links found'
            ], 404);
        }
        $validatedData = $request->validate([
            'platform' => ['required', 'string', 'in:discord,instagram,twitter,tiktok,youtube,linkedIn'],
        ]);

        $socialMedia = $request->user()->socialMedia[0];
        
        if (!$socialMedia) {
            return response()->json([
                'message' => 'No social media links found'
            ], 404);
        }

        $platform = $validatedData['platform'];
        
        if (is_null($socialMedia->$platform)) {
            return response()->json([
                'message' => 'This social media link is already unlinked'
            ], 400);
        }

        $socialMedia->update([
            $platform => null
        ]);

        return response()->json([
            'message' => 'Social media link unlinked successfully',
            'social_media' => $socialMedia
        ]);
    }
    /**
     * @requestMediaType multipart/form-data
     */
    public function changeProfileImage(Request $request){
        $request->validate([
            'profile_image' => 'required|image'
        ]);
        $file = $request->file('profile_image');
        $path = $file->store('profile_images', 'public');
        $validatedData['profile_image'] = $path;
        $request->user()->update($validatedData);

        return response()->json([
            'message' => 'Profile image updated successfully',
            'profile_image' => asset('storage/' . $path)
        ]);
    }

    /**
     * Request email change by sending OTP to new email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEmailChange(Request $request)
    {
        try {
            $request->validate([
                'new_email' => 'required|email|unique:users,email'
            ]);

            $user = auth('api')->user();
            
            // Generate OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store OTP and new email in cache
            $cacheKey = "email_change:{$user->id}";
            Cache::put($cacheKey, [
                'new_email' => $request->new_email,
                'otp' => $otp,
                'expires_at' => now()->addMinutes(5),
                'attempts' => 0
            ], now()->addMinutes(30));

            // Send OTP to new email synchronously
            Mail::to($request->new_email)->send(new RegistrationOtpMail(['email' => $request->new_email], $otp));

            return response()->json([
                'message' => 'OTP has been sent to your new email address.',
                'expires_in' => 300 // 5 minutes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify OTP and change email
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmailChange(Request $request)
    {
        try {
            $request->validate([
                'otp' => 'required|string|size:6'
            ]);

            $user = auth('api')->user();
            $cacheKey = "email_change:{$user->id}";
            $otpData = Cache::get($cacheKey);

            if (!$otpData) {
                return response()->json([
                    'error' => 'OTP session expired. Please request a new OTP.',
                    'code' => 'SESSION_EXPIRED'
                ], 404);
            }

            if ($otpData['expires_at'] < now()) {
                Cache::forget($cacheKey);
                return response()->json([
                    'error' => 'OTP has expired. Please request a new OTP.',
                    'code' => 'OTP_EXPIRED'
                ], 400);
            }

            if ($otpData['attempts'] >= 3) {
                Cache::forget($cacheKey);
                return response()->json([
                    'error' => 'Maximum attempts exceeded. Please request a new OTP.',
                    'code' => 'MAX_ATTEMPTS_EXCEEDED'
                ], 400);
            }

            // Increment attempts
            $otpData['attempts']++;
            Cache::put($cacheKey, $otpData, now()->addMinutes(30));

            if ($request->otp !== $otpData['otp']) {
                $remainingAttempts = 3 - $otpData['attempts'];
                return response()->json([
                    'error' => 'Invalid OTP',
                    'remaining_attempts' => $remainingAttempts,
                    'code' => 'INVALID_OTP'
                ], 400);
            }

            // Store old email for updating event invitations
            $oldEmail = $user->email;
            $newEmail = $otpData['new_email'];
            
            // Update user's email and mark as verified
            $user = User::find($user->id);
            $user->email = $newEmail;
            $user->email_verified_at = now();
            $user->save();

            // Update any event invitations associated with the old email
            $eventInvitations = \App\Models\EventInvitation::where('email', $oldEmail)->get();
            foreach ($eventInvitations as $invitation) {
                $invitation->email = $newEmail;
                $invitation->save();
            }

            // Clean up cache
            Cache::forget($cacheKey);

            return response()->json([
                'message' => 'Email changed successfully.',
                'user' => $user->makeHidden(['otp', 'otp_expires_at', 'otp_attempts', 'id'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify OTP. Please try again.'
            ], 500);
        }
    }

    public function publicProfile($user_name){
        $user = User::where('user_name', $user_name)->firstOrFail();
        if ($user->profile_image) {
            $user->profile_image = url('storage/' . $user->profile_image);
        }
        return response()->json(['user' => $user]);
    }

    /**
     * Get comprehensive statistics for a user
     *
     * @param string $user_name
     * @return \Illuminate\Http\JsonResponse
     */
    public function userStats($user_name)
    {
        // Get the user
        $user = User::where('user_name', $user_name)->firstOrFail();
        
        // Get user's social media
        $socialMedia = $user->socialMedia()->first();
        
        // Get all challenges (excluding events)
        $allChallenges = Challange::all();
        $totalChallenges = $allChallenges->count();
        
        // Get challenges by difficulty and count
        $challengesByDifficulty = $allChallenges->groupBy('difficulty');
        $totalChallengesByDifficulty = [];
        foreach ($challengesByDifficulty as $difficulty => $challenges) {
            $totalChallengesByDifficulty[$difficulty] = $challenges->count();
        }
        
        // Get user's solved challenges
        $userSolvedChallenges = Submission::where('user_uuid', $user->uuid)
            ->where('solved', true)
            ->with(['challange', 'challange.category', 'challange.flags'])
            ->get();
        
        // Get unique challenge UUIDs, considering flag types
        $solvedChallengeUUIDs = collect();
        $solvedFlagSubmissions = collect(); // Track flagSubmissions for multiple_individual challenges
        
        foreach ($userSolvedChallenges as $submission) {
            if (!$submission->challange) {
                continue;
            }
            
            // Add to solved challenges if not already there
            if (!$solvedChallengeUUIDs->contains($submission->challange_uuid)) {
                $solvedChallengeUUIDs->push($submission->challange_uuid);
            }
            
            // Track submission for later bytes calculation
            $solvedFlagSubmissions->push($submission);
        }
        
        $totalSolved = $solvedChallengeUUIDs->count();
        
        // Calculate percentage solved
        $percentageSolved = $totalChallenges > 0 ? ($totalSolved / $totalChallenges) * 100 : 0;
        
        // Get user's current title
        $currentTitle = PlayerTitle::getTitleForPercentage($percentageSolved);
        
        // Get next title
        $titleConfig = PlayerTitle::first();
        $nextTitle = null;
        $nextTitleArabic = null; // Initialize the variable
        $percentageForNextTitle = null;
        
        if ($titleConfig) {
            $ranges = collect($titleConfig->title_ranges)->sortBy('from');
            $currentRange = null;
            $nextRange = null;
            $isHighestTitle = false;
            
            foreach ($ranges as $index => $range) {
                if ($percentageSolved >= $range['from'] && $percentageSolved <= $range['to']) {
                    $currentRange = $range;
                    if ($index < count($ranges) - 1) {
                        $nextRange = $ranges[$index + 1];
                    } else {
                        // User has reached the highest title (Legend)
                        $isHighestTitle = true;
                    }
                    break;
                }
            }
            
            if ($currentRange && $nextRange) {
                $nextTitle = $nextRange['title'];
                $nextTitleArabic = $nextRange['arabic_title'];
                // Calculate the percentage needed within the current range
                $currentRangeSize = $nextRange['from'] - $currentRange['from'];
                $progressInRange = $percentageSolved - $currentRange['from'];
                $percentageForNextTitle = ($currentRangeSize - $progressInRange) / $currentRangeSize * 100;
            } elseif ($isHighestTitle) {
                // For users with the highest title (Legend), show percentage remaining to solve all challenges
                $percentageForNextTitle = 100 - $percentageSolved;
            }            
        }
        
        // Calculate solved challenges by difficulty
        $solvedByDifficulty = [
            'easy' => 0,
            'medium' => 0,
            'hard' => 0,
            'very_hard' => 0
        ];
        
        foreach ($userSolvedChallenges as $submission) {
            if ($submission->challange) {
                $difficulty = $submission->challange->difficulty;
                $solvedByDifficulty[$difficulty] = ($solvedByDifficulty[$difficulty] ?? 0) + 1;
            }
        }
        
        // Get total bytes and firstblood bytes
        $totalBytes = 0;
        $totalFirstBloodBytes = 0;
        $totalFirstBloodCount = 0;
        $processedFlags = collect(); // Track processed flags to avoid duplicates
        
        foreach ($solvedFlagSubmissions as $submission) {
            if (!$submission->challange) {
                continue;
            }
            
            $challange = $submission->challange;
            
            // For single-flag challenges (default, simple)
            if (!$challange->usesMultipleFlags()) {
                // Skip if we've already processed this challenge
                if ($processedFlags->contains($submission->id)) {
                    continue;
                }
                $processedFlags->push($submission->id);
                
                // Check if this is a first blood
                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                if ($isFirstBlood) {
                    // User gets firstblood bytes only
                    $totalFirstBloodBytes += $challange->firstBloodBytes;
                    $totalFirstBloodCount++;
                } else {
                    // User gets regular bytes
                    $totalBytes += $challange->bytes;
                }
            }
            // For multiple_individual challenges, we need to count each flag's bytes separately
            else if ($challange->usesIndividualFlagPoints()) {
                // Find which flag this submission corresponds to
                $submissionFlag = $submission->flag;
                
                // For each flag in the challenge
                foreach ($challange->flags as $flag) {
                    // If this submission solves this flag (and we haven't counted it yet)
                    if ($flag->flag === $submissionFlag && !$processedFlags->contains("flag_{$flag->id}")) {
                        $processedFlags->push("flag_{$flag->id}");
                        
                        // Check if this is a first blood for this specific flag
                        $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                            ->where('flag', $flag->flag)
                            ->where('solved', true)
                            ->orderBy('created_at')
                            ->first()
                            ->user_uuid === $user->uuid;
                        
                        if ($isFirstBlood) {
                            // User gets firstblood bytes only
                            $totalFirstBloodBytes += $flag->firstBloodBytes;
                            $totalFirstBloodCount++;
                        } else {
                            // User gets regular bytes
                            $totalBytes += $flag->bytes;
                        }
                    }
                }
            }
            // For multiple_all challenges, we count the challenge's bytes only once
            else {
                // Skip if we've already processed this challenge
                if ($processedFlags->contains("challenge_{$challange->id}")) {
                    continue;
                }
                $processedFlags->push("challenge_{$challange->id}");
                
                // Check if this is a first blood (for the whole challenge)
                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                if ($isFirstBlood) {
                    // User gets firstblood bytes only
                    $totalFirstBloodBytes += $challange->firstBloodBytes;
                    $totalFirstBloodCount++;
                } else {
                    // User gets regular bytes
                    $totalBytes += $challange->bytes;
                }
            }
        }
        
        // Calculate user's rank using the same approach as the leaderboard
        $allUsersRanked = User::with(['submissions' => function($query) {
            $query->where('solved', true)
                  ->with(['challange' => function($q) {
                      $q->with('flags');
                  }]);
        }])
        ->get()
        ->map(function($rankUser) {
            $points = 0;
            $firstBloodCount = 0;
            $processedChallenges = [];
            $solvedChallenges = [];
            
            foreach ($rankUser->submissions as $submission) {
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
                    
                    if ($firstBloodSubmission && $firstBloodSubmission->user_uuid === $rankUser->uuid) {
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
                        ->where('user_uuid', $rankUser->uuid)
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
                            
                            if (!$firstSolver || $firstSolver->user_uuid !== $rankUser->uuid) {
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
                        
                        if ($firstSolver && $firstSolver->user_uuid === $rankUser->uuid) {
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
            
            return [
                'uuid' => $rankUser->uuid,
                'user_name' => $rankUser->user_name,
                'points' => $points,
                'challenges_solved' => count($solvedChallenges),
                'first_blood_count' => $firstBloodCount,
            ];
        })
        ->sortByDesc('points');
        
        // Find the user's rank in the sorted collection
        $userRank = 1;
        // Reset the collection keys after sorting to ensure proper indexing
        $allUsersRankedWithResetKeys = $allUsersRanked->values();
        foreach ($allUsersRankedWithResetKeys as $index => $rankData) {
            if ($rankData['uuid'] === $user->uuid) {
                $userRank = $index + 1;
                break;
            }
        }
        
        // Recalculate total bytes to match the leaderboard calculation
        $totalLeaderboardBytes = 0;
        foreach ($allUsersRankedWithResetKeys as $rankData) {
            if ($rankData['uuid'] === $user->uuid) {
                $totalLeaderboardBytes = $rankData['points'];
                break;
            }
        }
        
        // Get challenges by category
        $challengesByCategory = [];
        $categories = ChallangeCategory::all();
        
        foreach ($categories as $category) {
            $totalInCategory = Challange::where('category_uuid', $category->uuid)->count();
            
            // Count challenges, not flags
            $solvedInCategory = Challange::where('category_uuid', $category->uuid)
                ->whereIn('uuid', $solvedChallengeUUIDs)
                ->count();
            
            $percentageInCategory = $totalInCategory > 0 ? ($solvedInCategory / $totalInCategory) * 100 : 0;
            
            $challengesByCategory[] = [
                'name' => $category->name,
                'total' => $totalInCategory,
                'solved' => $solvedInCategory,
                'percentage' => $percentageInCategory,
            ];
        }
        
        // Get median statistics for all users - only for users with >0% in the category
        $allUsersStats = [];
        $users = User::all();
        foreach ($categories as $category) {
            $challengesInCategory = Challange::where('category_uuid', $category->uuid)->with('flags')->get();
            $totalChallenges = $challengesInCategory->count();
            if ($totalChallenges === 0) {
                $allUsersStats[] = [
                    'name' => $category->name,
                    'median_percentage' => 0,
                    'debug' => [
                        'allPercentages' => [],
                        'totalChallenges' => 0,
                    ],
                ];
                continue;
            }
            $allPercentages = [];
            foreach ($users as $currentUser) {
                $solvedCount = 0;
                foreach ($challengesInCategory as $challenge) {
                    if ($challenge->flag_type === 'single' || !$challenge->flag_type) {
                        $solved = Submission::where('user_uuid', $currentUser->uuid)
                            ->where('challange_uuid', $challenge->uuid)
                            ->where('solved', true)
                            ->exists();
                        if ($solved) $solvedCount++;
                    } elseif ($challenge->flag_type === 'multiple_all') {
                        $totalFlags = $challenge->flags->count();
                        if ($totalFlags > 0) {
                            $userSolvedFlags = Submission::where('user_uuid', $currentUser->uuid)
                                ->where('challange_uuid', $challenge->uuid)
                                ->where('solved', true)
                                ->pluck('flag')
                                ->unique()
                                ->count();
                            if ($userSolvedFlags === $totalFlags) $solvedCount++;
                        }
                    } elseif ($challenge->flag_type === 'multiple_individual') {
                        $solved = Submission::where('user_uuid', $currentUser->uuid)
                            ->where('challange_uuid', $challenge->uuid)
                            ->where('solved', true)
                            ->exists();
                        if ($solved) $solvedCount++;
                    }
                }
                $percentage = $totalChallenges > 0 ? ($solvedCount / $totalChallenges) * 100 : 0;
                if ($percentage > 0) { // Only include users who solved at least one
                    $allPercentages[] = $percentage;
                }
            }
            sort($allPercentages);
            $count = count($allPercentages);
            $median = 0;
            if ($count > 0) {
                if ($count % 2 === 0) {
                    $middleIndex = $count / 2;
                    $median = ($allPercentages[$middleIndex - 1] + $allPercentages[$middleIndex]) / 2;
                } else {
                    $middleIndex = floor($count / 2);
                    $median = $allPercentages[$middleIndex];
                }
            }
            $allUsersStats[] = [
                'name' => $category->name,
                'median_percentage' => $median,
                'debug' => [
                    'allPercentages' => $allPercentages,
                    'totalChallenges' => $totalChallenges,
                ],
            ];
        }
        
        $lab3 = Lab::where('id', 3)->first();
        $lab3Stats = null;
        
        if ($lab3) {
            $lab3Categories = $lab3->labCategories->pluck('uuid');
            
            $lab3Challenges = Challange::whereIn('lab_category_uuid', $lab3Categories)->get();
            $lab3TotalChallenges = $lab3Challenges->count();
            
            // Set lab3Stats to null if there are no active challenges
            if ($lab3TotalChallenges <= 0) {
                $lab3Stats = null;
            } else {
                $lab3SolvedChallenges = Submission::where('user_uuid', $user->uuid)
                    ->where('solved', true)
                    ->whereIn('challange_uuid', $lab3Challenges->pluck('uuid'))
                    ->with(['challange', 'challange.flags'])
                    ->get();
                
                // Get unique challenge UUIDs for Lab 3
                $lab3SolvedChallengeUUIDs = collect();
                foreach ($lab3SolvedChallenges as $submission) {
                    if ($submission->challange && !$lab3SolvedChallengeUUIDs->contains($submission->challange_uuid)) {
                        $lab3SolvedChallengeUUIDs->push($submission->challange_uuid);
                    }
                }
                $lab3SolvedCount = $lab3SolvedChallengeUUIDs->count();
                $lab3PercentageSolved = $lab3TotalChallenges > 0 ? ($lab3SolvedCount / $lab3TotalChallenges) * 100 : 0;
                
                // Calculate Lab 3 solved challenges by difficulty
                $lab3SolvedByDifficulty = [
                    'easy' => 0,
                    'medium' => 0,
                    'hard' => 0,
                    'very_hard' => 0
                ];
                
                // Count challenges by difficulty, not flags
                foreach ($lab3SolvedChallengeUUIDs as $challengeUuid) {
                    $challenge = $lab3Challenges->firstWhere('uuid', $challengeUuid);
                    if ($challenge) {
                        $difficulty = $challenge->difficulty;
                        $lab3SolvedByDifficulty[$difficulty] = ($lab3SolvedByDifficulty[$difficulty] ?? 0) + 1;
                    }
                }
                
                // Calculate total bytes for Lab 3
                $lab3Bytes = 0;
                $lab3FirstBloodBytes = 0;
                $processedLab3Flags = collect();
                
                foreach ($lab3SolvedChallenges as $submission) {
                    if (!$submission->challange) {
                        continue;
                    }
                    
                    $challange = $submission->challange;
                    
                    // For single-flag challenges
                    if (!$challange->usesMultipleFlags()) {
                        // Skip if we've already processed this challenge
                        if ($processedLab3Flags->contains($submission->id)) {
                            continue;
                        }
                        $processedLab3Flags->push($submission->id);
                        
                        // Check if this is a first blood
                        $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                            ->where('solved', true)
                            ->orderBy('created_at')
                            ->first()
                            ->user_uuid === $user->uuid;
                        
                        if ($isFirstBlood) {
                            // User gets firstblood bytes only
                            $lab3FirstBloodBytes += $challange->firstBloodBytes;
                        } else {
                            // User gets regular bytes
                            $lab3Bytes += $challange->bytes;
                        }
                    }
                    // For multiple_individual challenges
                    else if ($challange->usesIndividualFlagPoints()) {
                        // Find which flag this submission corresponds to
                        $submissionFlag = $submission->flag;
                        
                        // For each flag in the challenge
                        foreach ($challange->flags as $flag) {
                            // If this submission solves this flag (and we haven't counted it yet)
                            if ($flag->flag === $submissionFlag && !$processedLab3Flags->contains("flag_{$flag->id}")) {
                                $processedLab3Flags->push("flag_{$flag->id}");
                                
                                // Check if this is a first blood for this specific flag
                                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                                    ->where('flag', $flag->flag)
                                    ->where('solved', true)
                                    ->orderBy('created_at')
                                    ->first()
                                    ->user_uuid === $user->uuid;
                                
                                if ($isFirstBlood) {
                                    // User gets firstblood bytes only
                                    $lab3FirstBloodBytes += $flag->firstBloodBytes;
                                } else {
                                    // User gets regular bytes
                                    $lab3Bytes += $flag->bytes;
                                }
                            }
                        }
                    }
                    // For multiple_all challenges
                    else {
                        // Skip if we've already processed this challenge
                        if ($processedLab3Flags->contains("challenge_{$challange->id}")) {
                            continue;
                        }
                        $processedLab3Flags->push("challenge_{$challange->id}");
                        
                        // Check if this is a first blood
                        $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                            ->where('solved', true)
                            ->orderBy('created_at')
                            ->first()
                            ->user_uuid === $user->uuid;
                        
                        if ($isFirstBlood) {
                            // User gets firstblood bytes only
                            $lab3FirstBloodBytes += $challange->firstBloodBytes;
                        } else {
                            // User gets regular bytes
                            $lab3Bytes += $challange->bytes;
                        }
                    }
                }
                
                $lab3Stats = [
                    'total_challenges' => $lab3TotalChallenges,
                    'solved_challenges' => $lab3SolvedCount,
                    'percentage_solved' => $lab3PercentageSolved,
                    'solved_by_difficulty' => $lab3SolvedByDifficulty,
                    'total_bytes' => $lab3Bytes,
                    'total_firstblood_bytes' => $lab3FirstBloodBytes
                ];
            }
        }
        
        // Get maximum and minimum bytes per month (current year) and yearly median max and min
        $bytesByMonth = [];
        $currentYear = date('Y');
        $allMaxBytes = collect();
        $allMinBytes = collect();

        for ($month = 1; $month <= 12; $month++) {
            $monthlySubmissions = Submission::where('user_uuid', $user->uuid)
                ->where('solved', true)
                ->whereYear('created_at', $currentYear)
                ->whereMonth('created_at', $month)
                ->with(['challange', 'challange.flags'])
                ->get();

            $processedMonthFlags = collect(); // Track processed flags to avoid duplicates
            $monthBytes = collect(); // Collect bytes for each submission

            foreach ($monthlySubmissions as $submission) {
                if (!$submission->challange) {
                    continue;
                }

                $challange = $submission->challange;

                // For single-flag challenges (default, simple)
                if (!$challange->usesMultipleFlags()) {
                    // Skip if we've already processed this challenge
                    if ($processedMonthFlags->contains($submission->id)) {
                        continue;
                    }
                    $processedMonthFlags->push($submission->id);

                    // Check if this is a first blood
                    $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                        ->where('solved', true)
                        ->orderBy('created_at')
                        ->first()
                        ->user_uuid === $user->uuid;

                    if ($isFirstBlood) {
                        // User gets firstblood bytes only
                        $monthBytes->push($challange->firstBloodBytes);
                    } else {
                        // User gets regular bytes
                        $monthBytes->push($challange->bytes);
                    }
                }
                // For multiple_individual challenges, we need to count each flag's bytes separately
                else if ($challange->usesIndividualFlagPoints()) {
                    // Find which flag this submission corresponds to
                    $submissionFlag = $submission->flag;

                    // For each flag in the challenge
                    foreach ($challange->flags as $flag) {
                        // If this submission solves this flag (and we haven't counted it yet)
                        if ($flag->flag === $submissionFlag && !$processedMonthFlags->contains("flag_{$flag->id}_{$month}")) {
                            $processedMonthFlags->push("flag_{$flag->id}_{$month}");

                            // Check if this is a first blood for this specific flag
                            $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                                ->where('flag', $flag->flag)
                                ->where('solved', true)
                                ->orderBy('created_at')
                                ->first()
                                ->user_uuid === $user->uuid;

                            if ($isFirstBlood) {
                                // User gets firstblood bytes only
                                $monthBytes->push($flag->firstBloodBytes);
                            } else {
                                // User gets regular bytes
                                $monthBytes->push($flag->bytes);
                            }
                        }
                    }
                }
                // For multiple_all challenges, we count the challenge's bytes only once
                else {
                    // Skip if we've already processed this challenge
                    if ($processedMonthFlags->contains("challenge_{$challange->id}_{$month}")) {
                        continue;
                    }
                    $processedMonthFlags->push("challenge_{$challange->id}_{$month}");

                    // Check if this is a first blood (for the whole challenge)
                    $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                        ->where('solved', true)
                        ->orderBy('created_at')
                        ->first()
                        ->user_uuid === $user->uuid;

                    if ($isFirstBlood) {
                        // User gets firstblood bytes only
                        $monthBytes->push($challange->firstBloodBytes);
                    } else {
                        // User gets regular bytes
                        $monthBytes->push($challange->bytes);
                    }
                }
            }

            $maxBytes = $monthBytes->max() ?? 0;
            $minBytes = $monthBytes->min() ?? 0;
            $totalBytes = $monthBytes->sum() ?? 0;
            $bytesByMonth[$month] = [
                'max' => $totalBytes,
                'min' => $minBytes,
            ];

            $allMaxBytes->push($maxBytes);
            $allMinBytes->push($minBytes);
        }

        $yearlyMedianMax = $allMaxBytes->median() ?? 0;
        $yearlyMedianMin = $allMinBytes->median() ?? 0;

        // Calculate yearly total
        $yearlyTotal = 0;
        for ($month = 1; $month <= 12; $month++) {
            if (isset($bytesByMonth[$month]['total'])) {
                $yearlyTotal += $bytesByMonth[$month]['total'];
            }
        }
        
        // Add yearly median max and min to the result
        $bytesByMonth['yearly_median'] = [
            'max' => $yearlyMedianMax,
            'min' => $yearlyMedianMin
        ];
        
        // Add yearly total
        $bytesByMonth['yearly_total'] = $yearlyTotal;
        
        // Compile and return the statistics
        return response()->json([
            'user' => [
                'user_name' => $user->user_name,
                'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                'country' => $user->country,
                'title' => $currentTitle["title"],
                'ar_title' => $currentTitle["arabic_title"],
                'next_title' => $nextTitle,
                'next_title_arabic' => $nextTitleArabic,
                'percentage_for_next_title' => $percentageForNextTitle,
                'total_bytes' => $totalLeaderboardBytes,
                'total_firstblood_count' => $totalFirstBloodCount,
                'rank' => $userRank,
                'social_media' => $socialMedia,
            ],
            'challenges' => [
                'total' => $totalChallenges,
                'solved' => $totalSolved,
                'percentage_solved' => $percentageSolved,
                'total_by_difficulty' => $totalChallengesByDifficulty,
                'solved_by_difficulty' => $solvedByDifficulty,
            ],
            'categories' => $challengesByCategory,
            'all_users_median' => $allUsersStats,
            'lab3' => $lab3Stats,
            'bytes_by_month' => $bytesByMonth,
        ]);
    }

    /**
     * Get comprehensive statistics for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myStats(Request $request)
    {
        return $this->userStats($request->user()->user_name);
    }

    /**
     * Get user's recent challenge activities
     *
     * @param string $user_name
     * @return \Illuminate\Http\JsonResponse
     */
    public function userActivities($user_name)
    {
        // Get the user
        $user = User::where('user_name', $user_name)->firstOrFail();
        
        // Get user's solved challenges, ordered by solved time (most recent first)
        $userSubmissions = Submission::where('user_uuid', $user->uuid)
            ->where('solved', true)
            ->with(['challange', 'challange.category', 'challange.flags'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $activities = [];
        $count = 0;
        $processedMultipleAllChallenges = []; // Track processed multiple_all challenges
        
        foreach ($userSubmissions as $submission) {
            if (!$submission->challange) {
                continue;
            }
            
            $challange = $submission->challange;
            $submissionFlag = $submission->flag;
            
            // For single-flag challenges or default flag type
            if ($challange->flag_type !== 'multiple_individual' && $challange->flag_type !== 'multiple_all') {
                // Check if this was a first blood
                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                // Format date based on user's timezone
                $solvedAt = new \DateTime($submission->created_at);
                $userTimezone = auth()->user()->time_zone ?? 'UTC';
                $solvedAt->setTimezone(new \DateTimeZone($userTimezone));
                
                $activities[] = [
                    'challenge_title' => $challange->title,
                    'challenge_uuid' => $challange->uuid,
                    'category' => $challange->category ? $challange->category->name : null,
                    'category_icon_url' => url('/storage/' . $challange->category->icon),
                    'difficulty' => $challange->difficulty,
                    'bytes' => $challange->bytes,
                    'is_first_blood' => $isFirstBlood,
                    'first_blood_bytes' => $challange->firstBloodBytes,
                    'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                    'timezone' => $userTimezone,
                    'flag_type' => $challange->flag_type
                ];
                
                // Limit to 50 activities
                $count++;
                if ($count >= 50) {
                    break;
                }
            }
            // For multiple_all challenges
            else if ($challange->flag_type === 'multiple_all') {
                // Skip if we've already processed this challenge
                if (in_array($challange->uuid, $processedMultipleAllChallenges)) {
                    continue;
                }
                
                // Check if all flags for this challenge have been solved by the user
                $totalFlags = $challange->flags->count();
                if ($totalFlags == 0) {
                    continue; // Skip if there are no flags
                }
                
                // Get all solved flags for this user and challenge
                $userSolvedSubmissions = Submission::where('user_uuid', $user->uuid)
                    ->where('challange_uuid', $challange->uuid)
                    ->where('solved', true)
                    ->get();
                
                $solvedFlags = [];
                $lastSolvedTime = null;
                
                foreach ($userSolvedSubmissions as $solvedSubmission) {
                    if (!in_array($solvedSubmission->flag, $solvedFlags)) {
                        $solvedFlags[] = $solvedSubmission->flag;
                        
                        // Track the most recent submission time
                        if (!$lastSolvedTime || $solvedSubmission->created_at > $lastSolvedTime) {
                            $lastSolvedTime = $solvedSubmission->created_at;
                        }
                    }
                }
                
                // Only show an entry if ALL flags have been solved
                if (count($solvedFlags) == $totalFlags) {
                    // Mark this challenge as processed
                    $processedMultipleAllChallenges[] = $challange->uuid;
                    
                    // Check if this was a first blood for the whole challenge (all flags)
                    $isFirstBlood = false;
                    
                    // For multiple_all challenges, first blood should go to the first user who completes ALL flags
                    // Find if any other user has previously completed all flags of this challenge
                    $allFlags = $challange->flags->pluck('flag')->toArray();
                    
                    // Get all users who have completed all flags for this challenge
                    $usersWithAllFlags = [];
                    
                    // For each user who has submitted for this challenge
                    $userSubmissions = Submission::where('challange_uuid', $challange->uuid)
                        ->where('solved', true)
                        ->select('user_uuid')
                        ->distinct()
                        ->get();
                    
                    foreach ($userSubmissions as $userSubmission) {
                        $tmpUserUuid = $userSubmission->user_uuid;
                        
                        // Get all solved flags for this user and challenge
                        $userSolvedFlags = Submission::where('challange_uuid', $challange->uuid)
                            ->where('user_uuid', $tmpUserUuid)
                            ->where('solved', true)
                            ->pluck('flag')
                            ->unique()
                            ->toArray();
                        
                        // Check if they've solved all flags
                        $solvedAllFlags = true;
                        foreach ($allFlags as $flagValue) {
                            if (!in_array($flagValue, $userSolvedFlags)) {
                                $solvedAllFlags = false;
                                break;
                            }
                        }
                        
                        // If they've solved all flags, store them with their completion time
                        if ($solvedAllFlags) {
                            // Get the time when this user solved their last flag
                            $lastFlagSolveTime = Submission::where('challange_uuid', $challange->uuid)
                                ->where('user_uuid', $tmpUserUuid)
                                ->where('solved', true)
                                ->orderBy('created_at', 'desc')
                                ->first()
                                ->created_at;
                            
                            $usersWithAllFlags[$tmpUserUuid] = $lastFlagSolveTime;
                        }
                    }
                    
                    // Sort users by completion time (earliest first)
                    asort($usersWithAllFlags);
                    
                    // First blood goes to the first user who completed all flags
                    if (!empty($usersWithAllFlags)) {
                        $firstCompleteUserUuid = array_key_first($usersWithAllFlags);
                        $isFirstBlood = ($firstCompleteUserUuid === $user->uuid);
                    } else {
                        // If no one has completed all flags yet, this user is the first blood
                        $isFirstBlood = true;
                    }
                    
                    // Format date based on user's timezone
                    $solvedAt = new \DateTime($lastSolvedTime);
                    $userTimezone = $user->time_zone ?? 'UTC';
                    $solvedAt->setTimezone(new \DateTimeZone($userTimezone));
                    
                    $activities[] = [
                        'challenge_title' => $challange->title,
                        'challenge_uuid' => $challange->uuid,
                        'category' => $challange->category ? $challange->category->name : null,
                        'category_icon_url' => url('/storage/' . $challange->category->icon),
                        'difficulty' => $challange->difficulty,
                        'bytes' => $challange->bytes,
                        'is_first_blood' => $isFirstBlood,
                        'first_blood_bytes' => $challange->firstBloodBytes,
                        'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                        'timezone' => $userTimezone,
                        'flag_type' => $challange->flag_type . '_complete', // Mark as complete
                        'flags_total' => $totalFlags,
                        'flags_solved' => count($solvedFlags)
                    ];
                    
                    // Limit to 50 activities
                    $count++;
                    if ($count >= 50) {
                        break;
                    }
                }
            }
            // For multiple_individual challenges, list each flag separately
            else if ($challange->flag_type === 'multiple_individual') {
                // Find the specific flag this submission corresponds to
                $flag = null;
                foreach ($challange->flags as $challengeFlag) {
                    if ($challengeFlag->flag === $submissionFlag) {
                        $flag = $challengeFlag;
                        break;
                    }
                }
                
                if (!$flag) {
                    continue; // Skip if we can't find the matching flag
                }
                
                // Check if this was a first blood for this specific flag
                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                // Format date based on user's timezone
                $solvedAt = new \DateTime($submission->created_at);
                $userTimezone = $user->time_zone ?? 'UTC';
                $solvedAt->setTimezone(new \DateTimeZone($userTimezone));
                
                $activities[] = [
                    'challenge_title' => $challange->title . ' - ' . ($flag->name ?? 'Flag'),
                    'challenge_uuid' => $challange->uuid,
                    'category' => $challange->category ? $challange->category->name : null,
                    'category_icon_url' => url('/storage/' . $challange->category->icon),
                    'difficulty' => $challange->difficulty,
                    'bytes' => $flag->bytes,
                    'is_first_blood' => $isFirstBlood,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                    'timezone' => $userTimezone,
                    'flag_type' => $challange->flag_type,
                    'flag_name' => $flag->name ?? 'Flag'
                ];
                
                // Limit to 50 activities
                $count++;
                if ($count >= 50) {
                    break;
                }
            }
        }
        
        return response()->json([
            'user_name' => $user->user_name,
            'activities' => $activities
        ]);
    }
    
    /**
     * Get authenticated user's recent challenge activities
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myActivities(Request $request)
    {
        return $this->userActivities($request->user()->user_name);
    }

    /**
     * Get the most recent 30 challenge activities across all users
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recentPlatformActivities()
    {
        // Get the 30 most recent solved submissions
        $recentSubmissions = Submission::where('solved', true)
            ->with(['challange', 'challange.category', 'challange.flags', 'user'])
            ->orderBy('created_at', 'desc')
            ->take(80) // Fetch more than needed to ensure we have 30 valid submissions after filtering
            ->get();
        
        $activities = [];
        $count = 0;
        $processedMultipleAllChallenges = []; // Track processed multiple_all challenges
        
        foreach ($recentSubmissions as $submission) {
            if (!$submission->challange || !$submission->user) {
                continue;
            }
            
            $challange = $submission->challange;
            $user = $submission->user;
            $submissionFlag = $submission->flag;
            
            // For single-flag challenges or default flag type
            if ($challange->flag_type !== 'multiple_individual' && $challange->flag_type !== 'multiple_all') {
                // Check if this was a first blood
                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                // Format date
                $solvedAt = new \DateTime($submission->created_at);
                
                $activities[] = [
                    'user_name' => $user->user_name,
                    'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                    'challenge_title' => $challange->title,
                    'challenge_uuid' => $challange->uuid,
                    'category' => $challange->category ? $challange->category->name : null,
                    'difficulty' => $challange->difficulty,
                    'bytes' => $challange->bytes,
                    'is_first_blood' => $isFirstBlood,
                    'first_blood_bytes' => $challange->firstBloodBytes,
                    'total_bytes' => $isFirstBlood ? $challange->firstBloodBytes : $challange->bytes,
                    'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                    'flag_type' => $challange->flag_type
                ];
                
                // Limit to 30 activities
                $count++;
                if ($count >= 30) {
                    break;
                }
            }
            // For multiple_all challenges
            else if ($challange->flag_type === 'multiple_all') {
                // Generate a unique key for this user-challenge combination
                $key = $user->uuid . '_' . $challange->uuid;
                
                // Skip if we've already processed this challenge for this user
                if (in_array($key, $processedMultipleAllChallenges)) {
                    continue;
                }
                
                // Check if all flags for this challenge have been solved by the user
                $totalFlags = $challange->flags->count();
                if ($totalFlags == 0) {
                    continue; // Skip if there are no flags
                }
                
                // Get all solved flags for this user and challenge
                $userSolvedSubmissions = Submission::where('user_uuid', $user->uuid)
                    ->where('challange_uuid', $challange->uuid)
                    ->where('solved', true)
                    ->get();
                
                $solvedFlags = [];
                $lastSolvedTime = null;
                
                foreach ($userSolvedSubmissions as $solvedSubmission) {
                    if (!in_array($solvedSubmission->flag, $solvedFlags)) {
                        $solvedFlags[] = $solvedSubmission->flag;
                        
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
                    $isFirstBlood = false;
                    
                    // For multiple_all challenges, first blood should go to the first user who completes ALL flags
                    // Find if any other user has previously completed all flags of this challenge
                    $allFlags = $challange->flags->pluck('flag')->toArray();
                    
                    // Get all users who have completed all flags for this challenge
                    $usersWithAllFlags = [];
                    
                    // For each user who has submitted for this challenge
                    $userSubmissions = Submission::where('challange_uuid', $challange->uuid)
                        ->where('solved', true)
                        ->select('user_uuid')
                        ->distinct()
                        ->get();
                    
                    foreach ($userSubmissions as $userSubmission) {
                        $tmpUserUuid = $userSubmission->user_uuid;
                        
                        // Get all solved flags for this user and challenge
                        $userSolvedFlags = Submission::where('challange_uuid', $challange->uuid)
                            ->where('user_uuid', $tmpUserUuid)
                            ->where('solved', true)
                            ->pluck('flag')
                            ->unique()
                            ->toArray();
                        
                        // Check if they've solved all flags
                        $solvedAllFlags = true;
                        foreach ($allFlags as $flagValue) {
                            if (!in_array($flagValue, $userSolvedFlags)) {
                                $solvedAllFlags = false;
                                break;
                            }
                        }
                        
                        // If they've solved all flags, store them with their completion time
                        if ($solvedAllFlags) {
                            // Get the time when this user solved their last flag
                            $lastFlagSolveTime = Submission::where('challange_uuid', $challange->uuid)
                                ->where('user_uuid', $tmpUserUuid)
                                ->where('solved', true)
                                ->orderBy('created_at', 'desc')
                                ->first()
                                ->created_at;
                            
                            $usersWithAllFlags[$tmpUserUuid] = $lastFlagSolveTime;
                        }
                    }
                    
                    // Sort users by completion time (earliest first)
                    asort($usersWithAllFlags);
                    
                    // First blood goes to the first user who completed all flags
                    if (!empty($usersWithAllFlags)) {
                        $firstCompleteUserUuid = array_key_first($usersWithAllFlags);
                        $isFirstBlood = ($firstCompleteUserUuid === $user->uuid);
                    } else {
                        // If no one has completed all flags yet, this user is the first blood
                        $isFirstBlood = true;
                    }
                    
                    $solvedAt = new \DateTime($lastSolvedTime);
                    
                    $activities[] = [
                        'user_name' => $user->user_name,
                        'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                        'challenge_title' => $challange->title,
                        'challenge_uuid' => $challange->uuid,
                        'category' => $challange->category ? $challange->category->name : null,
                        'difficulty' => $challange->difficulty,
                        'bytes' => $challange->bytes,
                        'is_first_blood' => $isFirstBlood,
                        'first_blood_bytes' => $challange->firstBloodBytes,
                        'total_bytes' => $isFirstBlood ? $challange->firstBloodBytes : $challange->bytes,
                        'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                        'flag_type' => $challange->flag_type . '_complete', // Mark as complete
                        'flags_total' => $totalFlags,
                        'flags_solved' => count($solvedFlags)
                    ];
                    
                    // Limit to 30 activities
                    $count++;
                    if ($count >= 30) {
                        break;
                    }
                }
            }
            // For multiple_individual challenges, list each flag separately
            else if ($challange->flag_type === 'multiple_individual') {
                // Find the specific flag this submission corresponds to
                $flag = null;
                foreach ($challange->flags as $challengeFlag) {
                    if ($challengeFlag->flag === $submissionFlag) {
                        $flag = $challengeFlag;
                        break;
                    }
                }
                
                if (!$flag) {
                    continue; // Skip if we can't find the matching flag
                }
                
                // Check if this was a first blood for this specific flag
                $isFirstBlood = Submission::where('challange_uuid', $submission->challange_uuid)
                    ->where('flag', $flag->flag)
                    ->where('solved', true)
                    ->orderBy('created_at')
                    ->first()
                    ->user_uuid === $user->uuid;
                
                // Format date
                $solvedAt = new \DateTime($submission->created_at);
                
                $activities[] = [
                    'user_name' => $user->user_name,
                    'user_profile_image' => $user->profile_image ? url('storage/' . $user->profile_image) : null,
                    'challenge_title' => $challange->title . ' - ' . ($flag->name ?? 'Flag'),
                    'challenge_uuid' => $challange->uuid,
                    'category' => $challange->category ? $challange->category->name : null,
                    'difficulty' => $challange->difficulty,
                    'bytes' => $flag->bytes,
                    'is_first_blood' => $isFirstBlood,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'total_bytes' => $isFirstBlood ? $flag->firstBloodBytes : $flag->bytes,
                    'solved_at' => $solvedAt->format('Y-m-d H:i:s'),
                    'flag_type' => $challange->flag_type,
                    'flag_name' => $flag->name ?? 'Flag'
                ];
                
                // Limit to 30 activities
                $count++;
                if ($count >= 30) {
                    break;
                }
            }
        }
        
        return response()->json([
            'activities' => $activities
        ]);
    }

    public function updateLastSeen(Request $request)
    {
        $user = $request->user();
        $user->last_seen = now();
        $user->save();
        broadcast(new UsersOnlineEvent());
        return response()->json(['message' => 'Last seen updated successfully']);
    }
    public function getOnlineUsersCount()
    {
        return rand(10, 100);
    }
    

}
