<?php

namespace App\Filament\Resources\UserChallangeResource\Pages;

use App\Filament\Resources\UserChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserChallanges extends ListRecords
{
    protected static string $resource = UserChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
