<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use App\Models\EventChallange;
use App\Models\ChallangeCategory;
use App\Models\EventInvitation;
use Filament\Forms;
use Filament\Forms\Form;
// // use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EventInvitationsImport;
use App\Models\User;
use App\Models\EventRegistration;
use App\Mail\EventRegistrationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class EventResource extends BaseResource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function getNavigationGroup(): ?string
    {
        return 'Events';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Event Details')
                        ->description('Set up the basic information for your event')
                        ->schema([
                            Forms\Components\Section::make('Basic Information')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('description')
                                        ->required()
                                        ->columnSpanFull(),
                                    Forms\Components\FileUpload::make('image')
                                        ->required()
                                        ->imageEditor()
                                        ->image()
                                        ->columnSpanFull(),
                                    Forms\Components\FileUpload::make('background_image')
                                        ->required()
                                        ->imageEditor()
                                        ->image()
                                        ->columnSpanFull(),
                                    Forms\Components\Toggle::make('freeze')
                                    ->label('Freeze')
                                    ->afterStateUpdated(function ($state, $record) {
                                        try {
                                            $eventId = $record->uuid;
                                            
                                            // Set freeze_time when freezing or set to null when unfreezing
                                            if ($state) {
                                                $record->freeze_time = now();
                                                $record->save();
                                            } else {
                                                $record->freeze_time = null;
                                                $record->save();
                                            }
                                            
                                            Http::post('http://213.136.91.209:3000/api/broadcast-freeze?eventId=' . $eventId, [
                                                'freeze' => $state ? true : false,
                                                'eventId' => $eventId,
                                                'key' => 'cb209876540331298765'
                                            ]);
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->title('Error updating freeze status')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                                    Forms\Components\Toggle::make('is_private')
                                        ->label('Private Event')
                                        ->helperText('If enabled, only invited users can register')
                                        ->reactive(),
                                    Forms\Components\Toggle::make('is_main')
                                        ->label('Main Event')
                                        ->helperText('If enabled, this will be the main featured event. Only one event can be the main event.')
                                        ->reactive(),
                                ]),

                            Forms\Components\Section::make('Registration Period')
                                ->description('Set when users can register for this event')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('registration_start_date')
                                        ->required()
                                        ->timezone('Africa/Cairo')
                                        ->helperText('Users can start registering from this date'),
                                    Forms\Components\DateTimePicker::make('registration_end_date')
                                        ->required()
                                        ->timezone('Africa/Cairo')
                                        ->helperText('Registration closes at this date')
                                        ->afterOrEqual('registration_start_date'),
                                ])->columns(2),

                            Forms\Components\Section::make('Team Formation Period')
                                ->description('Set when users can create and join teams')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('team_formation_start_date')
                                        ->required()
                                        ->timezone('Africa/Cairo')
                                        ->helperText('Users can start creating/joining teams from this date')
                                        ->afterOrEqual('registration_start_date'),
                                    Forms\Components\DateTimePicker::make('team_formation_end_date')
                                        ->required()
                                        ->timezone('Africa/Cairo')
                                        ->helperText('Team formation closes at this date')
                                        ->afterOrEqual('team_formation_start_date'),
                                ])->columns(2),

                            Forms\Components\Section::make('Event Period')
                                ->schema([
                                    Forms\Components\DateTimePicker::make('start_date')
                                        ->required()
                                        ->timezone('Africa/Cairo')
                                        ->helperText('When the event/challenge actually starts')
                                        ->afterOrEqual('team_formation_end_date'),
                                    Forms\Components\DateTimePicker::make('end_date')
                                        ->required()
                                        ->timezone('Africa/Cairo')
                                        ->helperText('When the event/challenge ends')
                                        ->afterOrEqual('start_date'),
                                ])->columns(2),

                            Forms\Components\Section::make('Team Settings')
                                ->schema([
                                    Forms\Components\Toggle::make('requires_team')
                                        ->required()
                                        ->default(true)
                                        ->helperText('If enabled, users must be in a team to participate'),
                                    Forms\Components\TextInput::make('team_minimum_members')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->helperText('Minimum number of members required per team'),
                                    Forms\Components\TextInput::make('team_maximum_members')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->helperText('Maximum number of members allowed per team')
                                ])->columns(3),
                        ]),
                    
                    Forms\Components\Wizard\Step::make('Challenges')
                        ->description('Add challenges to your event')
                        ->schema([
                            Forms\Components\Repeater::make('challenges')
                                ->schema([
                                    Forms\Components\Select::make('category_uuid')
                                        ->label('Category')
                                        ->options(ChallangeCategory::all()->pluck('name', 'uuid'))
                                        ->required(),
                                    Forms\Components\TextInput::make('title')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description')
                                        ->required(),
                                    Forms\Components\TextInput::make('made_by')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('made_by_url')
                                        ->nullable()
                                        ->maxLength(255),
                                    Forms\Components\TagsInput::make('keywords')
                                        ->nullable(),
                                    Forms\Components\Select::make('difficulty')
                                        ->required()
                                        ->options([
                                            'easy' => 'Easy',
                                            'medium' => 'Medium',
                                            'hard' => 'Hard',
                                            'very_hard' => 'Very Hard',
                                        ]),
                                    Forms\Components\Select::make('flag_type')
                                        ->required()
                                        ->options([
                                            'single' => 'Single Flag',
                                            'multiple_all' => 'Multiple Flags (Points after all flags)',
                                            'multiple_individual' => 'Multiple Flags (Individual points)',
                                        ])
                                        ->default('single')
                                        ->reactive(),
                                    Forms\Components\TextInput::make('bytes')
                                        ->numeric()
                                        ->required(fn (Forms\Get $get) => $get('flag_type') !== 'multiple_individual')
                                        ->visible(fn (Forms\Get $get) => $get('flag_type') !== 'multiple_individual'),
                                    Forms\Components\TextInput::make('firstBloodBytes')
                                        ->numeric()
                                        ->required(fn (Forms\Get $get) => $get('flag_type') !== 'multiple_individual')
                                        ->visible(fn (Forms\Get $get) => $get('flag_type') !== 'multiple_individual'),
                                    Forms\Components\TextInput::make('flag')
                                        ->required(fn (Forms\Get $get) => $get('flag_type') === 'single')
                                        ->maxLength(255)
                                        ->visible(fn (Forms\Get $get) => $get('flag_type') === 'single'),
                                    Forms\Components\Repeater::make('flags')
                                        ->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->required()
                                                ->maxLength(255)
                                                ->label('Flag Name'),
                                            Forms\Components\TextInput::make('ar_name')
                                                ->maxLength(255)
                                                ->label('Flag Name (Arabic)'),
                                            Forms\Components\TextInput::make('flag')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\Textarea::make('description')
                                                ->label('Flag Description')
                                                ->visible(fn (Forms\Get $get) => $get('../../flag_type') === 'multiple_all'),
                                            Forms\Components\TextInput::make('bytes')
                                                ->numeric()
                                                ->required(fn (Forms\Get $get) => $get('../../flag_type') === 'multiple_individual')
                                                ->visible(fn (Forms\Get $get) => $get('../../flag_type') === 'multiple_individual'),
                                            Forms\Components\TextInput::make('firstBloodBytes')
                                                ->numeric()
                                                ->required(fn (Forms\Get $get) => $get('../../flag_type') === 'multiple_individual')
                                                ->visible(fn (Forms\Get $get) => $get('../../flag_type') === 'multiple_individual'),
                                        ])
                                        ->visible(fn (Forms\Get $get) => $get('flag_type') !== 'single')
                                        ->defaultItems(0)
                                        ->addActionLabel('Add Flag')
                                        ->relationship('flags'),
                                    Forms\Components\Select::make('input_type')
                                        ->label('Input Type')
                                        ->options([
                                            'file' => 'File Only',
                                            'link' => 'Link Only',
                                            'file_and_link' => 'File and Link',
                                        ])
                                        ->default('file')
                                        ->reactive(),
                                    Forms\Components\FileUpload::make('file')
                                        ->label('File')
                                        ->visible(fn (Forms\Get $get) => in_array($get('input_type'), ['file', 'file_and_link'])),
                                    Forms\Components\Textarea::make('link')
                                        ->label('Link')
                                        ->visible(fn (Forms\Get $get) => in_array($get('input_type'), ['link', 'file_and_link'])),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add Challenge')
                                ->columnSpanFull()
                                ->relationship('challenges')
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record) {
                                        // Load flags for each challenge
                                        foreach ($state as $index => $challenge) {
                                            if (isset($challenge['id'])) {
                                                $challengeModel = EventChallange::find($challenge['id']);
                                                if ($challengeModel) {
                                                    $state[$index]['flags'] = $challengeModel->flags->toArray();
                                                }
                                            }
                                        }
                                        $component->state($state);
                                    }
                                }),
                        ]),
                    
                    Forms\Components\Wizard\Step::make('Invitations')
                        ->description('Add users to your private event')
                        ->schema([
                            Forms\Components\Section::make('Add Users')
                                ->schema([
                                    Forms\Components\Toggle::make('is_private')
                                        ->label('Private Event')
                                        ->helperText('If enabled, only invited users can view and will be auto-registered for this event')
                                        ->reactive(),
                                    Forms\Components\FileUpload::make('invitation_list')
                                        ->label('User List (CSV)')
                                        ->helperText('Upload a CSV file with a list of user emails to add to this event. They will be automatically registered if their account exists.')
                                        ->columnSpanFull()
                                        ->acceptedFileTypes(['text/csv'])
                                        ->directory('invitations')
                                        ->preserveFilenames()
                                        ->visibility('private')
                                        ->live()
                                        ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                            if (!$state) return;
                                            
                                            try {
                                                $eventUuid = $get('uuid');
                                                
                                                if (empty($eventUuid)) {
                                                    throw new \Exception('Event UUID is missing. Please save the event first.');
                                                }
                                                
                                                Log::info('Starting CSV import for event: ' . $eventUuid);
                                                
                                                // Get the uploaded file path
                                                $tmpFile = null;
                                                
                                                if ($state instanceof TemporaryUploadedFile) {
                                                    $tmpFile = $state->getRealPath();
                                                } else {
                                                    $tmpFile = Storage::disk('local')->path($state);
                                                }
                                                
                                                if (!file_exists($tmpFile)) {
                                                    throw new \Exception("File not found at: " . $tmpFile);
                                                }
                                                
                                                Log::info('Importing CSV from: ' . $tmpFile);
                                                
                                                // Import the CSV using the real path
                                                $import = new EventInvitationsImport($eventUuid);
                                                Excel::import($import, $tmpFile);
                                                
                                                // Clear the file input
                                                $set('invitation_list', null);
                                                
                                                Notification::make()
                                                    ->title('Users added and auto-registered successfully')
                                                    ->success()
                                                    ->send();
                                                    
                                            } catch (\Exception $e) {
                                                Log::error('CSV Import Error: ' . $e->getMessage());
                                                Log::error('Stack trace: ' . $e->getTraceAsString());
                                                
                                                Notification::make()
                                                    ->title('Error adding users')
                                                    ->body($e->getMessage())
                                                    ->danger()
                                                    ->persistent()
                                                    ->send();
                                                    
                                                // Clear the file input on error
                                                $set('invitation_list', null);
                                            }
                                        })
                                        ->visible(fn (Forms\Get $get) => $get('is_private')),
                                    Forms\Components\TagsInput::make('invited_emails')
                                        ->label('Add Individual Users')
                                        ->helperText('Enter email addresses of users to add to this event. They will be automatically registered if their account exists.')
                                        ->placeholder('Enter email address and press Enter')
                                        ->visible(fn (Forms\Get $get) => $get('is_private'))
                                        ->default([])
                                        ->dehydrated(true)
                                ]),
                        ]),
                ])
                ->skippable()
                ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_private')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_main')
                    ->boolean()
                    ->label('Main Event'),
                Tables\Columns\TextColumn::make('registration_start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('registration_end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team_formation_start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team_formation_end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_team')
                    ->boolean(),
                Tables\Columns\TextColumn::make('team_minimum_members')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team_maximum_members')
                    ->numeric()
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
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            EventResource\Widgets\EventRegistrationsWidget::class,
            EventResource\Widgets\TeamsWidget::class,
            EventResource\Widgets\ChallengesSolvedWidget::class,
        ];
    }
}
