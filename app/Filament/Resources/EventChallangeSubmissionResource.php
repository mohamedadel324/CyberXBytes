<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventChallangeSubmissionResource\Pages;
use App\Filament\Resources\EventChallangeSubmissionResource\RelationManagers;
use App\Models\EventChallangeSubmission;
use Filament\Forms;
use Filament\Forms\Form;
// // use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventChallangeSubmissionResource extends BaseResource
{
    protected static ?string $model = EventChallangeSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function getNavigationGroup(): ?string
    {
        return 'Submissions';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_challange_id')
                        ->preload()
                        ->searchable()
                    ->required()
                    ->relationship('eventChallange', 'title'),
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_challange_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_uuid')
                    ->searchable(),
                Tables\Columns\IconColumn::make('solved')
                    ->boolean(),
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
            'index' => Pages\ListEventChallangeSubmissions::route('/'),
            'create' => Pages\CreateEventChallangeSubmission::route('/create'),
            'edit' => Pages\EditEventChallangeSubmission::route('/{record}/edit'),
        ];
    }
}
