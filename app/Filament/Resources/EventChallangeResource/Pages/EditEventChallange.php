<?php

namespace App\Filament\Resources\EventChallangeResource\Pages;

use App\Filament\Resources\EventChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventChallange extends EditRecord
{
    protected static string $resource = EventChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
