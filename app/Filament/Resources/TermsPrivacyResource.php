<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TermsPrivacyResource\Pages;
use App\Models\TermsPrivacy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TermsPrivacyResource extends Resource
{
    protected static ?string $model = TermsPrivacy::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Terms & Privacy';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Terms and Privacy')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Terms')
                            ->schema([
                                Forms\Components\Textarea::make('terms_content')
                                    ->required()
                                    ->label('Terms Content')
                                    ->rows(15),
                            ]),
                        Forms\Components\Tabs\Tab::make('Privacy')
                            ->schema([
                                Forms\Components\Textarea::make('privacy_content')
                                    ->required()
                                    ->label('Privacy Content')
                                    ->rows(15),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('terms_content')
                    ->label('Terms')
                    ->limit(100)
                    ->searchable(),
                Tables\Columns\TextColumn::make('privacy_content')
                    ->label('Privacy')
                    ->limit(100)
                    ->searchable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTermsPrivacy::route('/'),
            'create' => Pages\CreateTermsPrivacy::route('/create'),
            'edit' => Pages\EditTermsPrivacy::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'UserChallenge Settings';
    }
} 