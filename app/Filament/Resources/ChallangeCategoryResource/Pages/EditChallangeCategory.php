<?php

namespace App\Filament\Resources\ChallangeCategoryResource\Pages;

use App\Filament\Resources\ChallangeCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChallangeCategory extends EditRecord
{
    protected static string $resource = ChallangeCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
