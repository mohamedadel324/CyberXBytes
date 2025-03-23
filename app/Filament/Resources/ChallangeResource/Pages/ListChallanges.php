<?php

namespace App\Filament\Resources\ChallangeResource\Pages;

use App\Filament\Resources\ChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChallanges extends ListRecords
{
    protected static string $resource = ChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
