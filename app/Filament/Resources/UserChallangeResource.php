<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserChallangeResource\Pages;
use App\Filament\Resources\UserChallangeResource\RelationManagers;
use App\Models\UserChallange;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\ChallangeCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserChallangeResource extends Resource
{
    protected static ?string $model = UserChallange::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function getNavigationGroup(): ?string
    {
        return 'User Management';
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_uuid')
                    ->required()
                    ->preload()
                    ->relationship('user', 'user_name')
                    ->searchable(),
                Forms\Components\Select::make('category_uuid')
                    ->required()
                    ->options(ChallangeCategory::all()->pluck('name', 'uuid')->map(fn ($name) => $name ?: 'Unnamed Category'))
                    ->searchable(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('difficulty')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('flag')
                    ->required(),
                Forms\Components\FileUpload::make('challange_file')
                    ->required()
                    ->downloadable()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('answer_file')
                    ->required()
                    ->downloadable()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'declined' => 'Declined',
                        'under_review' => 'Under Review',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.user_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('difficulty')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
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
            'index' => Pages\ListUserChallanges::route('/'),
            'create' => Pages\CreateUserChallange::route('/create'),
            'edit' => Pages\EditUserChallange::route('/{record}/edit'),
        ];
    }
}
