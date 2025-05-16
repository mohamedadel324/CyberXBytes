<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DdosServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/ddos.php', 'ddos'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/ddos.php' => config_path('ddos.php'),
        ], 'ddos-config');
    }
} 