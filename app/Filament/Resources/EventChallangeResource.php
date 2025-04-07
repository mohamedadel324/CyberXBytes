<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventChallangeResource\Pages;
use App\Filament\Resources\EventChallangeResource\RelationManagers;
use App\Models\EventChallange;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventChallangeResource extends Resource
{
    protected static ?string $model = EventChallange::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): ?string
    {
        return 'Events';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('event_uuid')
                    ->required()
                    ->relationship('event', 'title'),
                Forms\Components\Select::make('category_uuid')
                    ->required()
                    ->relationship('category', 'name'),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('difficulty')
                    ->required()
                    ->options([
                        'easy' => 'Easy',
                        'medium' => 'Medium',
                        'hard' => 'Hard',
                        'very_hard' => 'Very Hard',
                    ]),
                Forms\Components\TextInput::make('bytes')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('firstBloodBytes')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('flag')
                    ->required()
                    ->maxLength(255),
                    Forms\Components\Select::make('input_type')
            ->label('Input Type')
            ->options([
                'file' => 'File Only',
                'link' => 'Link Only',
                'file_and_link' => 'File and Link',
            ])
            ->reactive()
            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set, $state, $record) {
                if (!$state) {
                    if ($record?->file && $record?->link) {
                        $set('input_type', 'file_and_link');
                    } elseif ($record?->file) {
                        $set('input_type', 'file');
                    } elseif ($record?->link) {
                        $set('input_type', 'link');
                    } else {
                        $set('input_type', 'file'); 
                    }
                }
            })
            ->afterStateUpdated(function ($state, Forms\Set $set) {
                if ($state === 'file') {
                    $set('link', null);
                } elseif ($state === 'link') {
                    $set('file', null);
                }
            })
            ->required(),


            Forms\Components\Group::make([
            Forms\Components\FileUpload::make('file')
            ->required()
                ->label('File')
                ->columnSpanFull(),
        ])
        ->visible(function (Forms\Get $get) {
            return in_array($get('input_type'), ['file', 'file_and_link']);
        }),

        // Link Field Group
        Forms\Components\Group::make([
            Forms\Components\Textarea::make('link')
                ->label('Link')
                ->required()
                ->columnSpanFull(),
        ])
        ->visible(function (Forms\Get $get) {
            return in_array($get('input_type'), ['link', 'file_and_link']);
        }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event.title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.icon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('difficulty')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bytes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('firstBloodBytes')
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
            'index' => Pages\ListEventChallanges::route('/'),
            'create' => Pages\CreateEventChallange::route('/create'),
            'edit' => Pages\EditEventChallange::route('/{record}/edit'),
        ];
    }
}
