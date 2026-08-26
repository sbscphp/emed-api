<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $host = $request->getHost();
        $mainDomain = env('CENTRAL_DOMAIN', 'emed.com');

        if (!str_ends_with($host, $mainDomain)) {
            return response()->json(['message' => 'Invalid tenant domain.'], 400);
        }

        $subdomain = str_replace('.' . $mainDomain, '', $host);
        $tenantDomain = $subdomain . '.' . $mainDomain;

        $tenant = Tenant::where('domain', $tenantDomain)->first();
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        // Set tenant globally and database connection
        $tenant->makeCurrent();
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Share tenant globally
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
