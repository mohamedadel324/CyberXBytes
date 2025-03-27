<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabCategoryResource\Pages;
use App\Filament\Resources\LabCategoryResource\RelationManagers;
use App\Models\LabCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LabCategoryResource extends Resource
{
    protected static ?string $model = LabCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function getNavigationGroup(): ?string
    {
        return 'Labs Management';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('lab_uuid')
                    ->relationship('lab', 'name')
                    ->columnSpanFull()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                            Forms\Components\TextInput::make('ar_name')
                            ->required(),
                    ])
                    ->required(),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->columnSpanFull()
                        ->maxLength(255),
                        Forms\Components\TextInput::make('ar_title')
                        ->required()
                        ->columnSpanFull()
                        ->maxLength(255),
                Forms\Components\FileUpload::make('image')
                    ->required()
                    ->imageEditor()
                    ->image()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lab.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                    Tables\Columns\TextColumn::make('ar_title')
                    ->searchable(),
                    Tables\Columns\ImageColumn::make('image')
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
            'index' => Pages\ListLabCategories::route('/'),
            'create' => Pages\CreateLabCategory::route('/create'),
            'edit' => Pages\EditLabCategory::route('/{record}/edit'),
        ];
    }
}
