<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChallangeResource\Pages;
use App\Filament\Resources\ChallangeResource\RelationManagers;
use App\Models\Challange;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\FileUpload;

class ChallangeResource extends Resource
{
    protected static ?string $model = Challange::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function getNavigationGroup(): ?string
    {
        return 'Challanges Management';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('lab_category_uuid')
                    ->required()
                    ->relationship('labCategory', 'title')
                    ,
                Forms\Components\Select::make('category_uuid')
                    ->required()
                    ->relationship('category', 'name'),
                Forms\Components\TagsInput::make('key_words')
                    ->columnSpanFull()
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull()
                    ->required(),
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
            Select::make('input_type')
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


        Group::make([
            FileUpload::make('file')
            ->required()
                ->label('File')
                ->columnSpanFull(),
        ])
        ->visible(function (Forms\Get $get) {
            return in_array($get('input_type'), ['file', 'file_and_link']);
        }),

        // Link Field Group
        Group::make([
            Textarea::make('link')
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
                Tables\Columns\TextColumn::make('lab_category_uuid')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('category.icon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image'),
                Tables\Columns\TextColumn::make('difficulty')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bytes')
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
            'index' => Pages\ListChallanges::route('/'),
            'create' => Pages\CreateChallange::route('/create'),
            'edit' => Pages\EditChallange::route('/{record}/edit'),
        ];
    }
}
