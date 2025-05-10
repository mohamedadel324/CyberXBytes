<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;

class HandleFilamentNotifications
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Check for flash messages after the request is processed
        if (session()->has('error')) {
            Notification::make()
                ->danger()
                ->title(session('error'))
                ->send();
        }
        
        if (session()->has('success')) {
            Notification::make()
                ->success()
                ->title(session('success'))
                ->send();
        }
        
        return $response;
    }
} 