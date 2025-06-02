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
use App\Providers\MailConfigServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Request;
use Illuminate\Http\Request as HttpRequest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(MailConfigServiceProvider::class);
        $this->app->singleton(\App\Services\EmailTemplateService::class, function ($app) {
            return new \App\Services\EmailTemplateService();
        });
        URL::macro('alternateHasCorrectSignature',
function (HttpRequest $request, $absolute = true, array $ignoreQuery = []) {
$ignoreQuery[] = 'signature';

            $absoluteUrl = url($request->path());
            $url = $absolute ? $absoluteUrl : '/' . $request->path();

            $queryString = collect(explode('&', (string) $request
                ->server->get('QUERY_STRING')))
                ->reject(fn($parameter) => in_array(Str::before($parameter, '='), $ignoreQuery))
                ->join('&');

            $original = rtrim($url . '?' . $queryString, '?');

            $key = config('app.key'); 
            if (empty($key)) {
                throw new \RuntimeException('Application key is not set.');
            }

            $signature = hash_hmac('sha256', $original, $key);
            return hash_equals($signature, (string) $request->query('signature', ''));
        });

    URL::macro('alternateHasValidSignature', function (HttpRequest $request, $absolute = true, array $ignoreQuery = []) {
        return URL::alternateHasCorrectSignature($request, $absolute, $ignoreQuery)
            && URL::signatureHasNotExpired($request);
    });

    Request::macro('hasValidSignature', function ($absolute = true, array $ignoreQuery = []) {
        return URL::alternateHasValidSignature($this, $absolute, $ignoreQuery);
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');
        
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
