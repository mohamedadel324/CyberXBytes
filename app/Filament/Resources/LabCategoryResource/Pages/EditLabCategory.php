<?php

namespace App\Filament\Resources\LabCategoryResource\Pages;

use App\Filament\Resources\LabCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLabCategory extends EditRecord
{
    protected static string $resource = LabCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
