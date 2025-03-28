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
        $challenges = Challange::with('category:uuid,icon')->get();
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
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
        $challenges = Challange::with('category:uuid,icon')
            ->where('lab_category_uuid', $categoryUUID)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
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
        $challenges = Challange::with('category:uuid,icon')
            ->where('difficulty', $difficulty)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
        });

        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
    }

    public function getChallenge($uuid)
    {
        $challenge = Challange::with('category:uuid,icon')
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

        return response()->json([
            'status' => 'success',
            'data' => $challenge
        ]);
    }

    public function lastThreeChallenges()
    {
        $challenges = Challange::with('category:uuid,icon')
            ->latest()
            ->take(3)
            ->get();
        
        $challenges->each(function ($challenge) {
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
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

    public function SubmitChallange(Request $request)
    {
        $request->validate([
            'challange_uuid' => 'required|exists:challanges,uuid',
            'solution' => 'required|string',
        ]);
        $challenge = Challange::where('uuid', $request->challange_uuid)->first();
        if ($challenge->submissions()->where('user_uuid', auth('api')->user()->uuid)->where('solved', true)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already solved this challenge'
            ], 400);
        }
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

    public function checkIfSolved(Request $request)
    {
        $request->validate([
            'challange_uuid' => 'required|exists:challanges,uuid',
        ]);
        $challenge = Challange::where('uuid', $request->challange_uuid)->first();
        if ($challenge->submissions()->where('user_uuid', auth('api')->user()->uuid)->where('solved', true)->exists()) {
            return response()->json([
                'status' => 'success',
                'solved' => true
            ]);
        }
        return response()->json([
            'status' => 'success',
            'solved' => false
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