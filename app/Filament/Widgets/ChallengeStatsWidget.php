<?php

namespace App\Filament\Widgets;

use App\Models\Challange;
use App\Models\EventChallange;
use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChallengeStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Challenges', Challange::count())
                ->description('Total challenges in the system')
                ->descriptionIcon('heroicon-m-puzzle-piece')
                ->color('primary'),
                
            Stat::make('Event Challenges', EventChallange::count())
                ->description('Challenges used in events')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('danger'),
                
            Stat::make('Total Submissions', Submission::count())
                ->description('Challenge submissions by users')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('success'),
        ];
    }
} 