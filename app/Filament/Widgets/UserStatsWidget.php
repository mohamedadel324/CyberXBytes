<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\EventRegistration;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Total registered users on the platform')
                ->descriptionIcon('heroicon-m-user')
                ->color('primary'),
                
            Stat::make('New Users Today', User::whereDate('created_at', today())->count())
                ->description('Users registered today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),
                
            Stat::make('Event Registrations', EventRegistration::count())
                ->description('Total event registrations')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
        ];
    }
} 