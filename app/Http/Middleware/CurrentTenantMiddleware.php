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
        return 333; 
        $tenant = Tenant::where('domain', $request->getHost())->first();

        if ($tenant) {
            $tenant->makeCurrent();
        }

        return $next($request);
    }
}
