<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;

class CustomUnauthorizedRedirectMiddleware
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
        try {
            return $next($request);
        } catch (UnauthorizedException $e) {
            // Redirect to dashboard with error message
            return redirect()->route('filament.admin.pages.dashboard')
                ->with('error', 'You do not have permission to access this resource.');
        }
    }
} 