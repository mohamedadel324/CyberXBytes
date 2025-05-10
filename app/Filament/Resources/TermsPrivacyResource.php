<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TermsPrivacyResource\Pages;
use App\Models\TermsPrivacy;
use Filament\Forms;
use Filament\Forms\Form;
// // use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TermsPrivacyResource extends BaseResource
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
                                Forms\Components\FileUpload::make('terms_content')
                                    ->required()
                                    ->label('Terms Document')
                                    ->directory('terms')
                                    ->disk('public')
                                    ->acceptedFileTypes(['application/pdf', 'text/plain', 'text/html'])
                                    ->helperText('Upload a PDF, text, or HTML file for the terms and conditions'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Privacy')
                            ->schema([
                                Forms\Components\FileUpload::make('privacy_content')
                                    ->required()
                                    ->label('Privacy Policy Document')
                                    ->directory('privacy')
                                    ->disk('public')
                                    ->acceptedFileTypes(['application/pdf', 'text/plain', 'text/html'])
                                    ->helperText('Upload a PDF, text, or HTML file for the privacy policy'),
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
                    ->label('Terms Document')
                    ->searchable(),
                Tables\Columns\TextColumn::make('privacy_content')
                    ->label('Privacy Policy Document')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
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