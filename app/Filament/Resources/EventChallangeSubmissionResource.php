<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventChallangeSubmissionResource\Pages;
use App\Models\EventChallangeFlagSubmission;
use App\Models\EventChallangeSubmission;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class EventChallangeSubmissionResource extends Resource
{
    // Set the model class based on a static property that we can change
    protected static ?string $model = EventChallangeFlagSubmission::class;

    // Track which model type is currently active
    // Removed static tracking of model type - now using session
    
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Combined Submissions';
    protected static ?string $pluralModelLabel = 'Combined Submissions';
    
    // We're now using session instead of static properties to track model type
    // This method is here for backward compatibility
    public static function useModel(string $type): void
    {
        session(['submission_type' => $type]);
    }
    
    public static function getNavigationGroup(): ?string
    {
        return 'Submissions';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_challange_flag_id')
                        ->preload()
                        ->searchable()
                    ->required()
                    ->relationship('eventChallangeFlag', 'name'),
                Forms\Components\Select::make('user_uuid')
                    ->required()
                    ->preload()
                    ->searchable()
                    ->relationship('user', 'user_name'),
                Forms\Components\Textarea::make('submission')
                    ->required()
                    ->columnSpanFull(),
                    Forms\Components\TextInput::make('attempts')
                    ->required()
                    ->numeric()
                    ->default(0),
                    Forms\Components\TextInput::make('ip')
                    ->required()
                    ->default(0),
                    Forms\Components\DateTimePicker::make('solved_at'),
                    Forms\Components\Toggle::make('solved')
                        ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->bulkActions([
                Tables\Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->color('danger')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $records->each->delete();
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('view_flag_submissions')
                    ->label('Flag Submissions')
                    ->button()
                    ->color('primary')
                    ->disabled(fn () => session('submission_type', 'flag') === 'flag')
                    ->action(function () {
                        session(['submission_type' => 'flag']);
                        return redirect(request()->header('Referer'));
                    }),
                    
                Tables\Actions\Action::make('view_challenge_submissions')
                    ->label('Challenge Submissions')
                    ->button()
                    ->color('warning')
                    ->disabled(fn () => session('submission_type', 'flag') === 'challenge')
                    ->action(function () {
                        session(['submission_type' => 'challenge']);
                        return redirect(request()->header('Referer'));
                    }),
            ])
            ->columns([
                // Type indicator based on model class - shows Flag or Challenge
                Tables\Columns\TextColumn::make('submission_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => $record instanceof EventChallangeFlagSubmission ? 'Flag' : 'Challenge')
                    ->color(fn ($state, $record) => $record instanceof EventChallangeFlagSubmission ? 'success' : 'warning'),
                    
                // Event Title (for both submission types)
                Tables\Columns\TextColumn::make('event_title')
                    ->label('Event')
                    ->getStateUsing(function($record) {
                        if ($record instanceof EventChallangeFlagSubmission) {
                            // For flag submissions: flag -> challenge -> event
                            if ($record->eventChallangeFlag && $record->eventChallangeFlag->eventChallange && $record->eventChallangeFlag->eventChallange->event) {
                                return $record->eventChallangeFlag->eventChallange->event->title ?? 'Unknown Event';
                            }
                        } elseif ($record instanceof EventChallangeSubmission) {
                            // For challenge submissions: challenge -> event
                            if ($record->eventChallange && $record->eventChallange->event) {
                                return $record->eventChallange->event->title ?? 'Unknown Event';
                            }
                        }
                        return 'Unknown Event';
                    }),
                
                // Challenge Name (for both submission types)
                Tables\Columns\TextColumn::make('challenge_name')
                    ->label('Challenge')
                    ->getStateUsing(function($record) {
                        if ($record instanceof EventChallangeFlagSubmission) {
                            // For flag submissions: flag -> challenge
                            if ($record->eventChallangeFlag && $record->eventChallangeFlag->eventChallange) {
                                return $record->eventChallangeFlag->eventChallange->title ?? 'Unknown Challenge';
                            }
                        } elseif ($record instanceof EventChallangeSubmission) {
                            // For challenge submissions: direct challenge
                            if ($record->eventChallange) {
                                return $record->eventChallange->title ?? 'Unknown Challenge';
                            }
                            return 'Challenge #' . $record->event_challange_id;
                        }
                        return 'Unknown Challenge';
                    }),
                    
                // Flag ID/Name (for flag submissions only)
                Tables\Columns\TextColumn::make('event_challange_flag_id')
                    ->label('Flag')
                    ->getStateUsing(function($record) {
                        if ($record instanceof EventChallangeFlagSubmission) {
                            if ($record->eventChallangeFlag) {
                                return $record->eventChallangeFlag->name ?? 'Unknown Flag';
                            }
                            return 'Flag #' . $record->event_challange_flag_id;
                        }
                        return null;
                    })
                    ->visible(fn ($record) => $record instanceof EventChallangeFlagSubmission),
                
                // Legacy Challenge ID/Title column (hidden as we now use the universal challenge_name column)
                Tables\Columns\TextColumn::make('event_challange_id')
                    ->label('Challenge (Legacy)')
                    ->getStateUsing(function($record) {
                        if ($record instanceof EventChallangeSubmission) {
                            if ($record->eventChallange) {
                                return $record->eventChallange->title ?? 'Unknown Challenge';
                            }
                            return 'Challenge #' . $record->event_challange_id;
                        }
                        return null;
                    })
                    ->visible(fn ($record) => $record instanceof EventChallangeSubmission)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user_uuid')
                    ->label('User')
                    ->formatStateUsing(function($state, $record) {
                        // Try to get the username when available
                        try {
                            return $record->user->user_name ?? $state;
                        } catch (\Exception $e) {
                            return $state;
                        }
                    })
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('solved')
                    ,
                Tables\Columns\TextColumn::make('submission')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('ip')
                    ->sortable(),
                Tables\Columns\TextColumn::make('solved_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')

            ->filters([
                
                // Filter by solved status
                Tables\Filters\Filter::make('solved')
                    ->toggle()
                    ->label('Show Only Solved')
                    ->query(fn (Builder $query): Builder => $query->where('solved', true)),
                
                // Filter by user UUID
                Tables\Filters\Filter::make('user_filter')
                    ->form([
                        Forms\Components\TextInput::make('user_search')
                            ->label('Search by User UUID')
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['user_search'] ?? null,
                            fn (Builder $query, $search): Builder => $query->where('user_uuid', 'like', "%{$search}%")
                        );
                    }),
                    
                // Filter by event
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Filter by Event')
                    ->options(function() {
                        // Get all events from the database
                        return \App\Models\Event::pluck('title', 'id');
                    })
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        $eventId = $data['value'];
                        
                        // Apply different logic based on the model type
                        if ($query->getModel() instanceof EventChallangeFlagSubmission) {
                            // For flag submissions, filter through the flag -> challenge -> event relationship
                            return $query->whereHas('eventChallangeFlag.eventChallange.event', function ($q) use ($eventId) {
                                $q->where('id', $eventId);
                            });
                        } else {
                            // For challenge submissions, filter through challenge -> event relationship
                            return $query->whereHas('eventChallange.event', function ($q) use ($eventId) {
                                $q->where('id', $eventId);
                            });
                        }
                    }),
            ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventChallangeSubmissions::route('/'),
            // No create or edit pages - read-only view
        ];
    }
    public static function getEloquentQuery(): Builder
    {
        // Check the current submission type from session
        $type = session('submission_type', 'flag');
        
        // Log for debugging
        Log::info('Current submission type: ' . $type);
        
        // Switch model based on the type
        if ($type === 'challenge') {
            Log::info('Loading CHALLENGE submissions');
            return EventChallangeSubmission::query();
        } else {
            Log::info('Loading FLAG submissions');
            return EventChallangeFlagSubmission::query();
        }
    }
    public static function getNavigationLabel(): string
    {
        return 'All Submissions';
    }
    
    public static function getNavigationBadge(): ?string
    {
        // Count submissions from both models
        $flagCount = EventChallangeFlagSubmission::where('solved', true)->count();
        $challengeCount = EventChallangeSubmission::where('solved', true)->count();
        
        return (string)($flagCount + $challengeCount);
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
