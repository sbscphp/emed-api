<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admission module. Admission belongs to the nurse role, and to the
 * admins who hold every module.
 */
class AdmissionAccessMiddleware
{
    /**
     * The roles allowed through.
     *
     * @var array<int, string>
     */
    protected const ROLES = ['admin', 'super_admin', 'nurse'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user();

        // Every role the user holds is read, not just the first one, so a user
        // carrying more than one role is not turned away by the order they
        // happen to come back in.
        $roles = $user ? $user->roles->pluck('name')->all() : [];

        if (array_intersect($roles, self::ROLES)) {
            return $next($request);
        }

        return response()->json([
            "success" => false,
            "message" => "Access Denied :("
        ], 401);
    }
}
