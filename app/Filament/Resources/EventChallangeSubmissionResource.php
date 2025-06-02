<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventChallangeSubmissionResource\Pages;
use App\Filament\Resources\EventChallangeSubmissionResource\RelationManagers;
use App\Models\EventChallangeFlagSubmission;
use Filament\Forms;
use Filament\Forms\Form;
// // use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventChallangeSubmissionResource extends BaseResource // This now represents flag submissions
{
    protected static ?string $model = EventChallangeFlagSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Flag Submission';
    protected static ?string $pluralModelLabel = 'Flag Submissions';
    
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
            ->columns([
                Tables\Columns\TextColumn::make('eventChallangeFlag.name')
                    ->label('Flag Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('eventChallangeFlag.eventChallange.title')
                    ->label('Challenge')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.user_name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\IconColumn::make('solved')
                    ->boolean(),
                Tables\Columns\TextColumn::make('submission')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->numeric()
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
                Tables\Filters\SelectFilter::make('event_challange_flag_id')
                    ->relationship('eventChallangeFlag', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Flag'),
                
                Tables\Filters\SelectFilter::make('challenge')
                    ->relationship('eventChallangeFlag.eventChallange', 'title')
                    ->searchable()
                    ->preload()
                    ->label('Challenge'),
                    
                Tables\Filters\SelectFilter::make('user_uuid')
                    ->relationship('user', 'user_name')
                    ->searchable()
                    ->preload()
                    ->label('User'),
                    
                Tables\Filters\Filter::make('solved')
                    ->toggle()
                    ->label('Show Only Solved')
                    ->query(fn (Builder $query): Builder => $query->where('solved', true)),
            ])->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListEventChallangeSubmissions::route('/'),
            'create' => Pages\CreateEventChallangeSubmission::route('/create'),
            'edit' => Pages\EditEventChallangeSubmission::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Flag Submissions';
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('solved', true)->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
