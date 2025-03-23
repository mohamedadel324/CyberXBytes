<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Challange;
use App\Models\LabCategory;
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
                    'name' => $lab->name,
                    'category_count' => $lab->lab_categories_count,
                    'challenge_count' => $lab->challenge_count,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $labs,
        ]);
    }

    public function getAllChallenges()
    {
        $challenges = Challange::all();
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
        $challenges = Challange::where('lab_category_uuid', $categoryUUID)->get();
        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
    }

    public function getChallengesByDifficulty($difficulty)
    {
        $challenges = Challange::where('difficulty', $difficulty)->get();
        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count()
        ]);
    }

    public function getChallenge($uuid)
    {
        $challenge = Challange::where('uuid',$uuid)->first();
        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => $challenge
        ]);
    }
}
