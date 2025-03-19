<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\Users;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            Users::class,
        ];
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->color(null) // 🚀 disables automatic background/text color logic

            ->extraAttributes([
                'class' => 'text-black',
            ]),
        
        ];
    }
}
