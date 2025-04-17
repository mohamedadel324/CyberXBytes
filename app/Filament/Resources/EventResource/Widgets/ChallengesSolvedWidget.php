<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\EventChallange;
use App\Models\EventChallengeSubmission;

class ChallengesSolvedWidget extends ChartWidget
{
    protected static ?string $heading = 'Challenge Solve Status';
    
    public $record = null;
    
    protected static string $color = 'danger';
    
    protected static ?string $maxHeight = '200px';
    
    protected function getType(): string
    {
        return 'doughnut';
    }
    
    protected function getData(): array
    {
        if (!$this->record) {
            return [
                'datasets' => [
                    [
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }
        
        // Get all challenges for this event
        $challenges = EventChallange::where('event_uuid', $this->record->uuid)->get();
        
        $totalChallenges = $challenges->count();
        $solvedChallenges = 0;
        $notSolvedChallenges = 0;
        
        // Count how many challenges have been solved at least once
        foreach ($challenges as $challenge) {
            $hasSolves = EventChallengeSubmission::where('event_challange_id', $challenge->id)
                ->where('solved', 1)
                ->exists();
                
            if ($hasSolves) {
                $solvedChallenges++;
            } else {
                $notSolvedChallenges++;
            }
        }
        
        // If no challenges found, show a placeholder
        if ($totalChallenges === 0) {
            return [
                'datasets' => [
                    [
                        'data' => [1],
                        'backgroundColor' => ['rgba(156, 163, 175, 0.7)'],
                    ],
                ],
                'labels' => ['No challenges'],
            ];
        }
        
        return [
            'datasets' => [
                [
                    'data' => [$solvedChallenges, $notSolvedChallenges],
                    'backgroundColor' => [
                        'rgba(22, 163, 74, 0.7)',  // Green (Solved)
                        'rgba(239, 68, 68, 0.7)',  // Red (Not Solved)
                    ],
                ],
            ],
            'labels' => ['Solved', 'Not Solved'],
        ];
    }
} 