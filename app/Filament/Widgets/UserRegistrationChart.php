<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

class UserRegistrationChart extends ChartWidget
{
    protected static ?string $heading = 'User Registrations';
    
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '500px';

    protected function getData(): array
    {
        $data = Trend::model(User::class)
            ->between(
                start: now()->subMonths(6),
                end: now(),
            )
            ->perMonth()
            ->count();
            
        return [
            'datasets' => [
                [
                    'label' => 'User Registrations',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => '#38FFE5',
                    'borderColor' => '#38FFE5',
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->format('M')),
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
} 