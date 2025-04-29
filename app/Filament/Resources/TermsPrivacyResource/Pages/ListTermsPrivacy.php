<?php

namespace App\Filament\Resources\TermsPrivacyResource\Pages;

use App\Filament\Resources\TermsPrivacyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTermsPrivacy extends ListRecords
{
    protected static string $resource = TermsPrivacyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
} 