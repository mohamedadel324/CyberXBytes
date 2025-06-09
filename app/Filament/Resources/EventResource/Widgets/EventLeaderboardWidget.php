<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\EventTeam;
use App\Models\EventChallangeFlagSubmission;
use App\Models\EventChallangeSubmission;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class EventLeaderboardWidget extends BaseWidget
{
    protected static ?string $heading = 'Event Leaderboard';
    
    public $record = null;
    
    protected int | string | array $columnSpan = 'full';
    
    // Cache for team points to avoid recalculating
    protected Collection $teamPointsCache;
    
    // Add a new property to store solve times
    protected Collection $teamSolveTimesCache;
    
    // Track the current sort direction
    protected string $sortDirection = 'desc';
    
    public function table(Table $table): Table
    {
        // Initialize the caches
        $this->teamPointsCache = collect();
        $this->teamSolveTimesCache = collect();
        
        return $table
            ->query(function () {
                if (!$this->record) {
                    return EventTeam::query()->whereNull('id');
                }
                
                $eventUuid = $this->record->uuid;
                $event = Event::where('uuid', $eventUuid)->first();   
                
                // Get all teams for this event with their members
                $query = EventTeam::where('event_uuid', $eventUuid)
                    ->with(['members.eventSubmissions' => function($query) use ($eventUuid) {
                        $query->whereHas('eventChallange', function($q) use ($eventUuid) {
                            $q->where('event_uuid', $eventUuid);
                        })->where('solved', true);
                    }, 'members.flagSubmissions' => function($query) use ($eventUuid) {
                        $query->whereHas('eventChallangeFlag.eventChallange', function($q) use ($eventUuid) {
                            $q->where('event_uuid', $eventUuid);
                        })->where('solved', true);
                    }]);
                
                return $query;
            })
            ->modifyQueryUsing(function (Builder $query) {
                // Load all teams
                $teams = $query->get();
                
                // Calculate points and solve times for all teams using the EventTeamController API
                foreach ($teams as $team) {
                    // Use the working getTeamById method from EventTeamController to get accurate points
                    $teamController = new \App\Http\Controllers\Api\EventTeamController();
                    $teamResponse = $teamController->getTeamByIdForAdmin($team->id);
                    $teamData = json_decode($teamResponse->getContent(), true);
                    
                    // If we got valid data back from the API
                    if (isset($teamData['status']) && $teamData['status'] === 'success' && isset($teamData['data'])) {
                        // Extract statistics data from the response
                        $stats = isset($teamData['data']['statistics']) ? $teamData['data']['statistics'] : [];
                        
                        // Get total bytes as points
                        $totalPoints = isset($stats['total_bytes']) ? $stats['total_bytes'] : 0;
                        $this->teamPointsCache[$team->id] = $totalPoints;
                        
                        // Calculate solve time for the team
                        $this->teamSolveTimesCache[$team->id] = $this->calculateTeamAverageSolveTime($teamData['data']);
                    } else {
                        // Fallback to the old calculation method if API fails
                        $this->teamPointsCache[$team->id] = $this->calculateTeamPoints($team);
                        $this->teamSolveTimesCache[$team->id] = PHP_INT_MAX; // Default to maximum time if we can't calculate
                    }
                }
                
                // Group teams by points for secondary sorting
                $teamsByPoints = $this->teamPointsCache
                    ->map(function ($points, $teamId) {
                        return [
                            'id' => $teamId,
                            'points' => $points,
                            'solve_time' => $this->teamSolveTimesCache[$teamId] ?? PHP_INT_MAX
                        ];
                    })
                    ->groupBy('points')
                    ->sortKeysDesc();
                
                // Create a new sorted array of team IDs
                $sortedTeamIds = [];
                
                // For each group of teams with the same points
                foreach ($teamsByPoints as $points => $teamsGroup) {
                    // Sort teams with the same points by their solve time (ascending)
                    // Lower solve time means they completed challenges earlier
                    $samePointsTeams = $teamsGroup->sortBy('solve_time')->pluck('id')->toArray();
                    
                    // Add the sorted teams to our result array
                    foreach ($samePointsTeams as $teamId) {
                        $sortedTeamIds[] = $teamId;
                    }
                }
                
                // Use manual sorting instead of FIELD() since we're working with UUIDs
                if (!empty($sortedTeamIds)) {
                    // We'll use a subquery to add a sorting column
                    $ids = array_map(function ($id) {
                        return "'" . $id . "'";
                    }, $sortedTeamIds);
                    
                    if (count($ids) > 0) {
                        // Create a case statement for ordering
                        $cases = [];
                        foreach ($sortedTeamIds as $position => $id) {
                            $cases[] = "WHEN id = '$id' THEN $position";
                        }
                        $orderByCase = "CASE " . implode(' ', $cases) . " ELSE " . count($sortedTeamIds) . " END";
                        
                        $query->orderByRaw($orderByCase);
                    }
                }
                
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('Rank')
                    ->state(function ($record, $rowLoop) {
                        return $rowLoop->iteration;
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->label('Team')
                    ->searchable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Points')
                    ->getStateUsing(function ($record) {
                        return $this->teamPointsCache[$record->id] ?? 0;
                    }),
                Tables\Columns\TextColumn::make('solved')
                    ->label('Challenges Solved')
                    ->getStateUsing(function ($record) {
                        // Use the cached team API data if available
                        // This value comes from getTeamById which handles both single and multiple flag challenges correctly
                        $teamController = new \App\Http\Controllers\Api\EventTeamController();
                        $teamResponse = $teamController->getTeamById($record->id);
                        $teamData = json_decode($teamResponse->getContent(), true);
                        
                        if (isset($teamData['status']) && $teamData['status'] === 'success' && 
                            isset($teamData['data']) && isset($teamData['data']['statistics'])) {
                            return $teamData['data']['statistics']['total_challenges_solved'] ?? 0;
                        }
                        
                        // Fallback to the old calculation method if API fails
                        $solvedChallenges = collect();
                        
                        foreach ($record->members as $member) {
                            // Add regular challenge submissions
                            foreach ($member->eventSubmissions as $submission) {
                                $solvedChallenges->push($submission->event_challange_id);
                            }
                        }
                        
                        return $solvedChallenges->unique()->count();
                    }),
                Tables\Columns\TextColumn::make('first_blood')
                    ->label('First Bloods')
                    ->getStateUsing(function ($record) {
                        // Use the cached team API data for first blood count
                        $teamController = new \App\Http\Controllers\Api\EventTeamController();
                        $teamResponse = $teamController->getTeamById($record->id);
                        $teamData = json_decode($teamResponse->getContent(), true);
                        
                        if (isset($teamData['status']) && $teamData['status'] === 'success' && 
                            isset($teamData['data']) && isset($teamData['data']['statistics'])) {
                            return $teamData['data']['statistics']['total_first_blood_count'] ?? 0;
                        }
                        
                        // If API fails, return 0 as we don't have a good way to calculate this manually
                        return 0;
                    }),
                
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Members')
                    ->getStateUsing(fn ($record) => $record->members->count()),
            ]);
    }
    
    /**
     * Calculate points for a team
     *
     * @param EventTeam $team
     * @return int
     */
    protected function calculateTeamPoints(EventTeam $team): int
    {
        $points = 0;
        
        // Calculate points for each team member
        foreach ($team->members as $member) {
            // Points from regular challenge submissions
            foreach ($member->eventSubmissions as $submission) {
                $challenge = $submission->eventChallange;
                
                if ($challenge->flag_type === 'single') {
                    // For single flag challenges
                    $firstSolver = EventChallangeSubmission::where('event_challange_id', $submission->event_challange_id)
                        ->where('solved', true)
                        ->orderBy('solved_at')
                        ->first();
                        
                    if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                        $points += $challenge->firstBloodBytes ?? 0;
                    } else {
                        $points += $challenge->bytes ?? 0;
                    }
                }
            }
            
            // Points from flag submissions
            foreach ($member->flagSubmissions as $flagSubmission) {
                $challenge = $flagSubmission->eventChallangeFlag->eventChallange;
                
                if ($challenge->flag_type === 'multiple_individual') {
                    // For individual flags, each flag gives points
                    $firstSolver = EventChallangeFlagSubmission::where('event_challange_flag_id', $flagSubmission->event_challange_flag_id)
                        ->where('solved', true)
                        ->orderBy('solved_at')
                        ->first();
                        
                    if ($firstSolver && $firstSolver->user_uuid === $member->uuid) {
                        $points += $flagSubmission->eventChallangeFlag->firstBloodBytes ?? 0;
                    } else {
                        $points += $flagSubmission->eventChallangeFlag->bytes ?? 0;
                    }
                }
            }
        }
        
        return $points;
    }
    
    /**
     * Calculate the average solve time for a team
     *
     * @param array $teamData
     * @return float
     */
    protected function calculateTeamAverageSolveTime(array $teamData): float
    {
        $solveTimestamps = [];
        
        // Process each member's challenge completions
        foreach ($teamData['members'] as $member) {
            if (isset($member['challenge_completions'])) {
                foreach ($member['challenge_completions'] as $completion) {
                    if (isset($completion['completed_at'])) {
                        $solveTimestamps[] = strtotime($completion['completed_at']);
                    }
                }
            }
        }
        
        // If no challenges were solved, return a large number
        if (empty($solveTimestamps)) {
            return PHP_INT_MAX;
        }
        
        // Return the latest solve timestamp as a measure of how quickly the team solved challenges
        // Teams that solved all their challenges earlier will have a lower maximum timestamp
        return max($solveTimestamps);
    }
}