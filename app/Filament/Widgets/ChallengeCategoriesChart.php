<?php

namespace App\Filament\Widgets;

use App\Models\Challange;
use App\Models\ChallangeCategory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ChallengeCategoriesChart extends ChartWidget
{
    protected static ?string $heading = 'Challenge Categories';
    
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '500px';


    protected function getData(): array
    {
        $categories = ChallangeCategory::withCount('challanges')->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Categories',
                    'data' => $categories->pluck('challanges_count')->toArray(),
                    'backgroundColor' => [
                        '#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#97BBCD', '#F7464A', '#46BFBD', '#FDB45C'
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'pie';
    }
} 