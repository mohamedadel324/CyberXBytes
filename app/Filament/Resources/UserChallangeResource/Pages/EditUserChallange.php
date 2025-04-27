<?php

namespace App\Filament\Resources\UserChallangeResource\Pages;

use App\Filament\Resources\UserChallangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserChallange extends EditRecord
{
    protected static string $resource = UserChallangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
