<?php

namespace App\Filament\Resources\ChallangeResource\Pages;

use App\Filament\Resources\ChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChallange extends EditRecord
{
    protected static string $resource = ChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
