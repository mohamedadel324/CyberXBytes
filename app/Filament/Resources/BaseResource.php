<?php

namespace App\Filament\Resources;

use App\Filament\Traits\HasResourcePermissions;
use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    use HasResourcePermissions;
    
    /**
     * Check if the current resource should be shown in navigation
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::hasResourcePermission();
    }
}