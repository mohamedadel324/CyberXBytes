<?php

namespace App\Filament\Resources\EventChallangeSubmissionResource\Pages;

use App\Filament\Resources\EventChallangeSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventChallangeSubmission extends EditRecord
{
    protected static string $resource = EventChallangeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
