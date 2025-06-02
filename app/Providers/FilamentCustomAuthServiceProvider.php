<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Illuminate\Support\ServiceProvider;

class FilamentCustomAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Filament::serving(function () {
            // Add our custom logout component to the layout
            Filament::registerRenderHook(
                'panels::body.end',
                fn (): string => view('components.filament-logout')->render(),
            );
        });
    }
} 