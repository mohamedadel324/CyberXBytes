<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTeam;
use App\Models\EventTeamJoinSecret;
use App\Models\User;
use App\Traits\HandlesTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EventTeamController extends Controller
{
    use HandlesTimezones;

    public function create(Request $request, $eventUuid)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:event_teams,name',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $event = Event::where('uuid', $eventUuid)->first();
        
        if (!$event) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event not found'
            ], 404);
        }

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($event->team_formation_start_date);
        $endTime = strtotime($event->team_formation_end_date);
        
        // Hard debug output - always log every date check
        \Illuminate\Support\Facades\Log::critical('TEAM TIME CHECK', [
            'event_uuid' => $eventUuid,
            'current_time' => date('Y-m-d H:i:s', $currentTime),
            'current_timestamp' => $currentTime,
            'formation_start' => date('Y-m-d H:i:s', $startTime),
            'formation_start_timestamp' => $startTime,
            'formation_end' => date('Y-m-d H:i:s', $endTime),
            'formation_end_timestamp' => $endTime,
            'time_diff' => $startTime - $currentTime,
            'raw_start_date' => $event->team_formation_start_date,
            'raw_end_date' => $event->team_formation_end_date,
            'allowed' => ($currentTime >= $startTime && $currentTime <= $endTime) ? 'YES' : 'NO'
        ]);
        
        // Only proceed if current time is between start and end times
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }

        // Check if user is already in a team for this event
        $existingTeam = EventTeam::where('event_uuid', $event->uuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if ($existingTeam) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already in a team for this event'
            ], 400);
        }

        // Create team
        $team = EventTeam::create([
            'event_uuid' => $event->uuid,
            'name' => $request->name,
            'description' => $request->description,
            'leader_uuid' => Auth::user()->uuid,
            'is_locked' => false
        ]);

        // Add leader as member
        $team->members()->attach(Auth::user()->uuid, ['role' => 'leader']);

        return response()->json([
            'status' => 'success',
            'message' => 'Team created successfully',
            'data' => $team
        ]);
    }

    public function join($teamUuid)
    {
        $team = EventTeam::with('event')->where('id', $teamUuid)->first();
        
        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found'
            ], 404);
        }

        if ($team->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This team is locked'
            ], 400);
        }

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($team->event->team_formation_start_date);
        $endTime = strtotime($team->event->team_formation_end_date);
        
        // Hard debug output - always log every date check
        \Illuminate\Support\Facades\Log::critical('TEAM JOIN TIME CHECK', [
            'team_uuid' => $teamUuid,
            'team_name' => $team->name,
            'current_time' => date('Y-m-d H:i:s', $currentTime),
            'current_timestamp' => $currentTime,
            'formation_start' => date('Y-m-d H:i:s', $startTime),
            'formation_start_timestamp' => $startTime,
            'formation_end' => date('Y-m-d H:i:s', $endTime),
            'formation_end_timestamp' => $endTime,
            'time_diff' => $startTime - $currentTime,
            'raw_start_date' => $team->event->team_formation_start_date,
            'raw_end_date' => $team->event->team_formation_end_date,
            'allowed' => ($currentTime >= $startTime && $currentTime <= $endTime) ? 'YES' : 'NO'
        ]);
        
        // Only proceed if current time is between start and end times
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }

        // Check if user is already in a team
        $existingTeam = EventTeam::where('event_uuid', $team->event_uuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if ($existingTeam) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already in a team for this event'
            ], 400);
        }

        // Check team size
        if ($team->members()->count() >= $team->event->team_maximum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team is full'
            ], 400);
        }

        // Add member to team
        $team->members()->attach(Auth::user()->uuid, ['role' => 'member']);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully joined the team'
        ]);
    }

    public function leave($teamUuid)
    {
        $team = EventTeam::where('uuid', $teamUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not a member of this team'
            ], 404);
        }

        if ($team->leader_uuid === Auth::user()->uuid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team leader cannot leave the team'
            ], 400);
        }

        $team->members()->detach(Auth::user()->uuid);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully left the team'
        ]);
    }
    public function checkIfInTeam($eventUuid) {
        $team = EventTeam::with(['members', 'event'])
            ->where('event_uuid', $eventUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not in a team for this event'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'You are in a team for this event'
        ]);
    }
    public function myTeam($eventUuid)
    {
        $team = EventTeam::with(['members', 'event'])
            ->where('event_uuid', $eventUuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not in a team for this event'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'uuid' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'is_locked' => $team->is_locked,
                'leader' => [
                    'uuid' => $team->leader->uuid,
                    'user_name' => $team->leader->user_name,
                    'profile_image' => $team->leader->profile_image ? url('storage/' . $team->leader->profile_image) : null
                ],
                'members' => $team->members->map(function ($member) {
                    return [
                        'uuid' => $member->uuid,
                        'username' => $member->user_name,
                        'profile_image' => $member->profile_image ? url('storage/' . $member->profile_image) : null,
                        'role' => $member->pivot->role
                    ];
                })
            ]
        ]);
    }

    public function listTeams($eventUuid)
    {
        $teams = EventTeam::with(['members', 'leader'])
            ->where('event_uuid', $eventUuid)
            ->get()
            ->map(function ($team) {
                return [
                    'uuid' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'is_locked' => $team->is_locked,
                    'leader' => $team->leader->only(['uuid', 'user_name']),
                    'member_count' => $team->members->count(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $teams
        ]);
    }

    public function lock($teamUuid)
    {
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        if ($team->members()->count() < $team->event->team_minimum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team does not have minimum required members'
            ], 400);
        }

        $team->update(['is_locked' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Team has been locked'
        ]);
    }

    public function unlock($teamUuid)
    {
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        $team->update(['is_locked' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Team has been unlocked'
        ]);
    }

    public function removeMember(Request $request, $teamUuid)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Find the team and verify the current user is the leader
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        // Find the user to remove
        $userToRemove = User::where('user_name', $request->username)->first();

        if (!$userToRemove) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Check if the user is actually in the team
        $isMember = $team->members()
            ->where('user_uuid', $userToRemove->uuid)
            ->exists();

        if (!$isMember) {
            return response()->json([
                'status' => 'error',
                'message' => 'This user is not a member of your team'
            ], 400);
        }

        // Don't allow removing the leader
        if ($userToRemove->uuid === $team->leader_uuid) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot remove the team leader'
            ], 400);
        }

        // Remove the member
        $team->members()->detach($userToRemove->uuid);

        return response()->json([
            'status' => 'success',
            'message' => 'Member removed successfully',
            'data' => [
                'removed_member' => [
                    'username' => $userToRemove->user_name,
                    'name' => $userToRemove->name
                ],
                'remaining_members' => $team->members->map(function ($member) {
                    return [
                        'username' => $member->user_name,
                        'name' => $member->name,
                        'role' => $member->pivot->role
                    ];
                })
            ]
        ]);
    }

    public function generateJoinSecret($teamUuid)
    {
        // Find the team and verify the current user is the leader
        $team = EventTeam::with('members')->where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        // Check if team is full
        if ($team->members->count() >= $team->event->team_maximum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team is already at maximum capacity'
            ], 400);
        }

        // Generate a unique 16-character secret
        do {
            $secret = Str::random(16);
        } while (EventTeamJoinSecret::where('secret', $secret)->exists());

        // Create the join secret
        $joinSecret = EventTeamJoinSecret::create([
            'team_uuid' => $team->id,
            'secret' => $secret
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Join secret generated successfully',
            'data' => [
                'secret' => $secret,
                'remaining_slots' => $team->event->team_maximum_members - $team->members->count()
            ]
        ]);
    }

    public function joinWithSecret(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'secret' => 'required|string|size:16',
            'team_name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Find the unused secret and verify team name
        $joinSecret = EventTeamJoinSecret::with('team.event')
            ->where('secret', $request->secret)
            ->where('used', false)
            ->whereHas('team', function($query) use ($request) {
                $query->where('name', $request->team_name);
            })
            ->first();

        if (!$joinSecret) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid secret, team name, or secret already used'
            ], 400);
        }

        $team = $joinSecret->team;

        // DIRECT SERVER-SIDE TIME CHECK
        $currentTime = time(); // Current server Unix timestamp
        $startTime = strtotime($team->event->team_formation_start_date);
        $endTime = strtotime($team->event->team_formation_end_date);
        
        // Hard debug output - always log every date check
        \Illuminate\Support\Facades\Log::critical('JOIN WITH SECRET TIME CHECK', [
            'team_uuid' => $team->id,
            'team_name' => $team->name,
            'user_uuid' => Auth::user()->uuid,
            'current_time' => date('Y-m-d H:i:s', $currentTime),
            'current_timestamp' => $currentTime,
            'formation_start' => date('Y-m-d H:i:s', $startTime),
            'formation_start_timestamp' => $startTime,
            'formation_end' => date('Y-m-d H:i:s', $endTime),
            'formation_end_timestamp' => $endTime,
            'time_diff' => $startTime - $currentTime,
            'raw_start_date' => $team->event->team_formation_start_date,
            'raw_end_date' => $team->event->team_formation_end_date,
            'allowed' => ($currentTime >= $startTime && $currentTime <= $endTime) ? 'YES' : 'NO'
        ]);
        
        // Only proceed if current time is between start and end times
        if ($currentTime < $startTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation has not started yet',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'seconds_remaining' => $startTime - $currentTime
                ]
            ], 400);
        }

        if ($currentTime > $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team formation period has ended',
                'debug_info' => [
                    'current_time' => date('Y-m-d H:i:s', $currentTime),
                    'end_time' => date('Y-m-d H:i:s', $endTime)
                ]
            ], 400);
        }

        if ($team->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This team is locked'
            ], 400);
        }

        // Check if user is already in a team
        $existingTeam = EventTeam::where('event_uuid', $team->event_uuid)
            ->whereHas('members', function ($query) {
                $query->where('user_uuid', Auth::user()->uuid);
            })
            ->first();

        if ($existingTeam) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already in a team for this event'
            ], 400);
        }

        // Check team size
        if ($team->members()->count() >= $team->event->team_maximum_members) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team is full'
            ], 400);
        }

        // Add member to team
        $team->members()->attach(Auth::user()->uuid, ['role' => 'member']);

        // Mark secret as used
        $joinSecret->update([
            'used' => true,
            'used_by_uuid' => Auth::user()->uuid,
            'used_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully joined the team',
            'data' => [
                'team' => [
                    'name' => $team->name,
                    'description' => $team->description,
                    'member_count' => $team->members()->count()
                ]
            ]
        ]);
    }

    public function listJoinSecrets($teamUuid)
    {
        // Find the team and verify the current user is the leader
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        $secrets = EventTeamJoinSecret::where('team_uuid', $team->id)
            ->with('usedBy:uuid,user_name,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($secret) {
                return [
                    'secret' => $secret->secret,
                    'created_at' => $secret->created_at->format('c'),
                    'status' => $secret->used ? 'used' : 'available',
                    'used_by' => $secret->used ? [
                        'username' => $secret->usedBy->user_name,
                        'name' => $secret->usedBy->name
                    ] : null,
                    'used_at' => $secret->used_at ? $secret->used_at->format('c') : null
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $secrets
        ]);
    }
    /**
     * @requestMediaType multipart/form-data
     */
    public function updateTeam(Request $request, $teamUuid)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'icon' => 'nullable|image|max:2048' // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Find the team and verify the current user is the leader
        $team = EventTeam::where('id', $teamUuid)
            ->where('leader_uuid', Auth::user()->uuid)
            ->first();

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found or you are not the leader'
            ], 404);
        }

        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            if ($team->icon) {
                Storage::delete($team->icon);
            }

            // Store new icon
            $path = $request->file('icon')->store('team-icons', 'public');
            $team->icon = $path;
        }

        if ($request->has('name')) {
            $team->name = $request->name;
        }

        if ($request->has('description')) {
            $team->description = $request->description;
        }

        $team->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Team updated successfully',
            'data' => [
                'name' => $team->name,
                'description' => $team->description,
                'icon_url' => $team->icon_url
            ]
        ]);
    }
}
