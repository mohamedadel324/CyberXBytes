<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerTitleResource\Pages;
use App\Filament\Resources\PlayerTitleResource\RelationManagers;
use App\Models\PlayerTitle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlayerTitleResource extends Resource
{
    protected static ?string $model = PlayerTitle::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('title_ranges')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('from')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('to')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ])
                    ->defaultItems(5)
                    ->minItems(1)
                    ->maxItems(10)
                    ->columnSpanFull()
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_ranges')
                    ->formatStateUsing(function ($state) {
                        // Handle case where $state is a JSON string
                        if (is_string($state)) {
                            $state = json_decode($state, true);
                        }
                        
                        if (!is_array($state)) {
                            return '';
                        }
                        
                        return collect($state)->map(function ($range) {
                            return "{$range['title']} ({$range['from']}% - {$range['to']}%)";
                        })->join(', ');
                    })
                    ->wrap()
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
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListPlayerTitles::route('/'),
            'create' => Pages\CreatePlayerTitle::route('/create'),
            'edit' => Pages\EditPlayerTitle::route('/{record}/edit'),
        ];
    }
}
