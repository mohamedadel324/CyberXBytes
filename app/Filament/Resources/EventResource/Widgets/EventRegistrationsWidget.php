<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\EventRegistration;

class EventRegistrationsWidget extends ChartWidget
{
    protected static ?string $heading = 'Event Registrations';
    
    public $record = null;
    
    protected static string $color = 'success';
    
    protected static ?string $maxHeight = '300px';
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getData(): array
    {
        if (!$this->record) {
            return [
                'datasets' => [
                    [
                        'label' => 'Registrations',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }
        
        // Get registrations and group them by date
        $registrations = EventRegistration::where('event_uuid', $this->record->uuid)
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
            $data[] = $registrations[$dateString]->count ?? 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Registrations',
                    'data' => $data,
                    'fill' => true,
                    'backgroundColor' => 'rgba(72, 187, 120, 0.1)',
                    'borderColor' => 'rgb(72, 187, 120)',
                ],
            ],
            'labels' => $labels,
        ];
    }
} 