<?php

namespace App\Filament\Resources\LabCategoryResource\Pages;

use App\Filament\Resources\LabCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLabCategories extends ListRecords
{
    protected static string $resource = LabCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
