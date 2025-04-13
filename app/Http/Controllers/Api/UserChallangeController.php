<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserChallange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserChallangeController extends Controller
{
    /**
     * Store a newly created user challenge in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_uuid' => 'required|uuid|exists:challange_categories,uuid',
            'difficulty' => 'required|string',
            'flag' => 'required|string',
            'challange_file' => 'required|file|max:10240', // Max 10MB
            'answer_file' => 'required|file|max:10240', // Max 10MB
            'notes' => 'required|string',
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

        return response()->json([
            'status' => 'success',
            'message' => 'Challenge created successfully',
            'data' => $userChallange
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
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $challenges
        ]);
    }
} 