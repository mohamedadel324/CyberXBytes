<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check if user is authenticated
        if (Auth::check()) {
            // Get authenticated user
            $user = Auth::user();
            
            // Update last_seen timestamp directly in the database
            DB::table('users')
                ->where('uuid', $user->uuid)
                ->update(['last_seen' => now()]);
        }

        return $next($request);
    }
} 