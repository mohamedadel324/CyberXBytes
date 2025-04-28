<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckTokenIP
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
        if (auth()->check()) {
            $token = auth()->getToken()->get();
            $tokenIp = Cache::get('token_ip:' . $token);
            
            if ($tokenIp && $tokenIp !== $request->ip()) {
                auth()->logout();
                return response()->json([
                    'error' => 'Unauthorized access: Token is bound to a different IP address',
                    'code' => 'IP_MISMATCH'
                ], 401);
            }
        }

        return $next($request);
    }
} 