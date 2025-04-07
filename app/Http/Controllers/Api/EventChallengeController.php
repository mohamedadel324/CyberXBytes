<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventChallange;
use App\Models\EventChallangeSubmission;
use App\Models\EventTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventChallengeController extends Controller
{
    public function listChallenges($eventUuid)
    {
        $challenges = EventChallange::where('event_uuid', $eventUuid)
            ->with(['category:uuid,name,icon', 'solvedBy' => function($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            }])
            ->get()
            ->map(function ($challenge) {
                $isSolved = $challenge->solvedBy->isNotEmpty();
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
                    'solved_at' => $isSolved ? $challenge->solvedBy->first()->pivot->solved_at : null,
                    'attempts' => $isSolved ? $challenge->solvedBy->first()->pivot->attempts : 0
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
            'submission' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $challenge = EventChallange::with(['event', 'solvedBy'])->find($eventChallengeUuid);
        
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
                $points += $challenge->firstBloodBytes;
            }

            $submission->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Correct! Challenge solved.',
                'data' => [
                    'points' => $points,
                    'first_blood' => $isFirstBlood,
                    'attempts' => $submission->attempts
                ]
            ]);
        }

        $submission->save();

        return response()->json([
            'status' => 'error',
            'message' => 'Incorrect solution',
            'data' => [
                'attempts' => $submission->attempts
            ]
        ], 400);
    }

    public function scoreboard($eventUuid)
    {
        $teams = EventTeam::where('event_uuid', $eventUuid)
            ->with(['members.submissions' => function($query) use ($eventUuid) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
            }, 'members:uuid,user_name,profile_image'])
            ->get()
            ->map(function($team) {
                $totalPoints = 0;
                $solvedChallenges = collect();

                foreach ($team->members as $member) {
                    foreach ($member->submissions as $submission) {
                        $challenge = $submission->eventChallange;
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
                            'first_blood' => $isFirstBlood
                        ]);

                        $totalPoints += $points;
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
}
