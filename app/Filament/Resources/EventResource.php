<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\EventInvitationsImport;

class EventResource extends Resource
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
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextArea::make('description')
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
                        Forms\Components\Toggle::make('is_private')
                            ->label('Private Event')
                            ->helperText('If enabled, only invited users can register')
                            ->reactive(),
                        Forms\Components\FileUpload::make('invitation_list')
                            ->label('Invitation List (CSV)')
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
                                    
                                    \Log::info('Starting CSV import for event: ' . $eventUuid);
                                    
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
                                    
                                    \Log::info('Importing CSV from: ' . $tmpFile);
                                    
                                    // Import the CSV using the real path
                                    $import = new EventInvitationsImport($eventUuid);
                                    Excel::import($import, $tmpFile);
                                    
                                    // Clear the file input
                                    $set('invitation_list', null);
                                    
                                    Notification::make()
                                        ->title('Invitations imported successfully')
                                        ->success()
                                        ->send();
                                        
                                } catch (\Exception $e) {
                                    \Log::error('CSV Import Error: ' . $e->getMessage());
                                    \Log::error('Stack trace: ' . $e->getTraceAsString());
                                    
                                    Notification::make()
                                        ->title('Error importing invitations')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                        
                                    // Clear the file input on error
                                    $set('invitation_list', null);
                                }
                            })
                            ->visible(fn (Forms\Get $get) => $get('is_private')),
                        Forms\Components\Hidden::make('invited_emails')
                            ->default([])
                            ->dehydrated(true),
                    ]),

                Forms\Components\Section::make('Registration Period')
                    ->description('Set when users can register for this event')
                    ->schema([
                        Forms\Components\DateTimePicker::make('registration_start_date')
                            ->required()
                            ->timezone('UTC')
                            ->helperText('Users can start registering from this date'),
                        Forms\Components\DateTimePicker::make('registration_end_date')
                            ->required()
                            ->timezone('UTC')
                            ->helperText('Registration closes at this date')
                            ->afterOrEqual('registration_start_date'),
                    ])->columns(2),

                Forms\Components\Section::make('Team Formation Period')
                    ->description('Set when users can create and join teams')
                    ->schema([
                        Forms\Components\DateTimePicker::make('team_formation_start_date')
                            ->required()
                            ->timezone('UTC')
                            ->helperText('Users can start creating/joining teams from this date')
                            ->afterOrEqual('registration_start_date'),
                        Forms\Components\DateTimePicker::make('team_formation_end_date')
                            ->required()
                            ->timezone('UTC')
                            ->helperText('Team formation closes at this date')
                            ->afterOrEqual('team_formation_start_date'),
                    ])->columns(2),

                Forms\Components\Section::make('Event Period')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_date')
                            ->required()
                            ->timezone('UTC')
                            ->helperText('When the event/challenge actually starts')
                            ->afterOrEqual('team_formation_end_date'),
                        Forms\Components\DateTimePicker::make('end_date')
                            ->required()
                            ->timezone('UTC')
                            ->helperText('When the event/challenge ends')
                            ->afterOrEqual('start_date'),
                    ])->columns(3),

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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_private')
                    ->boolean()
                    ->label('Private'),
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
}
