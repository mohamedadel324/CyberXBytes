<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNoOtpVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If admin is not logged in or there's an admin_id in session, redirect to OTP form
        if (!Auth::guard('admin')->check() && session('admin_id')) {
            return redirect()->route('admin.otp.form');
        }

        return $next($request);
    }
} 