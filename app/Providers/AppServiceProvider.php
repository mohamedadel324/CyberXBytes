<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Routing\Router;
use App\Http\Middleware\CheckTokenIP;
use App\Providers\AuthServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->singleton(\App\Services\EmailTemplateService::class, function ($app) {
            return new \App\Services\EmailTemplateService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register IP check middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('check.token.ip', CheckTokenIP::class);
        $router->pushMiddlewareToGroup('api', CheckTokenIP::class);

        Scramble::configure()
        ->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT')
            );
        });

    FilamentView::registerRenderHook(
        'panels::scripts.after',
        fn (): string => Blade::render('
        <script>
            if(localStorage.getItem(\'theme\') === null) {
                localStorage.setItem(\'theme\', \'dark\')
            }
        </script>'),
    );

    }
}
