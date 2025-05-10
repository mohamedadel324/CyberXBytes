<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BackupAccessMiddleware
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
        $user = Auth::guard('admin')->user();
        
        // If no user is logged in, redirect to login
        if (!$user) {
            return redirect()->route('filament.admin.auth.login');
        }
        
        // Allow Super Admin or admin with ID 1 to access
        if ($user->id === 1 || $user->hasRole('Super Admin')) {
            return $next($request);
        }
        
        // Check for the specific permission
        if ($user->hasPermissionTo('manage_backup')) {
            return $next($request);
        }
        
        // Otherwise, redirect to dashboard with an error message
        return redirect()->route('filament.admin.pages.dashboard')
            ->with('error', 'You do not have permission to access the backup functionality.');
    }
} 