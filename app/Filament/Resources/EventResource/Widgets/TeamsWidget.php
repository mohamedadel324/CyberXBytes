<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\EventTeam;

class TeamsWidget extends ChartWidget
{
    protected static ?string $heading = 'Event Teams';
    
    public $record = null;
    
    protected static string $color = 'info';
    
    protected static ?string $maxHeight = '300px';
    
    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getData(): array
    {
        if (!$this->record) {
            return [
                'datasets' => [
                    [
                        'label' => 'Teams',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }
        
        // Get teams and group them by date
        $teams = EventTeam::where('event_uuid', $this->record->uuid)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');
        
        // Generate date range from event creation to now
        $startDate = $this->record->created_at->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        
        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            new \DateTime($endDate)
        );
        
        $labels = [];
        $data = [];
        
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $labels[] = $date->format('M d');
            $data[] = $teams[$dateString]->count ?? 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Teams',
                    'data' => $data,
                    'backgroundColor' => 'rgba(49, 130, 206, 0.7)',
                ],
            ],
            'labels' => $labels,
        ];
    }
} 