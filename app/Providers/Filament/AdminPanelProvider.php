<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Admin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationBuilder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->colors([
                'primary' => "#38FFE5",
            ])
            ->brandLogo(fn () => view('admin.logo'))
            ->brandLogoHeight('3.5rem')
            ->plugins([
                \Hasnayeen\Themes\ThemesPlugin::make(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\Auth\VerifyOtp::class,
                \App\Filament\Pages\Auth\DirectOtp::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
            ])
            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Filament\Http\Middleware\AuthenticateSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
                \App\Http\Middleware\HandleFilamentNotifications::class,
            ])
            ->authMiddleware([
                \Filament\Http\Middleware\Authenticate::class,
            ])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('BackUps')
                    ->url('/admin/backup')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->group('Settings')
                    ->sort(1)
                    ->visible(function() {
                        $user = auth()->guard('admin')->user();
                        
                        // Super admin can always access
                        if ($user && ($user->id === 1 || method_exists($user, 'hasRole') && $user->hasRole('Super Admin'))) {
                            return true;
                        }
                        
                        // Check for backup permission
                        return $user && method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('manage_backup');
                    }),
            ])
            ->authGuard('admin')
            ->profile()
            ->spa()
            ->sidebarCollapsibleOnDesktop();
    }
}
