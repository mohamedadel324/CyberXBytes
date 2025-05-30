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
    
    // Track the current sort direction
    protected string $sortDirection = 'desc';
    
    public function table(Table $table): Table
    {
        // Initialize the cache
        $this->teamPointsCache = collect();
        
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
                
                // Calculate points for all teams
                foreach ($teams as $team) {
                    $this->teamPointsCache[$team->id] = $this->calculateTeamPoints($team);
                }
                
                // Get team IDs sorted by points
                $sortedTeamIds = $this->teamPointsCache
                    ->sortDesc()
                    ->keys()
                    ->toArray();
                
                // Use manual sorting instead of FIELD() since we're working with UUIDs
                if (!empty($sortedTeamIds)) {
                    // Convert the array to a position map for sorting
                    $positions = array_flip($sortedTeamIds);
                    
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
                        $solvedChallenges = collect();
                        
                        foreach ($record->members as $member) {
                            // Add regular challenge submissions
                            foreach ($member->eventSubmissions as $submission) {
                                $solvedChallenges->push($submission->event_challange_id);
                            }
                        }
                        
                        return $solvedChallenges->unique()->count();
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
} 