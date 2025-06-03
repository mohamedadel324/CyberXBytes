<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Helpers\CountryList;
use App\Models\User;
use App\Models\ChallangeCategory;
use App\Models\Challange;
use App\Models\EventChallange;
use Filament\Forms;
use Filament\Forms\Form;
// use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Tabs;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('country')
                    ->required()
                    ->searchable()
                    ->options(CountryList::getCountries()),
                Forms\Components\FileUpload::make('profile_image')
                    ->image()
                    ->directory('profile_images')
                    ->disk('public')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->required(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Forms\Components\DateTimePicker::make('email_verified_at'),
            
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                ->copyable()
                ->searchable(),
                Tables\Columns\ImageColumn::make('profile_image')->circular(),
                Tables\Columns\TextColumn::make('user_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_seen')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_name'),
                        Infolists\Components\TextEntry::make('email'),
                        Infolists\Components\TextEntry::make('country'),
                        Infolists\Components\ImageEntry::make('profile_image')->circular(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                
                Tabs::make('User Activity')
                    ->tabs([
                        Tabs\Tab::make('Submissions')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('submissions')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('challange.title')
                                            ->label('Challenge'),
                                        Infolists\Components\TextEntry::make('challange.category.name')
                                            ->label('Category'),
                                        Infolists\Components\TextEntry::make('flag')
                                            ->label('Submitted Flag'),
                                        Infolists\Components\IconEntry::make('solved')
                                            ->boolean(),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->dateTime()
                                            ->label('Submitted At'),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                            ]),
                            
                        Tabs\Tab::make('Event Submissions')
                            ->schema([
                                // Challenge submissions
                                Infolists\Components\Section::make('Challenge Submissions')
                                    ->schema([
                                        Infolists\Components\RepeatableEntry::make('eventSubmissions')
                                            ->schema([
                                                Infolists\Components\TextEntry::make('eventChallange.title')
                                                    ->label('Challenge'),
                                                Infolists\Components\TextEntry::make('eventChallange.category.name')
                                                    ->label('Category'),
                                                Infolists\Components\TextEntry::make('eventChallange.event.title')
                                                    ->label('Event'),
                                                Infolists\Components\IconEntry::make('solved')
                                                    ->boolean(),
                                                Infolists\Components\TextEntry::make('attempts')
                                                    ->label('Attempts'),
                                                Infolists\Components\TextEntry::make('solved_at')
                                                    ->dateTime()
                                                    ->label('Solved At'),
                                                Infolists\Components\TextEntry::make('submission_type')
                                                    ->label('Type')
                                                    ->default('Challenge')
                                                    ->badge()
                                                    ->color('warning'),
                                            ])
                                            ->columns(7)
                                            ->columnSpanFull()
                                    ]),
                                    
                                // Flag submissions
                                Infolists\Components\Section::make('Flag Submissions')
                                    ->schema([
                                        Infolists\Components\RepeatableEntry::make('flagSubmissions')
                                            ->schema([
                                                Infolists\Components\TextEntry::make('eventChallangeFlag.name')
                                                    ->label('Flag'),
                                                Infolists\Components\TextEntry::make('eventChallangeFlag.eventChallange.title')
                                                    ->label('Challenge'),
                                                Infolists\Components\TextEntry::make('eventChallangeFlag.eventChallange.category.name')
                                                    ->label('Category'),
                                                Infolists\Components\TextEntry::make('eventChallangeFlag.eventChallange.event.title')
                                                    ->label('Event'),
                                                Infolists\Components\IconEntry::make('solved')
                                                    ->boolean(),
                                                Infolists\Components\TextEntry::make('attempts')
                                                    ->label('Attempts'),
                                                Infolists\Components\TextEntry::make('solved_at')
                                                    ->dateTime()
                                                    ->label('Solved At'),
                                                Infolists\Components\TextEntry::make('submission_type')
                                                    ->label('Type')
                                                    ->default('Flag')
                                                    ->badge()
                                                    ->color('success'),
                                            ])
                                            ->columns(7)
                                            ->columnSpanFull()
                                    ]),
                            ]),
                            
                        Tabs\Tab::make('Solved Challenges')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('regularSolvedChallenges')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title')
                                            ->label('Challenge'),
                                        Infolists\Components\TextEntry::make('category.name')
                                            ->label('Category'),
                                        Infolists\Components\TextEntry::make('difficulty')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'easy' => 'success',
                                                'medium' => 'warning',
                                                'hard' => 'danger',
                                                'very_hard' => 'danger',
                                                default => 'gray',
                                            }),
                                        Infolists\Components\TextEntry::make('bytes')
                                            ->label('Points'),
                                        Infolists\Components\TextEntry::make('solved_at')
                                            ->dateTime()
                                            ->label('Solved At'),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Completion by Category')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('categoryCompletion')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Category'),
                                        Infolists\Components\TextEntry::make('solved_count')
                                            ->label('Solved'),
                                        Infolists\Components\TextEntry::make('total_count')
                                            ->label('Total'),
                                        Infolists\Components\TextEntry::make('percentage')
                                            ->label('Completion')
                                            ->formatStateUsing(fn (string $state): string => "{$state}%"),
                                        Infolists\Components\TextEntry::make('percentage')
                                            ->label('Progress')
                                            ->badge()
                                            ->color(function ($state) {
                                                if ($state < 30) return 'danger';
                                                if ($state < 70) return 'warning';
                                                return 'success';
                                            }),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                            ]),
                            
                        Tabs\Tab::make('Unsolved Challenges')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('unsolvedChallenges')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('title')
                                            ->label('Challenge'),
                                        Infolists\Components\TextEntry::make('category.name')
                                            ->label('Category'),
                                        Infolists\Components\TextEntry::make('difficulty')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'easy' => 'success',
                                                'medium' => 'warning',
                                                'hard' => 'danger',
                                                'very_hard' => 'danger',
                                                default => 'gray',
                                            }),
                                        Infolists\Components\TextEntry::make('bytes')
                                            ->label('Points'),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                            ]),
                            
                        Tabs\Tab::make('Events Joined')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('registeredEvents')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Event Name'),
                                        Infolists\Components\TextEntry::make('description')
                                            ->label('Description')
                                            ->limit(50),
                                        Infolists\Components\TextEntry::make('start_date')
                                            ->dateTime()
                                            ->label('Start Date'),
                                        Infolists\Components\TextEntry::make('end_date')
                                            ->dateTime()
                                            ->label('End Date'),
                                        Infolists\Components\TextEntry::make('pivot.created_at')
                                            ->dateTime()
                                            ->label('Registered At'),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'w-full',
                    ])
                    ->columnSpanFull(),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'User Management';
    }
    
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        $query->with([
            'submissions.challange.category',
            'eventSubmissions.eventChallange.category',
            'eventSubmissions.eventChallange.event',
            'flagSubmissions.eventChallangeFlag.eventChallange.category',
            'flagSubmissions.eventChallangeFlag.eventChallange.event',
            'solvedChallenges.category',
            'registeredEvents',
        ])
        ->withCount([
            'submissions', 
            'eventSubmissions',
            'flagSubmissions', 
            'solvedChallenges',
        ]);
        
        return $query;
    }
    
    public static function getModelLabel(): string
    {
        return 'User';
    }
}
