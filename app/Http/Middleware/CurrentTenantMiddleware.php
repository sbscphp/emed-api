<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

class CurrentTenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenant = Tenant::current();

        // Check if the tenant exists
        if (!$currentTenant) {
            abort(404, 'The requested tenant could not be located.');
        }
        
        // Check if the user is authenticated
        if (!Auth::check()) {
            abort(403, 'User not authenticated.');
        }

        // Check if the user belongs to the tenant
        if (!$currentTenant->users->contains('id', Auth::id())) {
            abort(403, 'You do not have access to this tenant.');
        }

        return $next($request);
    }
}
