<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\EventTeam;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class EventStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        $now = Carbon::now();
        
        // Get active events (current time is between start_date and end_date)
        $activeEvents = Event::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->count();
            
        return [
            Stat::make('Total Events', Event::count())
                ->description('Total events created')
                ->descriptionIcon('heroicon-m-flag')
                ->color('primary'),
                
            Stat::make('Active Events', $activeEvents)
                ->description('Currently active events')
                ->descriptionIcon('heroicon-m-play')
                ->color('success'),
                
            Stat::make('Total Teams', EventTeam::count())
                ->description('Teams across all events')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),
        ];
    }
} 