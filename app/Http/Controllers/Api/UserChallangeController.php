<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChallangeCategory;
use App\Models\UserChallange;
use App\Models\TermsPrivacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class UserChallangeController extends Controller
{
    /**
     * Store a newly created user challenge in storage.
     */
    public function store(Request $request)
    {
        // Check if the user has uploaded a challenge in the last 5 minutes
        $latestChallenge = UserChallange::where('user_uuid', Auth::user()->uuid)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if ($latestChallenge && $latestChallenge->created_at->diffInMinutes(now()) < 5) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only upload one challenge every 5 minutes. Please try again later.'
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_uuid' => 'required|uuid|exists:challange_categories,uuid',
            'difficulty' => 'required|string',
            'flag' => 'required|array',
            'challange_file' => 'required|file|mimes:zip|max:80000', // Max 80MB
            'answer_file' => 'required|file|mimes:zip|max:80000', // Max 80MB
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Store the challenge file
        $challengeFilePath = $request->file('challange_file')->store('challenges', 'public');
        
        // Store the answer file
        $answerFilePath = $request->file('answer_file')->store('answers', 'public');

        $userChallange = new UserChallange();
        $userChallange->user_uuid = Auth::user()->uuid;
        $userChallange->name = $request->name;
        $userChallange->description = $request->description;
        $userChallange->category_uuid = $request->category_uuid;
        $userChallange->difficulty = $request->difficulty;
        $userChallange->flag = $request->flag;
        $userChallange->challange_file = $challengeFilePath;
        $userChallange->answer_file = $answerFilePath;
        $userChallange->notes = $request->notes;
        $userChallange->status = 'pending';
        $userChallange->save();

        // Get the category icon
        $category = ChallangeCategory::find($request->category_uuid);
        $categoryIconUrl = $category ? asset('storage/' . $category->icon) : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Challenge created successfully',
            'data' => $userChallange,
            'category_icon_url' => $categoryIconUrl
        ], 201);
    }

    /**
     * Get statistics of user challenges.
     */
    public function getStatistics()
    {
        $userUuid = Auth::user()->uuid;
        
        $statistics = [
            'total' => UserChallange::where('user_uuid', $userUuid)->count(),
            'approved' => UserChallange::where('user_uuid', $userUuid)->where('status', 'approved')->count(),
            'declined' => UserChallange::where('user_uuid', $userUuid)->where('status', 'declined')->count(),
            'under_review' => UserChallange::where('user_uuid', $userUuid)->where('status', 'under_review')->count(),
            'pending' => UserChallange::where('user_uuid', $userUuid)->where('status', 'pending')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    /**
     * Get all challenges for the authenticated user.
     */
    public function getUserChallenges()
    {
        $userUuid = Auth::user()->uuid;
        
        $challenges = UserChallange::where('user_uuid', $userUuid)
            ->with(['category:uuid,name,icon'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($challenge) {
                $challenge->category->icon_url = asset('storage/' . $challenge->category->icon);
                return $challenge;
            });

        return response()->json([
            'status' => 'success',
            'data' => $challenges
        ]);
    }

    /**
     * Get terms for user challenges.
     */
    public function getTerms()
    {
        $termsPrivacy = TermsPrivacy::latest()->first();
        $terms = $termsPrivacy ? $termsPrivacy->terms_content : null;
        
        if (!$terms || !Storage::disk('public')->exists($terms)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terms file not found'
            ], 404);
        }
        
        // Generate a public URL for the file
        $fileUrl = asset('storage/' . $terms);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'terms_url' => $fileUrl
            ]
        ]);
    }

    /**
     * Get privacy policy for user challenges.
     */
    public function getPrivacy()
    {
        $termsPrivacy = TermsPrivacy::latest()->first();
        $privacy = $termsPrivacy ? $termsPrivacy->privacy_content : null;
        
        if (!$privacy || !Storage::disk('public')->exists($privacy)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Privacy policy file not found'
            ], 404);
        }
        
        // Generate a public URL for the file
        $fileUrl = asset('storage/' . $privacy);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'privacy_url' => $fileUrl
            ]
        ]);
    }

    /**
     * Get user's challenge streak and total approved challenges by username
     * 
     * @param string $user_name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserChallengeStreak(string $user_name = null)
    {
        if ($user_name) {
            // Get the user by username
            $user = \App\Models\User::where('user_name', $user_name)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }
            
            $userUuid = $user->uuid;
        } else {
            // Use authenticated user if no username provided
            $userUuid = Auth::user()->uuid;
        }
        
        // Get the user's approved challenges ordered by creation date
        $approvedChallenges = UserChallange::where('user_uuid', $userUuid)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Count total approved challenges
        $totalApproved = $approvedChallenges->count();
        
        // Calculate streak
        $currentStreak = $this->calculateStreak($approvedChallenges);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_approved_challenges' => $totalApproved,
                'current_streak' => $currentStreak
            ]
        ]);
    }
    
    /**
     * Calculate the user's current challenge streak.
     * 
     * @param \Illuminate\Database\Eloquent\Collection $challenges
     * @return int
     */
    private function calculateStreak($challenges)
    {
        if ($challenges->isEmpty()) {
            return 0;
        }
        
        $streak = 0;
        $maxStreak = 5; // Maximum streak value
        $lastActivity = now();
        
        // Check if the most recent challenge is within the last week
        $latestChallenge = $challenges->first();
        $daysSinceLastChallenge = $latestChallenge->created_at->diffInDays($lastActivity);
        
        // If it's been more than a week since the last challenge, reset streak
        if ($daysSinceLastChallenge > 7) {
            return 0;
        }
        
        // Process each challenge to calculate streak
        foreach ($challenges as $challenge) {
            // If challenge is recent (within 7 days of the previous one)
            if ($challenge->created_at->diffInDays($lastActivity) <= 7) {
                $streak++;
                $lastActivity = $challenge->created_at;
                
                // Cap streak at maximum value
                if ($streak >= $maxStreak) {
                    return $maxStreak;
                }
            } else {
                // Break the streak if more than 7 days between challenges
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * Get total approved challenges count
     */
    public function getTotalApprovedChallenges()
    {
        $userUuid = Auth::user()->uuid;
        
        $totalApproved = UserChallange::where('user_uuid', $userUuid)
            ->where('status', 'approved')
            ->count();
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_approved_challenges' => $totalApproved
            ]
        ]);
    }
} 