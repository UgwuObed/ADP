<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

    class AdminAuthorization
    {
        public function handle($request, Closure $next)
        {
        
            if (!Auth::guard('api')->check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            $user = Auth::guard('api')->user();  

            if (!$user || !$user->isAdmin()) {
                return response()->json(['error' => 'Forbidden: Admin access required'], 403);
            }
    
            return $next($request);
        }
    }
