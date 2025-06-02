<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FilamentAuthRedirector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If trying to access Filament login page but we have our custom login
        if ($request->is('admin/login') && $request->route() && $request->route()->getName() === 'filament.admin.auth.login') {
            return redirect()->route('admin.login');
        }
        
        // If trying to access Filament logout with GET request
        if ($request->is('admin/logout') && $request->isMethod('get')) {
            return redirect()->to('/admin/logout');
        }
        
        // If trying to access Filament logout with POST request
        if ($request->is('admin/logout') && $request->isMethod('post')) {
            return redirect()->route('admin.logout');
        }
        
        return $next($request);
    }
} 