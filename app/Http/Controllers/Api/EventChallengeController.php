<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\EventChallange;
use App\Models\EventChallangeSubmission;
use App\Models\EventChallangeFlagSubmission;
use App\Models\EventTeam;
use App\Models\User;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Event;
use App\Models\Team;

class EventChallengeController extends Controller
{
    use HandlesTimezones;

    /**
     * Validates event and team requirements before allowing access to APIs
     *
     * @param string $eventUuid
     * @param bool $ignoreEventEnded Whether to bypass the event end date check
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function validateEventAndTeamRequirements($eventUuid, $ignoreEventEnded = false)
    {
        $event = Event::where('uuid', $eventUuid)->first();
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Convert event dates to user timezone
        $userNow = $this->convertToUserTimezone(now());
        $eventStartDate = $this->convertToUserTimezone($event->start_date);
        $eventEndDate = $this->convertToUserTimezone($event->end_date);

        // Check if event has started
        if ($userNow->lt($eventStartDate)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has not started yet',
                'data' => [
                    'start_date' => $eventStartDate->format('Y-m-d H:i:s'),
                ]
            ], 403);
        }

        // Check if event has ended, but only if we're not ignoring this check
        if (!$ignoreEventEnded && $userNow->gt($eventEndDate)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event has ended',
                'data' => [
                    'end_date' => $eventEndDate->format('Y-m-d H:i:s')
                ]
            ], 403);
        }

        // Check if user is part of any team in this event
        $team = EventTeam::where('event_uuid', $eventUuid)
            ->where(function($query) use ($user) {
                $query->where('leader_uuid', $user->uuid)
                    ->orWhereHas('members', function($q) use ($user) {
                        $q->where('uuid', $user->uuid);
                    });
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team in this event'
            ], 403);
        }

        // Check if team meets the minimum and maximum member requirements
        $memberCount = $team->members()->count();
        if ($memberCount < $event->team_minimum_members || $memberCount > $event->team_maximum_members) {
            // Get all team members' UUIDs
            $memberUuids = $team->members()->pluck('user_uuid')->toArray();
            
            // Remove all user registrations for this event
            EventRegistration::where('event_uuid', $eventUuid)
                ->whereIn('user_uuid', $memberUuids)
                ->delete();
            
            // Delete the team
            $team->members()->detach(); // Remove all team member relationships first
            $team->delete();
            
            return response()->json([
                'status' => 'error',
                'message' => "Team has been removed because it doesn't meet the required member count (min: {$event->team_minimum_members}, max: {$event->team_maximum_members}, current: {$memberCount})"
            ], 403);
        }

        return null;
    }

    public function listChallenges($eventUuid)
    {
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the event to check if it's frozen
        $event = Event::where('uuid', $eventUuid)->first();
        $isFrozen = false;
        $freezeTime = null;
        
        if ($event && $event->freeze && $event->freeze_time) {
            $isFrozen = true;
            $freezeTime = $event->freeze_time;
        }

        // Get current user
        $user = Auth::user();

        // Get user's team for this event
        $team = EventTeam::where('event_uuid', $eventUuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->first();

        // Get team members' UUIDs
        $teamMemberUuids = $team ? $team->members()->pluck('user_uuid')->toArray() : [$user->uuid];

        // Get challenges with category and solvedBy, but not with flags (we'll load them separately)
        $challenges = EventChallange::with(['category:uuid,icon', 'solvedBy'])
            ->where('event_uuid', $eventUuid)
            ->orderBy("created_at", "DESC")
            ->get();
        
        // Load flags separately for calculations but don't include in final response
        $challengesWithFlags = EventChallange::with(['flags'])
            ->where('event_uuid', $eventUuid)
            ->get()
            ->keyBy('id');

        $challenges->each(function ($challenge) use ($isFrozen, $freezeTime, $teamMemberUuids, $eventUuid, $challengesWithFlags) {
            // Add id to the response
            $challenge->challenge_id = $challenge->id;
            
            $challenge->category_icon = $challenge->category->icon ?? null;
            unset($challenge->category);
            $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);
            
            // Add flag information
            $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
            
            // Check if this challenge is solved by the user or any team member
            $solved = false;
            
            if ($challenge->flag_type === 'single') {
                // For single flag type, check if any team member has solved it
                $solved = $challenge->solvedBy()->whereIn('user_uuid', $teamMemberUuids)->exists();
            } else if ($challenge->flag_type === 'multiple_all') {
                // For multiple_all, check if all flags are solved by the team collectively
                $challengeWithFlags = $challengesWithFlags[$challenge->id] ?? null;
                $allFlagIds = $challengeWithFlags && $challengeWithFlags->flags ? $challengeWithFlags->flags->pluck('id')->toArray() : [];
                
                if (!empty($allFlagIds)) {
                    $solvedFlagIds = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                        ->whereIn('user_uuid', $teamMemberUuids)
                        ->where('solved', true)
                        ->pluck('event_challange_flag_id')
                        ->unique()
                        ->toArray();
                    
                    // Team has solved if all flags are solved
                    $solved = count(array_intersect($solvedFlagIds, $allFlagIds)) === count($allFlagIds);
                }
            } else if ($challenge->flag_type === 'multiple_individual') {
                // For multiple_individual, check if at least one flag is solved
                $challengeWithFlags = $challengesWithFlags[$challenge->id] ?? null;
                $flagIds = $challengeWithFlags && $challengeWithFlags->flags ? $challengeWithFlags->flags->pluck('id')->toArray() : [];
                
                $solved = !empty($flagIds) && EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $flagIds)
                    ->whereIn('user_uuid', $teamMemberUuids)
                    ->where('solved', true)
                    ->exists();
            }
            
            $challenge->solved = $solved;
            
            // Get solved count for the challenge
            $solvedCountQuery = $challenge->submissions()->where('solved', true);
            
            // If frozen, only count submissions before freeze time
            if ($isFrozen) {
                $solvedCountQuery->where('created_at', '<=', $freezeTime);
            }
            
            // For multiple_individual, need to count each flag solved as its own challenge
            if ($challenge->flag_type === 'multiple_individual') {
                // Get the flags from our separate collection
                $challengeWithFlags = $challengesWithFlags[$challenge->id] ?? null;
                $flagIds = $challengeWithFlags && $challengeWithFlags->flags ? $challengeWithFlags->flags->pluck('id')->toArray() : [];
                
                // Get count of all solved flags for this challenge across all users
                $solvedCount = !empty($flagIds) ? EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $flagIds)
                    ->where('solved', true) : collect();
                    
                // Apply freeze time filter if needed
                if ($isFrozen) {
                    $solvedCount->where('solved_at', '<=', $freezeTime);
                }
                
                $solvedCount = $solvedCount->count();
            } 
            // For multiple_all, need to count teams that have solved ALL flags
            else if ($challenge->flag_type === 'multiple_all') {
                // Get all flag IDs for this challenge
                $allFlagIds = $challenge->flags->pluck('id')->toArray();
                if (empty($allFlagIds)) {
                    $solvedCount = 0;
                } else {
                    // Get a list of teams for this event
                    $teams = EventTeam::where('event_uuid', $eventUuid)->get();
                    
                    // Count teams that solved all flags
                    $teamsSolvedAll = 0;
                    
                    foreach ($teams as $checkTeam) {
                        // Get all flags solved by any team member
                        $teamMembers = $checkTeam->members()->pluck('uuid')->toArray();
                        
                        $solvedFlagIds = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                            ->whereIn('user_uuid', $teamMembers)
                            ->where('solved', true);
                            
                        // Apply freeze time if needed
                        if ($isFrozen) {
                            $solvedFlagIds->where('solved_at', '<=', $freezeTime);
                        }
                        
                        $solvedFlagIds = $solvedFlagIds->pluck('event_challange_flag_id')
                            ->unique()
                            ->toArray();
                            
                        // Check if team solved all flags
                        if (count(array_intersect($solvedFlagIds, $allFlagIds)) === count($allFlagIds)) {
                            $teamsSolvedAll++;
                        }
                    }
                    
                    $solvedCount = $teamsSolvedAll;
                }
            } else {
                $solvedCount = $solvedCountQuery->count();
            }
            
            $challenge->solved_count = $solvedCount;
            
            // Get first blood information
            $firstBlood = null;
            
            // For multiple_all, we need to find the first team to solve ALL flags
            if ($challenge->flag_type === 'multiple_all' && $solvedCount > 0) {
                // Find the first team to solve all flags
                $challengeWithFlags = $challengesWithFlags[$challenge->id] ?? null;
                $allFlagIds = $challengeWithFlags && $challengeWithFlags->flags ? $challengeWithFlags->flags->pluck('id')->toArray() : [];
                if (!empty($allFlagIds)) {
                    // Get a list of teams for this event
                    $teams = EventTeam::where('event_uuid', $eventUuid)->get();
                    
                    $firstTeamToSolveAll = null;
                    $firstTeamSolvedAt = null;
                    
                    foreach ($teams as $checkTeam) {
                        // Get all flags solved by any team member
                        $teamMembers = $checkTeam->members()->pluck('uuid')->toArray();
                        
                        $solvedFlagIds = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                            ->whereIn('user_uuid', $teamMembers)
                            ->where('solved', true);
                            
                        // Apply freeze time if needed
                        if ($isFrozen) {
                            $solvedFlagIds->where('solved_at', '<=', $freezeTime);
                        }
                        
                        $solvedFlagIds = $solvedFlagIds->pluck('event_challange_flag_id')
                            ->unique()
                            ->toArray();
                            
                        // Check if team solved all flags
                        if (count(array_intersect($solvedFlagIds, $allFlagIds)) === count($allFlagIds)) {
                            // Find the latest solved_at time for this team (when they completed all flags)
                            $latestSolvedAt = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                                ->whereIn('user_uuid', $teamMembers)
                                ->where('solved', true);
                                
                            if ($isFrozen) {
                                $latestSolvedAt->where('solved_at', '<=', $freezeTime);
                            }
                            
                            $latestSolvedAt = $latestSolvedAt->max('solved_at');
                            
                            // Check if this is the first team to solve all flags
                            if (!$firstTeamSolvedAt || $latestSolvedAt < $firstTeamSolvedAt) {
                                $firstTeamToSolveAll = $checkTeam;
                                $firstTeamSolvedAt = $latestSolvedAt;
                            }
                        }
                    }
                    
                    // Set first blood info for the first team to solve all flags
                    if ($firstTeamToSolveAll) {
                        // Get a representative member from the first team
                        $firstTeamMember = $firstTeamToSolveAll->members()->first();
                        if ($firstTeamMember) {
                            $firstBlood = [
                                'user_name' => $firstTeamMember->user_name, // Show actual username as requested
                                'profile_image' => $firstTeamMember->profile_image ? asset('storage/' . $firstTeamMember->profile_image) : null,
                                'team_name' => $firstTeamToSolveAll->name,
                                'solved_at' => $firstTeamSolvedAt
                            ];
                        }
                    }
                }
            }
            // For other challenge types, use the original logic
            else if ($solvedCount > 0) {
                $firstSolverQuery = $challenge->submissions()
                    ->where('solved', true)
                    ->orderBy('created_at', 'asc');
                
                // If frozen, only consider submissions before freeze time
                if ($isFrozen) {
                    $firstSolverQuery->where('created_at', '<=', $freezeTime);
                }
                
                $firstSolver = $firstSolverQuery->first();
                
                if ($firstSolver) {
                    // Get user information for first blood
                    $firstBloodUser = User::where('uuid', $firstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                    if ($firstBloodUser) {
                        $firstBlood = [
                            'user_name' => $firstBloodUser->user_name, // Show actual username as requested
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
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                    'solved_count' => $solvedCount,
                ];
            }
            // For multiple flag types
            else if ($challenge->flag_type === 'multiple_all' || $challenge->flag_type === 'multiple_individual') {
                // Get the flags from our separate collection without exposing them in the response
                $challengeWithFlags = $challengesWithFlags[$challenge->id] ?? null;
                $flags = $challengeWithFlags && $challengeWithFlags->flags ? $challengeWithFlags->flags : collect();
                
                // Store the flags count without exposing the flags themselves
                $challenge->flags_count = $flags->count();
                
                // For multiple_all, keep original bytes and firstBloodBytes
                if ($challenge->flag_type === 'multiple_all') {
                    $challenge->total_bytes = $challenge->bytes;
                    $challenge->total_first_blood_bytes = $challenge->firstBloodBytes;
                }
                // For multiple_individual, modify the existing bytes and firstBloodBytes properties
                else if ($challenge->flag_type === 'multiple_individual') {
                    $totalBytes = 0;
                    $totalFirstBloodBytes = 0;
                    
                    // Sum up bytes from all flags
                    foreach ($flags as $flag) {
                        $totalBytes += $flag->bytes ?? 0;
                        $totalFirstBloodBytes += $flag->firstBloodBytes ?? 0;
                    }
                    
                    // Update the existing bytes and firstBloodBytes properties
                    $challenge->bytes = $totalBytes;
                    $challenge->firstBloodBytes = $totalFirstBloodBytes;
                }
            }
        });

        // Remove any flags property from the response
        $challenges->each(function ($challenge) {
            // Explicitly unset any flags property that might exist
            if (isset($challenge->flags)) {
                unset($challenge->flags);
            }
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $challenges,
            'count' => $challenges->count(),
            'frozen' => $isFrozen,
            'freeze_time' => $freezeTime ? $freezeTime->format('Y-m-d H:i:s') : null
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

        // Start a database transaction to prevent race conditions
        return DB::transaction(function() use ($request, $eventChallengeUuid) {
            // Use pessimistic locking to prevent concurrent submissions
            $challenge = EventChallange::with(['event', 'solvedBy', 'flags'])
                ->lockForUpdate()
                ->find($eventChallengeUuid);
            
            if (!$challenge) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Challenge not found'
                ], 404);
            }

            // Validate event and team requirements
            $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
            if ($validationResponse) {
                return $validationResponse;
            }

            // Get user's team for this event - already verified in validateEventAndTeamRequirements
            $team = EventTeam::where('event_uuid', $challenge->event_uuid)
                ->whereHas('members', function($query) {
                    $query->where('user_uuid', Auth::user()->uuid);
                })
                ->first();

            // Get team members' UUIDs
            $teamMemberUuids = $team->members()->pluck('user_uuid')->toArray();

            // Handle single flag type
            if ($challenge->flag_type === 'single') {
                // Recheck with fresh data inside the transaction to prevent race conditions
                $freshChallenge = EventChallange::with(['solvedBy'])
                    ->lockForUpdate()
                    ->find($eventChallengeUuid);
                
                // Check if user has already solved this challenge
                if ($freshChallenge->solvedBy->contains('uuid', Auth::user()->uuid)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You have already solved this challenge',
                        'data' => [
                            'is_first_blood' => false
                        ]
                    ], 400);
                }

                // Check if any team member has already solved this challenge
                $teamMemberSolved = $freshChallenge->solvedBy()->whereIn('user_uuid', $teamMemberUuids)->exists();
                if ($teamMemberSolved) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Someone from your team has already solved this challenge',
                        'data' => [
                            'is_first_blood' => false
                        ]
                    ], 400);
                }

            if($challenge->flag == $request->submission) {
                // Create the submission
                $submission = EventChallangeSubmission::create([
                'event_challange_id' => $challenge->id,
                    'user_uuid' => Auth::user()->uuid,
                    'submission' => $request->submission,
                    'solved' => true,
                    'solved_at' => now(),
                    'ip' => $request->ip(),
                ]);
                
                // Calculate points and check for first blood
                $points = $challenge->bytes;
                $firstBloodPoints = 0;
                
                // Get first solver for this challenge
                $firstSolver = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc')
                    ->first();
                
                // Check if this user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === Auth::user()->uuid;
                if ($isFirstBlood) {
                    $firstBloodPoints = $challenge->firstBloodBytes;
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'The flag is correct',
                    'data' => [
                        'flag_type' => 'single',
                        'flag' => $challenge->flag,
                        'points' => $points,
                        'first_blood_points' => $firstBloodPoints,
                        'is_first_blood' => $isFirstBlood
                    ]
                ], 200);
            }

            // Record failed attempt
            EventChallangeSubmission::create([
                'event_challange_id' => $challenge->id,
                'user_uuid' => Auth::user()->uuid,
                'submission' => $request->submission,
                'solved' => false,
                'ip' => $request->ip(),

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
            // Find the flag that matches the submission
            $matchedFlag = null;
            foreach ($challenge->flags as $flag) {
                if ($request->submission === $flag->flag) {
                    $matchedFlag = $flag;
                    break;
                }
            }
            
            if (!$matchedFlag) {
                // No matching flag found, record the attempt
                EventChallangeFlagSubmission::create([
                    'event_challange_flag_id' => $challenge->flags->first()->id,
                    'user_uuid' => Auth::user()->uuid,
                    'submission' => $request->submission,
                    'solved' => false,
                    'attempts' => 1,
                    "ip" => request()->ip()
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
            
            // Check if user has already solved this flag - using lockForUpdate to prevent race conditions
            $flagSubmission = EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                ->where('user_uuid', Auth::user()->uuid)
                ->where('solved', true)
                ->lockForUpdate()
                ->first();

            if ($flagSubmission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already solved this flag',
                ], 400);
            }

            // For multiple_all challenges, we want each team member to be able to solve each flag
            // But first check if the team has already completed all flags for this challenge
            if ($challenge->flag_type === 'multiple_all') {
                // Get all flags available for this challenge
                $allFlags = $challenge->flags->pluck('id')->toArray();
                $totalFlags = count($allFlags);
                
                // Get all unique flags solved by any team member
                $teamSolvedFlags = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlags)
                    ->whereIn('user_uuid', $teamMemberUuids)
                    ->where('solved', true)
                    ->pluck('event_challange_flag_id')
                    ->unique()
                    ->count();
                
                // Check if the team has already completed all flags
                if ($teamSolvedFlags === $totalFlags) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Your team has already completed this challenge by solving all flags',
                    ], 400);
                }
                
                // If not all flags are solved yet, continue to allow this submission
            } else {
                // For non-multiple_all challenges, continue with the original behavior
                // Check if any team member has already solved this flag - using lockForUpdate to prevent race conditions
                $teamMemberSolved = EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                    ->whereIn('user_uuid', $teamMemberUuids)
                    ->where('solved', true)
                    ->lockForUpdate()
                    ->exists();
    
                if ($teamMemberSolved) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Someone from your team has already solved this flag',
                    ], 400);
                }
            }

            // Record the solved flag
            $submission = EventChallangeFlagSubmission::create([
                'event_challange_flag_id' => $matchedFlag->id,
                'user_uuid' => Auth::user()->uuid,
                'submission' => $request->submission,
                'solved' => true,
                'solved_at' => now(),
                'attempts' => 1,
                "ip"=> request()->ip(),
            ]);

            // Check if all flags are solved for multiple_all type
            $allFlagsSolved = false;
            $points = 0;
            $firstBloodPoints = 0;
            $isFirstBlood = false;
            
            if ($challenge->flag_type === 'multiple_all') {
                // Get all flags available for this challenge
                $allFlags = $challenge->flags->pluck('id')->toArray();
                $totalFlags = count($allFlags);
                
                // Get all flags solved by any team member
                $teamSolvedFlags = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlags)
                    ->whereIn('user_uuid', $teamMemberUuids)
                    ->where('solved', true)
                    ->pluck('event_challange_flag_id')
                    ->unique()
                    ->values()
                    ->toArray();
                
                $teamSolvedCount = count($teamSolvedFlags);
                
                // Check if the team has solved all flags
                $allFlagsSolved = count(array_intersect($teamSolvedFlags, $allFlags)) === $totalFlags;
                
                // Only award points if ALL flags are solved
                if ($allFlagsSolved) {
                    // Always award base points for solving all flags
                    $points = $challenge->bytes;
                        
                    // Check if this team was the first to solve all flags
                    // We need to find all teams that have solved all flags
                    // and check if this team was the first
                    $isFirstBlood = false;
                    
                    // Get all teams for this event
                    $eventTeams = EventTeam::where('event_uuid', $challenge->event_uuid)->get();
                    
                    // Track which teams have solved all flags and when they completed the last flag
                    $teamsCompletedAllFlags = [];
                    
                    foreach ($eventTeams as $eventTeam) {
                        $teamMembers = $eventTeam->members()->pluck('user_uuid')->toArray();
                        
                        // Check if this team has solved all flags
                        $teamSolvedFlagCount = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlags)
                            ->whereIn('user_uuid', $teamMembers)
                            ->where('solved', true)
                            ->pluck('event_challange_flag_id')
                            ->unique()
                            ->count();
                        
                        if ($teamSolvedFlagCount === $totalFlags) {
                            // Get the timestamp of the last flag solved by this team
                            $lastFlagSolvedAt = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlags)
                                ->whereIn('user_uuid', $teamMembers)
                                ->where('solved', true)
                                ->orderBy('solved_at', 'desc')
                                ->first()
                                ->solved_at;
                            
                            $teamsCompletedAllFlags[$eventTeam->id] = $lastFlagSolvedAt;
                        }
                    }
                    
                    // If there are teams that have completed all flags
                    if (!empty($teamsCompletedAllFlags)) {
                        // Sort by completion time (ascending)
                        asort($teamsCompletedAllFlags);
                        
                        // Get the ID of the first team to complete all flags
                        $firstTeamId = array_key_first($teamsCompletedAllFlags);
                        
                        // Check if current team is the first team
                        $isFirstBlood = ($firstTeamId == $team->id);
                    } else {
                        // If no team has completed all flags yet, this team is the first
                        $isFirstBlood = true;
                    }
                        
                    if ($isFirstBlood) {
                        $firstBloodPoints = $challenge->firstBloodBytes;
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
                    // If not all flags are solved, still show progress information
                    $completionPercentage = round(($teamSolvedCount / $totalFlags) * 100);
                    $partialPoints = round(($teamSolvedCount / $totalFlags) * $challenge->bytes);
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'The flag is correct',
                        'data' => [
                            'flag_type' => $challenge->flag_type,
                            'flag_name' => $matchedFlag->name,
                            'all_flags_solved' => false,
                            'flags_solved' => $teamSolvedCount,
                            'total_flags' => $totalFlags,
                            'completion_percentage' => $completionPercentage,
                            'partial_points' => $partialPoints,
                        ]
                    ], 200);
                }
            } else if ($challenge->flag_type === 'multiple_individual') {
                // For multiple_individual, points are awarded immediately for each flag
                // Always award base points for solving the flag
                $points = $matchedFlag->bytes;
                
                // Check if this is first blood for this flag
                $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $matchedFlag->id)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc')
                    ->first();
                
                // Check if the current user is the first solver
                $isFirstBlood = $firstSolver && $firstSolver->user_uuid === Auth::user()->uuid;
                
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
            }
            
            // This should never be reached, but added as a fallback
            return response()->json([
                'status' => 'success',
                'message' => 'The flag is correct',
                'data' => [
                    'flag_type' => $challenge->flag_type,
                    'flag_name' => $matchedFlag->name,
                    'flag' => $matchedFlag->flag,
                    'is_first_blood' => false,
                ]
            ], 200);
        }
        
        }, 5); // End of transaction with 5 retries on deadlock
    }

    public function scoreboard($eventUuid)
    {
        // Get the event
        $event = Event::where('uuid', $eventUuid)->first();
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }
        
        // Get all teams for this event
        $teams = EventTeam::where('event_uuid', $event->uuid)->get();
        
        // Determine event phase based on current time
        $now = now();
        $eventPhase = 'running';
        
        if ($event->end_time && $now > $event->end_time) {
            $eventPhase = 'ended';
        } elseif ($event->freeze && $event->freeze_time && $now > $event->freeze_time) {
            $eventPhase = 'frozen';
        } elseif ($event->start_time && $now < $event->start_time) {
            $eventPhase = 'not_started';
        }
        
        $teamsData = [];
        
        // Process each team
        foreach ($teams as $team) {
            // Initialize default values
            $totalPoints = 0;
            $solvedChallengesCount = 0;
            $firstBloodCount = 0;
            
            // Use the working getTeamById method from EventTeamController to get accurate points
            // This ensures consistency across the application
            $teamController = new \App\Http\Controllers\Api\EventTeamController();
            $teamResponse = $teamController->getTeamById($team->id);
            $teamData = json_decode($teamResponse->getContent(), true);
            
            // If we got valid data back from the API
            if (isset($teamData['status']) && $teamData['status'] === 'success' && isset($teamData['data'])) {
                // Extract statistics data from the response
                $stats = isset($teamData['data']['statistics']) ? $teamData['data']['statistics'] : [];
                
                // Get total statistics
                $totalPoints = isset($stats['total_bytes']) ? $stats['total_bytes'] : 0;
                $totalMaskedBytes = isset($stats['total_masked_bytes']) ? $stats['total_masked_bytes'] : 0;
                $firstBloodCount = isset($stats['total_first_blood_count']) ? $stats['total_first_blood_count'] : 0;
                $solvedChallengesCount = isset($stats['total_challenges_solved']) ? $stats['total_challenges_solved'] : 0;
            }
            
            // Build the final team data
            $teamsData[] = [
                'team_uuid' => $team->id,
                'team_name' => $team->name,
                'team_icon' => $team->icon_url, 
                'points' => $totalPoints,
                'total_bytes' => $totalPoints, // Using total_bytes as points to maintain existing structure
                'total_first_blood_count' => $firstBloodCount,
                'total_challenges_solved' => $solvedChallengesCount,
                'challenges_solved' => $solvedChallengesCount, // Keeping this for backward compatibility
                'first_blood_count' => $firstBloodCount, // Keeping this for backward compatibility
                'members' => $team->members->map(function($member) {
                    return [
                        'user_name' => $member->user_name,
                        'profile_image' => $member->profile_image ? asset('storage/' . $member->profile_image) : null
                    ];
                }),
            ];
        }
        
        // Sort teams by points first, then by first_blood_count if points are equal
        usort($teamsData, function($a, $b) {
            // First sort by points
            if ($b['points'] != $a['points']) {
                return $b['points'] - $a['points'];
            }
            
            // If points are equal, sort by first_blood_count
            return $b['total_first_blood_count'] - $a['total_first_blood_count'];
        });

        return response()->json([
            'status' => 'success',
            'data' => $teamsData,
            'frozen' => $event->freeze,
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
        ])->where('id', $eventChallengeUuid)->first();
        
        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
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
                    'solved_at' => $isSolved ? $this->formatInUserTimezone($challenge->solvedBy->first()->pivot->solved_at) : null
                ]
            ]);
        }
        
        // For multiple flag challenges
        $flags = $challenge->flags->map(function($flag) {
            $isFlagSolved = $flag->solvedBy->isNotEmpty();
            $solvedAt = $isFlagSolved ? $this->formatInUserTimezone($flag->solvedBy->first()->pivot->solved_at) : null;
            
            // Base flag data
            $flagData = [
                'id' => $flag->id,
                'solved' => $isFlagSolved,
                'solved_at' => $solvedAt
            ];

            // For multiple_all type, include name and description
            if ($flag->eventChallange->flag_type === 'multiple_all') {
                $flagData['name'] = $flag->name;
                $flagData['description'] = $flag->description;
            }
            // For multiple_individual type, include only name
            else if ($flag->eventChallange->flag_type === 'multiple_individual') {
                $flagData['name'] = $flag->name;
            }
            
            return $flagData;
        });
        
        $solvedCount = $flags->where('solved', true)->count();
        $allFlagsSolved = $solvedCount === $challenge->flags->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'challenge_id' => $challenge->id,
                'challenge_title' => $challenge->title,
                'flag_type' => $challenge->flag_type,
                'total_flags' => $challenge->flags->count(),
                'solved_flags' => $solvedCount,
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
        $challenge = EventChallange::with(['flags', 'solvedBy', 'flags.solvedBy'])->where('id', $eventChallengeUuid)->firstOrFail();
        
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }
        
        // Get user's team for this event
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->first();

        // Get team members' UUIDs
        $teamMemberUuids = $team ? $team->members()->pluck('user_uuid')->toArray() : [$user->uuid];
        
        // For single flag challenges
        if ($challenge->flag_type === 'single') {
            // Check if any team member has solved this challenge
            $isSolved = $challenge->solvedBy()->whereIn('user_uuid', $teamMemberUuids)->exists();
            $submission = null;
            
            if ($isSolved) {
                // Get the user's own submission if it exists
                $userSubmission = $challenge->solvedBy->where('uuid', $user->uuid)->first();
                
                if ($userSubmission) {
                    $submission = $userSubmission->pivot;
                } else {
                    // Get the first team member's submission
                    $teamMemberSubmission = $challenge->solvedBy()
                        ->whereIn('user_uuid', $teamMemberUuids)
                        ->orderBy('event_challange_submissions.created_at')
                        ->first();
                    
                    if ($teamMemberSubmission) {
                        $submission = $teamMemberSubmission->pivot;
                    }
                }
            }
            
            // Get first blood if there are any solvers
            $firstBlood = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                ->where('solved', true)
                ->oldest('created_at')
                ->first();
            
            $isFirstBlood = false;
            if ($submission && $firstBlood) {
                // Check if any team member got first blood
                $isFirstBlood = in_array($firstBlood->user_uuid, $teamMemberUuids);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_solved' => $isSolved,
                    'flag_type' => 'single',
                    'solved_flags' => $isSolved ? 1 : 0,
                    'total_flags' => 1,
                    'all_flags_solved' => $isSolved,
                    'points' => $isSolved ? $challenge->bytes : 0,
                    'first_blood_points' => $isFirstBlood ? $challenge->firstBloodBytes : 0,
                    'solved_flags_data' => $isSolved ? [
                        [
                            'id' => $challenge->id,
                            'name' => $challenge->title,
                            'bytes' => $challenge->bytes,
                            'first_blood_bytes' => $challenge->firstBloodBytes,
                            'solved_at' => $submission && $submission->solved_at ? $this->formatInUserTimezone($submission->solved_at) : null,
                            'is_first_blood' => $isFirstBlood
                        ]
                    ] : []
                ]
            ]);
        }
        
        // For multiple flag challenges
        $solvedFlags = collect();
        $points = 0;
        $firstBloodPoints = 0;
        
        // Get all flags solved by any team member
        $teamSolvedFlags = collect();
        // Get all flags solved by the current user only
        $userSolvedFlags = collect();
        
        foreach ($challenge->flags as $flag) {
            // First, check if flag is solved by the user personally
            $userFlagSubmission = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                ->where('user_uuid', $user->uuid)
                ->where('solved', true)
                ->first();
            
            // For tracking team solves (used for scoring and all_flags_solved)
            $teamFlagSubmissions = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                ->whereIn('user_uuid', $teamMemberUuids)
                ->where('solved', true)
                ->get();
                
            $isTeamSolved = $teamFlagSubmissions->isNotEmpty();
            $isUserSolved = $userFlagSubmission !== null;
            
            // For multiple_all, we only want to show flags solved by the current user
            $shouldAddToSolvedFlags = $isUserSolved || 
                                     ($challenge->flag_type !== 'multiple_all' && $isTeamSolved);
                
            if ($shouldAddToSolvedFlags) {
                // If user solved it, use user's submission; otherwise use team member's
                $flagSubmission = $userFlagSubmission ?: $teamFlagSubmissions->first();
                
                // Get first blood information
                $firstBlood = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true)
                    ->oldest('created_at')
                    ->first();
                
                $isFirstBlood = false;
                if ($firstBlood) {
                    // Check if any team member got first blood
                    $isFirstBlood = in_array($firstBlood->user_uuid, $teamMemberUuids);
                }
                
                if ($challenge->flag_type === 'multiple_individual') {
                    $points += $flag->bytes;
                    if ($isFirstBlood) {
                        $firstBloodPoints += $flag->firstBloodBytes;
                    }
                }
                
                $solvedFlags->push([
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved_at' => $flagSubmission->solved_at ? $this->formatInUserTimezone($flagSubmission->solved_at) : null,
                    'is_first_blood' => $isFirstBlood,
                    'solved_by' => $flagSubmission->user_uuid === $user->uuid ? 'you' : 'team'
                ]);
                
                if ($isUserSolved) {
                    $userSolvedFlags->push($flag->id);
                }
                
                if ($isTeamSolved) {
                    $teamSolvedFlags->push($flag->id);
                }
            }
        }
        
        // For team-based scoring and achievements (kept as is)
        $allFlagsSolved = $teamSolvedFlags->count() === $challenge->flags->count() && $challenge->flags->count() > 0;
        
        // For multiple_all type, add points only if all flags are solved by the team
        if ($challenge->flag_type === 'multiple_all' && $allFlagsSolved) {
            $points = $challenge->bytes;
            
            // Check if team has first blood for all flags
            $hasAllFirstBlood = true;
            foreach ($challenge->flags as $flag) {
                $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true)
                    ->oldest('created_at')
                    ->first();
                
                if (!$firstSolver || !in_array($firstSolver->user_uuid, $teamMemberUuids)) {
                    $hasAllFirstBlood = false;
                    break;
                }
            }
            
            if ($hasAllFirstBlood) {
                $firstBloodPoints = $challenge->firstBloodBytes;
            }
        }
        
        // For multiple_all, we need user-specific solved status
        $userAllFlagsSolved = false;
        if ($challenge->flag_type === 'multiple_all') {
            $userAllFlagsSolved = $userSolvedFlags->count() === $challenge->flags->count() && $challenge->flags->count() > 0;
        } else {
            $userAllFlagsSolved = $allFlagsSolved;
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'is_solved' => $solvedFlags->isNotEmpty(),
                'flag_type' => $challenge->flag_type,
                // For multiple_all, only count flags solved by the current user
                'solved_flags' => $challenge->flag_type === 'multiple_all' ? $userSolvedFlags->count() : $solvedFlags->count(),
                'total_flags' => $challenge->flags->count(),
                // For multiple_all, check if the user personally solved all flags
                'all_flags_solved' => $challenge->flag_type === 'multiple_all' ? $userAllFlagsSolved : $allFlagsSolved,
                'points' => $points,
                'first_blood_points' => $firstBloodPoints,
                'solved_flags_data' => $solvedFlags
            ]
        ]);
    }


    public function showChallenge($challengeUuid)
    {
        $challenge = EventChallange::with(['category:uuid,icon', 'flags'])
            ->where('id', $challengeUuid)
            ->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Check if the event has a frozen scoreboard
        $event = Event::where('uuid', $challenge->event_uuid)->first();
        $isFrozen = $event && $event->freeze && $event->freeze_time;
        $freezeTime = $isFrozen ? $event->freeze_time : null;

        // Get current user
        $user = Auth::user();

        // Get user's team for this event
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->first();

        // Get team members' UUIDs
        $teamMemberUuids = $team ? $team->members()->pluck('user_uuid')->toArray() : [$user->uuid];

        // Check if the challenge is solved by the user or any team member
        $teamSolved = false;
        
        if ($challenge->flag_type === 'single') {
            // For single flag type, check if any team member has solved it
            $teamSolved = $challenge->solvedBy()->whereIn('user_uuid', $teamMemberUuids)->exists();
        } else {
            // For multiple flag types, check if all flags are solved by the team collectively
            if ($challenge->flag_type === 'multiple_all') {
                // Get all flags for this challenge
                $allFlagIds = $challenge->flags->pluck('id')->toArray();
                
                // Get all flags solved by any team member
                $solvedFlagIds = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                    ->whereIn('user_uuid', $teamMemberUuids)
                    ->where('solved', true)
                    ->pluck('event_challange_flag_id')
                    ->unique()
                    ->toArray();
                
                // Team has solved if all flags are solved
                $teamSolved = count(array_intersect($solvedFlagIds, $allFlagIds)) === count($allFlagIds);
            } else if ($challenge->flag_type === 'multiple_individual') {
                // For multiple_individual, check if at least one flag is solved
                $teamSolved = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $challenge->flags->pluck('id'))
                    ->whereIn('user_uuid', $teamMemberUuids)
                    ->where('solved', true)
                    ->exists();
            }
        }

        $challenge->category_icon = $challenge->category->icon ?? null;
        unset($challenge->category);
        $challenge->difficulty = $this->translateDifficulty($challenge->difficulty);

        // Get solved count for the challenge
        $solvedCountQuery = $challenge->submissions()->where('solved', true);
        if ($isFrozen) {
            $solvedCountQuery->where('created_at', '<=', $freezeTime);
        }
        $solvedCount = $solvedCountQuery->count();
        
        // Add flag information
        $challenge->flag_type_description = $this->getFlagTypeDescription($challenge->flag_type);
        
        // Get first blood information
        $firstBlood = null;
        if ($solvedCount > 0) {
            $firstSolverQuery = $challenge->submissions()
                ->where('solved', true)
                ->orderBy('created_at', 'asc');
                
            if ($isFrozen) {
                $firstSolverQuery->where('created_at', '<=', $freezeTime);
            }
            
            $firstSolver = $firstSolverQuery->first();
            
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
            
            // Calculate how many teams have solved ALL flags
            $allFlagIds = $challenge->flags->pluck('id')->toArray();
            
            // Get a list of teams for this event
            $teams = EventTeam::where('event_uuid', $challenge->event_uuid)->get();
            
            // Count teams that solved all flags
            $teamsSolvedAll = 0;
            $firstTeamToSolveAll = null;
            $firstTeamSolvedAt = null;
            
            foreach ($teams as $checkTeam) {
                // Get all flags solved by any team member
                $teamMembers = $checkTeam->members()->pluck('uuid')->toArray();
                
                $solvedFlagIds = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                    ->whereIn('user_uuid', $teamMembers)
                    ->where('solved', true);
                    
                // Apply freeze time if needed
                if ($isFrozen) {
                    $solvedFlagIds->where('solved_at', '<=', $freezeTime);
                }
                
                $solvedFlagIds = $solvedFlagIds->pluck('event_challange_flag_id')
                    ->unique()
                    ->toArray();
                    
                // Check if team solved all flags
                if (count(array_intersect($solvedFlagIds, $allFlagIds)) === count($allFlagIds)) {
                    $teamsSolvedAll++;
                    
                    // Find the latest solved_at time for this team (when they completed all flags)
                    $latestSolvedAt = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $allFlagIds)
                        ->whereIn('user_uuid', $teamMembers)
                        ->where('solved', true);
                        
                    if ($isFrozen) {
                        $latestSolvedAt->where('solved_at', '<=', $freezeTime);
                    }
                    
                    $latestSolvedAt = $latestSolvedAt->max('solved_at');
                    
                    // Check if this is the first team to solve all flags
                    if (!$firstTeamSolvedAt || $latestSolvedAt < $firstTeamSolvedAt) {
                        $firstTeamToSolveAll = $checkTeam;
                        $firstTeamSolvedAt = $latestSolvedAt;
                    }
                }
            }
            
            // Get first blood info for the first team to solve all flags
            $multipleAllFirstBlood = null;
            if ($firstTeamToSolveAll) {
                // Get a representative member from the first team
                $firstTeamMember = $firstTeamToSolveAll->members()->first();
                if ($firstTeamMember) {
                    $multipleAllFirstBlood = [
                        'user_name' => $firstTeamMember->user_name, // Show actual username
                        'profile_image' => $firstTeamMember->profile_image ? asset('storage/' . $firstTeamMember->profile_image) : null,
                        'team_name' => $firstTeamToSolveAll->name,
                        'solved_at' => $firstTeamSolvedAt
                    ];
                }
            }
            
            foreach ($challenge->flags as $flag) {
                // Get individual flag solved count
                $flagSolvedCountQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true);
                    
                if ($isFrozen) {
                    $flagSolvedCountQuery->where('solved_at', '<=', $freezeTime);
                }
                
                $flagSolvedCount = $flagSolvedCountQuery->count();
                
                // Get first blood for this individual flag
                $flagFirstBloodQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc');
                    
                if ($isFrozen) {
                    $flagFirstBloodQuery->where('solved_at', '<=', $freezeTime);
                }
                
                $flagFirstBloodSolver = $flagFirstBloodQuery->first();
                $flagFirstBlood = null;
                
                if ($flagFirstBloodSolver) {
                    // Get user info for the first solver
                    $flagFirstBloodUser = User::where('uuid', $flagFirstBloodSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                    
                    $flagFirstBlood = [
                        'user_name' => $flagFirstBloodUser ? $flagFirstBloodUser->user_name : 'Unknown',
                        'profile_image' => $flagFirstBloodUser && $flagFirstBloodUser->profile_image ? asset('storage/' . $flagFirstBloodUser->profile_image) : null,
                        'solved_at' => $flagFirstBloodSolver->solved_at
                    ];
                }
                
                $flagsData[] = [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'ar_name' => $flag->ar_name,
                    'description' => $flag->description,
                    'bytes' => $challenge->bytes,
                    'first_blood_bytes' => $challenge->firstBloodBytes,
                    'solved_count' => $flagSolvedCount,
                    'individual_first_blood' => $flagFirstBlood, // First blood for this individual flag
                    'flag_first_blood' => $flagFirstBlood
                ];
            }
            
            $challenge->flags_data = $flagsData;
            $challenge->flags_count = $challenge->flags->count();
        }
        // For multiple_individual type
        else if ($challenge->flag_type === 'multiple_individual' && $challenge->flags) {
            $flagsData = [];
            
            foreach ($challenge->flags as $flag) {
                // Get solved count for this flag - use flag submissions table for multiple_individual
                $flagSolvedCountQuery = \App\Models\EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                    ->where('solved', true);
                    
                if ($isFrozen) {
                    $flagSolvedCountQuery->where('solved_at', '<=', $freezeTime);
                }
                
                $flagSolvedCount = $flagSolvedCountQuery->count();
                
                // Get first blood for this flag - use flag submissions table for multiple_individual
                $flagFirstBlood = null;
                if ($flagSolvedCount > 0) {
                    // For multiple_individual, we need to query the flag submissions table
                    $flagFirstSolverQuery = \App\Models\EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                        ->where('solved', true)
                        ->orderBy('solved_at', 'asc');
                        
                    if ($isFrozen) {
                        $flagFirstSolverQuery->where('solved_at', '<=', $freezeTime);
                    }
                    
                    $flagFirstSolver = $flagFirstSolverQuery->first();
                    
                    if ($flagFirstSolver) {
                        $flagFirstBloodUser = User::where('uuid', $flagFirstSolver->user_uuid)->first(['uuid', 'user_name', 'profile_image']);
                        if ($flagFirstBloodUser) {
                            $flagFirstBlood = [
                                'user_name' => $flagFirstBloodUser->user_name,
                                'profile_image' => $flagFirstBloodUser->profile_image ? asset('storage/' . $flagFirstBloodUser->profile_image) : null,
                                'solved_at' => $flagFirstSolver->solved_at,
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

        // Add correct challenge-level solved count for multiple_all challenges
        if ($challenge->flag_type === 'multiple_all' && isset($teamsSolvedAll)) {
            // Update with the count of teams that solved ALL flags
            $solvedCount = $teamsSolvedAll;
            
            // Update first blood info with the first team to solve all flags
            if (isset($multipleAllFirstBlood)) {
                $challenge->first_blood = $multipleAllFirstBlood;
            }
        }
        
        // Convert to array and remove unwanted fields
        $challengeData = $challenge->toArray();
        $challengeData['solved_count'] = $solvedCount;
        $challengeData['description'] = $challenge->description;
        $challengeData['file'] = $challenge->file;
        $challengeData['link'] = $challenge->link;
        $challengeData['team_solved'] = $teamSolved;
        
        // Remove flags from response
        unset($challengeData['flags']);
        
        // Remove the actual flag value from the response for security
        unset($challengeData['flag']);
        
        // Also ensure no flag values are exposed from the flags collection
        if (isset($challengeData['flags_data'])) {
            foreach ($challengeData['flags_data'] as &$flagData) {
                if (isset($flagData['flag'])) {
                    unset($flagData['flag']);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'event_name' => $event->title,
            'frozen' => $isFrozen,
            'freeze_time' => $isFrozen ? $freezeTime->format('Y-m-d H:i:s') : null,
            'data' => $challengeData
        ]);
    }
    
    /**
     * Get flags for a specific event challenge
     * 
     * @param string $challengeUuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeFlags($challengeUuid)
    {
        $challenge = EventChallange::where('id', $challengeUuid)->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }
        
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid);
        if ($validationResponse) {
            return $validationResponse;
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
     * Get challenge flags and solved status for the authenticated user
     * 
     * @param string $challengeUuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChallengeStatusAndFlags($challengeUuid)
    {
        $challenge = EventChallange::where('id', $challengeUuid)->with('flags')->first();

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }
        
        $user = Auth::user();
        
        $result = [
            'flag_type' => $challenge->flag_type,
            'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
        ];
        
        // For single flag type
        if ($challenge->flag_type === 'single') {
            $solved = $challenge->solvedBy()->where('user_uuid', $user->uuid)->exists();
                
            $result['solved'] = $solved;
            
            // Single flag data
            $result['flag_data'] = [
                'bytes' => $challenge->bytes,
                'first_blood_bytes' => $challenge->firstBloodBytes,
                'solved_count' => $challenge->solvedBy()->count(),
            ];
            
            // Add first blood information if solved
            if ($solved) {
                $firstSolver = $challenge->solvedBy()->orderBy('event_challange_submission.created_at', 'asc')->first();
                    
                $result['is_first_blood'] = $firstSolver && $firstSolver->uuid === $user->uuid;
                
                $solvedAt = $challenge->solvedBy()->where('user_uuid', $user->uuid)->first()->pivot->solved_at;
                    
                $result['solved_at'] = $this->formatInUserTimezone($solvedAt);
            }
        } 
        // For multiple flag types
        else {
            $flagsData = [];
            
            foreach ($challenge->flags as $flag) {
                $isSolved = $flag->solvedBy()->where('user_uuid', $user->uuid)->exists();
                
                $flagData = [
                    'id' => $flag->id,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'bytes' => $flag->bytes,
                    'first_blood_bytes' => $flag->firstBloodBytes,
                    'solved' => $isSolved,
                    'solved_count' => $flag->solvedBy()->count(),
                ];
                
                // Add additional data if the flag is solved
                if ($isSolved) {
                    $flagSubmission = $flag->solvedBy()->where('user_uuid', $user->uuid)->first();
                        
                    $flagData['solved_at'] = $this->formatInUserTimezone($flagSubmission->pivot->solved_at);
                    
                    // Check if this user got first blood for this flag
                    $firstSolver = $flag->solvedBy()->orderBy('event_challange_flag_submission.solved_at', 'asc')->first();
                        
                    $flagData['is_first_blood'] = $firstSolver && $firstSolver->uuid === $user->uuid;
                }
                
                $flagsData[] = $flagData;
            }
            
            // Count how many flags are solved
            $solvedCount = count(array_filter($flagsData, function($flag) {
                return $flag['solved'];
            }));
            
            $result['flags'] = $flagsData;
            $result['flags_count'] = count($flagsData);
            $result['solved_flags_count'] = $solvedCount;
            $result['all_flags_solved'] = $solvedCount === count($flagsData) && $solvedCount > 0;
            
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
     * Get team-specific leaderboard for an event
     * 
     * @param string $eventUuid Event UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamLeaderboard($eventUuid)
    {
        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($eventUuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the current user's team
        $user = Auth::user();
        $team = EventTeam::where('event_uuid', $eventUuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['members'])
            ->first();
            
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team for this event'
            ], 404);
        }
        
        // Get the event to check if it's frozen
        $event = Event::where('uuid', $eventUuid)->first();
        $isFrozen = false;
        $freezeTime = null;
        
        if ($event && $event->freeze && $event->freeze_time) {
            $isFrozen = true;
            $freezeTime = $event->freeze_time;
        }
        
        // Get all challenges for this event
        $challenges = EventChallange::where('event_uuid', $eventUuid)
            ->with(['flags'])
            ->get();
            
        // Get members' data with their solved challenges and flags
        $teamMembers = User::whereIn('uuid', $team->members->pluck('user_uuid'))
            ->with(['solvedChallenges' => function($query) use ($eventUuid, $isFrozen, $freezeTime) {
                $query->where('event_uuid', $eventUuid);
                // Apply freeze time filter if frozen
                if ($isFrozen) {
                    $query->wherePivot('solved_at', '<=', $freezeTime);
                }
            }, 'solvedFlags' => function($query) use ($challenges, $isFrozen, $freezeTime) {
                $query->whereIn('id', $challenges->flatMap->flags->pluck('id'));
                // Apply freeze time filter if frozen
                if ($isFrozen) {
                    $query->wherePivot('solved_at', '<=', $freezeTime);
                }
            }, 'eventSubmissions' => function($query) use ($eventUuid, $isFrozen, $freezeTime) {
                $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                    $q->where('event_uuid', $eventUuid);
                })->where('solved', true);
                // Apply freeze time filter if frozen
                if ($isFrozen) {
                    $query->where('solved_at', '<=', $freezeTime);
                }
            }, 'flagSubmissions' => function($query) use ($challenges, $isFrozen, $freezeTime) {
                $query->whereIn('event_challange_flag_id', $challenges->flatMap->flags->pluck('id'))
                      ->where('solved', true);
                // Apply freeze time filter if frozen
                if ($isFrozen) {
                    $query->where('solved_at', '<=', $freezeTime);
                }
            }])
            ->get()
            ->map(function($member) use ($challenges, $isFrozen, $freezeTime) {
                $totalPoints = 0;
                $totalFirstBloodPoints = 0;
                $solvedChallenges = [];
                $earliestSolveTime = null;
                
                // Process single flag type challenges
                foreach ($challenges->where('flag_type', 'single') as $challenge) {
                    $submission = $member->solvedChallenges
                        ->where('id', $challenge->id)
                        ->first();
                        
                    if ($submission) {
                        $points = $challenge->bytes;
                        $firstBloodPoints = 0;
                        $solvedAt = $submission->pivot->solved_at;
                        
                        // Track earliest solve time
                        if (is_null($earliestSolveTime) || $solvedAt < $earliestSolveTime) {
                            $earliestSolveTime = $solvedAt;
                        }
                        
                        // Check if this was first blood
                        $firstSolverQuery = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                            ->where('solved', true)
                            ->orderBy('solved_at');
                            
                        // Apply freeze time filter if frozen
                        if ($isFrozen) {
                            $firstSolverQuery->where('solved_at', '<=', $freezeTime);
                        }
                        
                        $firstSolver = $firstSolverQuery->first();
                            
                        if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                            $firstBloodPoints = $challenge->firstBloodBytes;
                            $points += $firstBloodPoints;
                        }
                            
                        $totalPoints += $points;
                        $totalFirstBloodPoints += $firstBloodPoints;
                            
                        $solvedChallenges[] = [
                            'id' => $challenge->id,
                            'title' => $challenge->title,
                            'type' => 'single',
                            'points' => $points,
                            'first_blood_points' => $firstBloodPoints,
                            'is_first_blood' => $firstBloodPoints > 0,
                            'solved_at' => $this->formatInUserTimezone($solvedAt)
                        ];
                    }
                }
                
                // Process multiple flags challenges
                foreach ($challenges->whereIn('flag_type', ['multiple_all', 'multiple_individual']) as $challenge) {
                    // Get solved flags for this challenge
                    $solvedFlags = $member->solvedFlags
                        ->filter(function($flag) use ($challenge) {
                            return $challenge->flags->contains('id', $flag->id);
                        });
                        
                    if ($solvedFlags->isEmpty()) {
                        continue;
                    }
                    
                    // Track earliest solve time from flags
                    foreach ($solvedFlags as $flag) {
                        $flagSolvedAt = $flag->pivot->solved_at;
                        if (is_null($earliestSolveTime) || $flagSolvedAt < $earliestSolveTime) {
                            $earliestSolveTime = $flagSolvedAt;
                        }
                    }
                    
                    $challengePoints = 0;
                    $challengeFirstBloodPoints = 0;
                    $flagsData = [];
                    
                    // For multiple_all type
                    if ($challenge->flag_type === 'multiple_all') {
                        // Check if all flags are solved
                        $allFlagsSolved = $solvedFlags->count() === $challenge->flags->count();
                        
                        if ($allFlagsSolved) {
                            $points = $challenge->bytes;
                            
                            // Check if this was first blood for all flags
                            $isFirstBlood = true;
                            foreach ($challenge->flags as $flag) {
                                $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                    ->where('solved', true)
                                    ->orderBy('solved_at');
                                
                                // Apply freeze time filter if frozen
                                if ($isFrozen) {
                                    $firstSolverQuery->where('solved_at', '<=', $freezeTime);
                                }
                                
                                $firstSolver = $firstSolverQuery->first();
                                
                                if (!$firstSolver || $firstSolver->user_uuid !== $member->uuid) {
                                    $isFirstBlood = false;
                                    break;
                                }
                            }
                            
                            if ($isFirstBlood) {
                                $challengeFirstBloodPoints = $challenge->firstBloodBytes;
                                $points += $challengeFirstBloodPoints;
                            }
                            
                            $challengePoints += $points;
                            
                            foreach ($solvedFlags as $flag) {
                                $flagsData[] = [
                                    'id' => $flag->id,
                                    'name' => $flag->name,
                                    'solved_at' => $this->formatInUserTimezone($flag->pivot->solved_at)
                                ];
                            }
                            
                            $solvedChallenges[] = [
                                'id' => $challenge->id,
                                'title' => $challenge->title,
                                'type' => 'multiple_all',
                                'points' => $challengePoints,
                                'first_blood_points' => $challengeFirstBloodPoints,
                                'is_first_blood' => $isFirstBlood,
                                'flags' => $flagsData,
                                'solved_at' => $this->formatInUserTimezone(collect($solvedFlags->pluck('pivot.solved_at'))->max())
                            ];
                            
                            $totalPoints += $challengePoints;
                            $totalFirstBloodPoints += $challengeFirstBloodPoints;
                        }
                    }
                    // For multiple_individual type
                    else if ($challenge->flag_type === 'multiple_individual') {
                        $challengePoints = 0;
                        $challengeFirstBloodPoints = 0;
                        $flagsData = [];
                        
                        // Get all flags for this challenge
                        $allChallengeFlags = $challenge->flags;
                        
                        // Process each flag in the challenge
                        foreach ($allChallengeFlags as $flag) {
                            // Check if the member has solved this flag
                            $flagSubmission = $member->flagSubmissions
                                ->where('event_challange_flag_id', $flag->id)
                                ->where('solved', true)
                                ->first();
                                
                            if (!$flagSubmission) {
                                continue; // Skip if not solved
                            }
                            
                            // Check if this was first blood for the flag
                            $firstSolverQuery = EventChallangeFlagSubmission::where('event_challange_flag_id', $flag->id)
                                ->where('solved', true)
                                ->orderBy('solved_at');
                                
                            // Apply freeze time filter if frozen
                            if ($isFrozen) {
                                $firstSolverQuery->where('solved_at', '<=', $freezeTime);
                            }
                            
                            $firstSolver = $firstSolverQuery->first();
                            $isFirstBlood = $firstSolver && $firstSolver->user_uuid === $member->uuid;
                            
                            // Use non-zero defaults if bytes is null to ensure they show in the JSON
                            $bytes = $isFirstBlood ? 0 : ($flag->bytes ?: 100);
                            $firstBloodBytes = $isFirstBlood ? ($flag->firstBloodBytes ?: 100) : 0;
                            $flagPoints = $bytes + $firstBloodBytes;
                            
                            $challengePoints += $flagPoints;
                            $challengeFirstBloodPoints += $firstBloodBytes;
                            
                            $flagsData[] = [
                                'id' => $flag->id,
                                'name' => $flag->name,
                                'points' => $flagPoints,
                                'normal_bytes' => $bytes,
                                'first_blood_points' => $firstBloodBytes,
                                'is_first_blood' => $isFirstBlood,
                                'solved_at' => $this->formatInUserTimezone($flagSubmission->solved_at)
                            ];
                        }
                        
                        if (!empty($flagsData)) {
                            $solvedChallenges[] = [
                                'id' => $challenge->id,
                                'title' => $challenge->title,
                                'type' => 'multiple_individual',
                                'points' => $challengePoints,
                                'first_blood_points' => $challengeFirstBloodPoints,
                                'is_first_blood' => $challengeFirstBloodPoints > 0,
                                'flags' => $flagsData,
                                'solved_at' => $this->formatInUserTimezone(collect($member->flagSubmissions->where('event_challange_flag_id', $allChallengeFlags->first()->id)->first()->solved_at ?? now())->min())
                            ];
                            
                            $totalPoints += $challengePoints;
                            $totalFirstBloodPoints += $challengeFirstBloodPoints;
                        }
                    }
                }
                
                return [
                    'user_uuid' => $member->uuid,
                    'user_name' => $member->user_name,
                    'profile_image' => $member->profile_image ? asset('storage/' . $member->profile_image) : null,
                    'total_points' => $totalPoints,
                    'first_blood_points' => $totalFirstBloodPoints,
                    'earliest_solve_time' => $earliestSolveTime,
                    'challenges' => collect($solvedChallenges)->sortByDesc('solved_at')->values()->all(),
                    'solved_challenges_count' => count($solvedChallenges)
                ];
            });
            
        // Sort by total points first, then by earliest solve time when points are equal
        $teamMembers = $teamMembers->sort(function ($a, $b) {
            // First sort by total points (descending)
            if ($a['total_points'] != $b['total_points']) {
                return $b['total_points'] - $a['total_points'];
            }
            
            // If points are equal, sort by earliest solve time (ascending)
            if ($a['earliest_solve_time'] && $b['earliest_solve_time']) {
                return strtotime($a['earliest_solve_time']) - strtotime($b['earliest_solve_time']);
            }
            
            // If one has solve time and other doesn't, the one with solve time comes first
            if ($a['earliest_solve_time'] && !$b['earliest_solve_time']) {
                return -1;
            }
            
            if (!$a['earliest_solve_time'] && $b['earliest_solve_time']) {
                return 1;
            }
            
            return 0;
        })->values();
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'team' => [
                    'name' => $team->name,
                    'icon' => $team->icon ? url('storage/team-icons/' . $team->icon) : null,
                    'member_count' => $team->members->count()
                ],
                'members' => $teamMembers,
                'total_team_points' => $teamMembers->sum('total_points'),
                'total_challenges' => $challenges->count(),
                'frozen' => $isFrozen,
                'freeze_time' => $freezeTime ? $this->formatInUserTimezone($freezeTime) : null,
                'last_updated' => now()->format('c')
            ]
        ]);
    }
    
    /**
     * Get leaderboard for a specific challenge showing all users who solved it
     * 
     * @param string $challengeUuid Challenge UUID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamChallengeLeaderboard($challengeUuid)
    {
        $challenge = EventChallange::with(['flags'])->find($challengeUuid);

        if (!$challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found'
            ], 404);
        }

        // Validate event and team requirements
        $validationResponse = $this->validateEventAndTeamRequirements($challenge->event_uuid, true);
        if ($validationResponse) {
            return $validationResponse;
        }

        // Get the event to check if it's frozen
        $event = Event::where('uuid', $challenge->event_uuid)->first();
        $isFrozen = false;
        $freezeTime = null;

        if ($event && $event->freeze && $event->freeze_time) {
            $isFrozen = true;
            $freezeTime = $event->freeze_time;
        }

        // Get the current user's team (for reference)
        $user = Auth::user();
        $team = EventTeam::where('event_uuid', $challenge->event_uuid)
            ->whereHas('members', function($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['members'])
            ->first();
            
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of any team for this event'
            ], 404);
        }
        
        // Collect results
        $results = collect();

        // SINGLE FLAG TYPE
        if ($challenge->flag_type === 'single') {
            // Get all submissions for this challenge (not filtered by team)
            $submissionsQuery = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                ->where('solved', true);
                
            // If frozen, only get submissions before freeze time
            if ($isFrozen) {
                $submissionsQuery->where('solved_at', '<=', $freezeTime);
            }
            
            $submissions = $submissionsQuery->with('user:uuid,user_name,profile_image')
                ->get();

            // Find first solver (respecting freeze time)
            $firstBloodQuery = EventChallangeSubmission::where('event_challange_id', $challenge->id)
                ->where('solved', true)
                ->orderBy('solved_at', 'asc');
                
            if ($isFrozen) {
                $firstBloodQuery->where('solved_at', '<=', $freezeTime);
            }
            
            $firstBloodSubmission = $firstBloodQuery->first();
            
            // Process each submission
            foreach ($submissions as $submission) {
                if (!$submission->user) continue;
                
                // Check if first blood
                $isFirstBlood = $firstBloodSubmission && $firstBloodSubmission->user_uuid === $submission->user_uuid;
                
                // Calculate points
                $points = $challenge->bytes;
                $firstBloodPoints = $isFirstBlood ? $challenge->firstBloodBytes : 0;
                
                // Get team information for this user
                $userTeam = EventTeam::where('event_uuid', $challenge->event_uuid)
                    ->whereHas('members', function($query) use ($submission) {
                        $query->where('user_uuid', $submission->user_uuid);
                    })
                    ->first();
                
                // Add to results
                $results->push([
                    'user_uuid' => $submission->user->uuid,
                    'user_name' => $submission->user->user_name,
                    'profile_image' => $submission->user->profile_image ? asset('storage/' . $submission->user->profile_image) : null,
                    'team_name' => $userTeam ? $userTeam->name : null,
                    'team_icon' => $userTeam ? $userTeam->icon_url : null,
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood,
                    'is_teammate' => $team && $userTeam && $team->id === $userTeam->id,
                    'solved_at' => $this->formatInUserTimezone($submission->solved_at),
                    'solved_flags' => [],
                    'flags_count' => 0,
                    'all_flags_solved' => true,
                    'solved_after_freeze' => false
                ]);
            }
        }
        // MULTIPLE FLAGS TYPE
        else if ($challenge->flags->count() > 0) {
            // Get flag IDs for this challenge
            $flagIds = $challenge->flags->pluck('id')->toArray();
            
            // Get all submissions for these flags (not filtered by team)
            $flagSubmissionsQuery = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $flagIds)
                ->where('solved', true);
                
            // If frozen, only get submissions before freeze time
            if ($isFrozen) {
                $flagSubmissionsQuery->where('solved_at', '<=', $freezeTime);
            }
            
            $flagSubmissions = $flagSubmissionsQuery->with('user:uuid,user_name,profile_image')
                ->get();
                
            if ($flagSubmissions->isEmpty()) {
                // Return empty result
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'team' => [
                            'name' => $team->name,
                            'icon' => $team->icon_url,
                            'member_count' => $team->members->count()
                        ],
                        'challenge' => [
                            'id' => $challenge->id,
                            'title' => $challenge->title,
                            'flag_type' => $challenge->flag_type,
                            'flag_type_description' => $this->getFlagTypeDescription($challenge->flag_type),
                            'bytes' => $challenge->bytes,
                            'first_blood_bytes' => $challenge->firstBloodBytes,
                            'total_flags' => $challenge->flags->count()
                        ],
                        'members' => [],
                        'total_solvers' => 0,
                        'frozen' => $isFrozen,
                        'freeze_time' => $freezeTime ? $this->formatInUserTimezone($freezeTime) : null,
                        'last_updated' => $this->formatInUserTimezone(now())
                    ]
                ]);
            }
            
            // Prepare first blood information for each flag
            $firstBloodByFlagId = [];
            foreach ($flagIds as $flagId) {
                $query = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagId)
                    ->where('solved', true)
                    ->orderBy('solved_at', 'asc');
                    
                if ($isFrozen) {
                    $query->where('solved_at', '<=', $freezeTime);
                }
                
                $firstBloodByFlagId[$flagId] = $query->first();
            }
            
            // Group submissions by user
            $submissionsByUser = $flagSubmissions->groupBy('user_uuid');
            
            // Process each user's submissions
            foreach ($submissionsByUser as $userUuid => $userSubmissions) {
                $user = $userSubmissions->first()->user;
                if (!$user) continue;
                
                $points = 0;
                $firstBloodPoints = 0;
                $isFirstBlood = false;
                $solvedFlags = [];
                
                // Find earliest submission time for sorting
                $earliestSolvedAt = $userSubmissions->min('solved_at');
                
                // Get team information for this user
                $userTeam = EventTeam::where('event_uuid', $challenge->event_uuid)
                    ->whereHas('members', function($query) use ($userUuid) {
                        $query->where('user_uuid', $userUuid);
                    })
                    ->first();
                
                // Handle Multiple ALL flag type
                if ($challenge->flag_type === 'multiple_all') {
                    // Get solved flag IDs
                    $solvedFlagIds = $userSubmissions->pluck('event_challange_flag_id')->unique()->values()->toArray();
                    $allFlagsSolved = count($solvedFlagIds) === count($flagIds);
                    
                    // For multiple_all, we should only show team members who have solved ALL flags
                    // If not all flags are solved, skip this user entirely
                    if (!$allFlagsSolved) {
                        continue;
                    }
                    
                    // Award points for solving all flags
                    $points = $challenge->bytes;
                    
                    // Check for first blood
                    $allFirstBlood = true;
                    foreach ($flagIds as $flagId) {
                        $firstSolver = $firstBloodByFlagId[$flagId] ?? null;
                        if (!$firstSolver || $firstSolver->user_uuid !== $userUuid) {
                            $allFirstBlood = false;
                            break;
                        }
                    }
                    
                    if ($allFirstBlood) {
                        $firstBloodPoints = $challenge->firstBloodBytes;
                        $isFirstBlood = true;
                    }
                    
                    // Find the user who was first to solve ALL flags - this is the true first blood for multiple_all
                    $firstTeamToSolveAll = null;
                    $teamsWithAllFlags = EventTeam::where('event_uuid', $challenge->event_uuid)
                        ->whereHas('members', function($query) use ($flagIds) {
                            $query->whereHas('flagSubmissions', function($fquery) use ($flagIds) {
                                $fquery->whereIn('event_challange_flag_id', $flagIds)
                                    ->where('solved', true);
                            });
                        })
                        ->get();
                    
                    foreach ($teamsWithAllFlags as $completingTeam) {
                        // Skip teams that haven't solved all flags
                        $teamMembers = $completingTeam->members()->pluck('uuid')->toArray();
                        $teamSolvedFlags = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $flagIds)
                            ->whereIn('user_uuid', $teamMembers)
                            ->where('solved', true)
                            ->pluck('event_challange_flag_id')
                            ->unique()
                            ->toArray();
                            
                        if (count($teamSolvedFlags) != count($flagIds)) {
                            continue;
                        }
                        
                        // Get the latest solved_at time for this team (when they completed all flags)
                        $latestSolvedAt = EventChallangeFlagSubmission::whereIn('event_challange_flag_id', $flagIds)
                            ->whereIn('user_uuid', $teamMembers)
                            ->where('solved', true)
                            ->max('solved_at');
                            
                        if (!$firstTeamToSolveAll || $latestSolvedAt < $firstTeamToSolveAll->solved_at) {
                            $firstTeamToSolveAll = (object)[
                                'team_id' => $completingTeam->id,
                                'solved_at' => $latestSolvedAt
                            ];
                        }
                    }
                    
                    // For multiple_all challenges, we don't need to show individual flags
                    // in the solved_flags array. Just indicate completion as a single challenge.
                    // Leave solvedFlags as an empty array, and the flags_count will be 0
                    // This matches how we handle multiple_all challenges in EventTeamController.php
                }
                // Handle Multiple INDIVIDUAL flag type
                else if ($challenge->flag_type === 'multiple_individual') {
                    // For multiple_individual, create a separate entry for each flag submission
                    // Reset the accumulated values as we'll add separate entries
                    $points = 0;
                    $firstBloodPoints = 0;
                    $solvedFlags = [];
                    $isFirstBlood = false;
                    
                    // Add each flag as a separate entry directly to the results collection
                    foreach ($userSubmissions as $submission) {
                        $flag = $challenge->flags->firstWhere('id', $submission->event_challange_flag_id);
                        if (!$flag) continue;
                        
                        // Initialize flag data
                        $flagPoints = $flag->bytes ?: 100; // Default to 100 if null
                        $flagFirstBlood = false;
                        $flagFirstBloodPoints = 0;
                        
                        // Check for first blood
                        $firstSolver = $firstBloodByFlagId[$submission->event_challange_flag_id] ?? null;
                        if ($firstSolver && $firstSolver->user_uuid === $userUuid) {
                            $flagFirstBloodPoints = $flag->firstBloodBytes ?: 100; // Default to 100 if null
                            $flagFirstBlood = true;
                        }
                        
                        // Add directly to results collection
                        $results->push([
                            'user_uuid' => $user->uuid,
                            'user_name' => '********', // Hide username for privacy
                            'profile_image' => null, // Hide profile image for privacy
                            'team_name' => $userTeam ? $userTeam->name : null,
                            'team_icon' => $userTeam ? $userTeam->icon_url : null,
                            'points' => $flagPoints,
                            'first_blood_points' => $flagFirstBloodPoints,
                            'is_first_blood' => $flagFirstBlood,
                            'is_teammate' => $team && $userTeam && $team->id === $userTeam->id,
                            'solved_at' => $this->formatInUserTimezone($submission->solved_at),
                            'solved_flags' => [
                                [
                                    'id' => $flag->id,
                                    'name' => $flag->name,
                                    'points' => $flagPoints,
                                    'is_first_blood' => $flagFirstBlood,
                                    'solved_at' => $this->formatInUserTimezone($submission->solved_at),
                                    'solved_after_freeze' => false
                                ]
                            ],
                            'flags_count' => 1, // Always 1 as we're creating separate entries
                            'all_flags_solved' => true, // Always true for individual flags
                            'solved_after_freeze' => false
                        ]);
                    }
                    
                    // Skip adding the combined entry since we've added individual entries
                    continue;
                }
                
                // Get flag IDs solved
                $solvedFlagIds = $userSubmissions->pluck('event_challange_flag_id')->unique()->values()->toArray();
                
                // For multiple_all, first blood should only be awarded to the first team that solves ALL flags
                if ($challenge->flag_type === 'multiple_all') {
                    // Update the top-level is_first_blood flag to match our flag-level determination
                    $isFirstBlood = ($firstTeamToSolveAll && $userTeam && $userTeam->id === $firstTeamToSolveAll->team_id);
                    
                    // Only the first team to solve all flags gets first blood
                    if ($isFirstBlood) {
                        $firstBloodPoints = $challenge->firstBloodBytes ?: 0;
                    } else {
                        $firstBloodPoints = 0;
                    }
                    
                    // Log for debugging
                    Log::info('Multiple_all first blood check', [
                        'user_uuid' => $userUuid,
                        'team_id' => $userTeam ? $userTeam->id : null,
                        'first_team_to_solve_all' => $firstTeamToSolveAll ? $firstTeamToSolveAll->team_id : null,
                        'all_flags_solved' => count($flagIds) === count($solvedFlagIds),
                        'is_first_blood' => $isFirstBlood
                    ]);
                }
                
                // Add to results - hide username for privacy
                // Create the result entry based on challenge type
                $resultEntry = [
                    'user_uuid' => $user->uuid,
                    'user_name' => '********', // Hide username for privacy
                    'profile_image' => null, // Hide profile image for privacy
                    'team_name' => $userTeam ? $userTeam->name : null,
                    'team_icon' => $userTeam ? $userTeam->icon_url : null,
                    'points' => $points,
                    'first_blood_points' => $firstBloodPoints,
                    'is_first_blood' => $isFirstBlood,
                    'is_teammate' => $team && $userTeam && $team->id === $userTeam->id,
                    'solved_at' => $this->formatInUserTimezone($earliestSolvedAt),
                    'solved_after_freeze' => false
                ];
                
                // For multiple_all challenges, don't include solved_flags or flags_count at all
                if ($challenge->flag_type !== 'multiple_all') {
                    $resultEntry['solved_flags'] = $solvedFlags;
                    $resultEntry['flags_count'] = count($solvedFlags);
                }
                
                // Include all_flags_solved field for all challenge types
                $resultEntry['all_flags_solved'] = $challenge->flag_type === 'multiple_individual' ? 
                                          true : // For multiple_individual, each flag is its own challenge, so this is always true
                                          ($challenge->flag_type !== 'multiple_all' || (count($flagIds) === count($solvedFlagIds)));
                
                // Add the entry to results
                $results->push($resultEntry);
            }
        }
        
        // Return full response with all data - change the sorting from sortByDesc to sortBy
        return response()->json([
            'status' => 'success',
            'data' => [
                
                'members' => $results->sortBy(function($item) {
                    // Sort by first blood first (first blood teams first), then by solved_at time
                    return [$item['is_first_blood'] ? 0 : 1, $item['solved_at']];
                })->values()->all(),
                'total_solvers' => $results->count(),
                'frozen' => $isFrozen,
                'freeze_time' => $freezeTime ? $this->formatInUserTimezone($freezeTime) : null,
                'last_updated' => $this->formatInUserTimezone(now())
            ]
        ]);
    }
    
    /**
     * Get flag type description 
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
}
