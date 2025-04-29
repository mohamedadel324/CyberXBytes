<?php

namespace App\Filament\Resources\PlayerTitleResource\Pages;

use App\Filament\Resources\PlayerTitleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlayerTitles extends ListRecords
{
    protected static string $resource = PlayerTitleResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
