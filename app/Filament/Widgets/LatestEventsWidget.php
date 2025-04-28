<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LatestEventsWidget extends BaseWidget
{
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Event::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('eventStatus')
                    ->label('Status')
                    ->badge()
                    ->state(function (Event $record): string {
                        $now = Carbon::now();
                        
                        if ($record->start_date > $now) {
                            return 'upcoming';
                        } elseif ($record->end_date < $now) {
                            return 'ended';
                        } else {
                            return 'active';
                        }
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'upcoming' => 'warning',
                        'ended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->heading('Latest Events')
            ->defaultSort('created_at', 'desc');
    }
} 