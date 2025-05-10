<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubmissionResource\Pages;
use App\Filament\Resources\SubmissionResource\RelationManagers;
use App\Models\Submission;
use Filament\Forms;
use Filament\Forms\Form;
// // use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubmissionResource extends BaseResource
{
    protected static ?string $model = Submission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function getNavigationGroup(): ?string
    {
        return 'Submissions';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('challange_uuid')
                ->searchable()
                ->preload()
                ->required()
                    ->relationship('challange', 'title'),
                Forms\Components\Select::make('user_uuid')
                ->searchable()
                ->preload()
                    ->required()
                    ->relationship('user', 'user_name')
                    ,
                Forms\Components\TextInput::make('flag')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ip')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Toggle::make('solved')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('challange.title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.user_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('flag')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip')
                    ->searchable(),
                Tables\Columns\IconColumn::make('solved')
                    ->boolean(),
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
                Tables\Filters\SelectFilter::make('challange_uuid')
                    ->relationship('challange', 'title')
                    ->searchable()
                    ->preload()
                    ->label('Challenge'),
                Tables\Filters\SelectFilter::make('user_uuid')
                    ->relationship('user', 'user_name')
                    ->searchable()
                    ->preload()
                    ->label('User'),
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
            'index' => Pages\ListSubmissions::route('/'),
            'create' => Pages\CreateSubmission::route('/create'),
            'edit' => Pages\EditSubmission::route('/{record}/edit'),
        ];
    }
}
