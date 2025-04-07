<?php

namespace App\Filament\Resources\EventChallangeResource\Pages;

use App\Filament\Resources\EventChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventChallanges extends ListRecords
{
    protected static string $resource = EventChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
