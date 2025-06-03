<?php

namespace App\Filament\Resources\EventChallangeSubmissionResource\Pages;

use App\Filament\Resources\EventChallangeSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventChallangeSubmissions extends ListRecords
{
    protected static string $resource = EventChallangeSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        // No create action - read-only resource
        return [];
    }
}
