<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user(); // Use API guard explicitly

        if ($user && $user->superAdminRoles()->exists()) {
            return $next($request);
        }

        return response()->json([
            "success" => false,
            "message" => "Access Denied :("
        ], 401);
    }
}
