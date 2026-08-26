<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
//use Spatie\Multitenancy\Models\Tenant;
use App\Models\Tenant;
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
        $tenantUuid = $request->header('X-Tenant-ID');

        if (!$tenantUuid) {
            return response()->json([
                'error'   => true,
                'message' => 'Missing tenant identifier (X-Tenant-ID header is required).',
                'data'    => [],
            ], 400);
        }

        $tenant = Tenant::where('uuid', $tenantUuid)->first();

        if (!$tenant) {
            return response()->json([
                'error'   => true,
                'message' => 'Invalid tenant provided.',
                'data'    => [],
            ], 404);
        }

        // makeCurrent() switches the tenant DB connection via
        // ConditionalSwitchTenantDatabaseTask (see config/multitenancy.php).
        $tenant->makeCurrent();

        // Share tenant globally for seeders/services that read app('currentTenant').
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }

    // public function handle(Request $request, Closure $next): Response
    // {
    //     $user = Auth::user();
    //     if ($user && $user->tenant_id) {
    //         $tenant = Tenant::find($user->tenant_id);
    //     } else {
    //         $tenant = Tenant::where('domain', $request->getHost())->first();
    //     }

    //     // An Error Occurred During Login. Undefined Variable $tenant

    //     if ($tenant) {
    //         $tenant->makeCurrent();

    //         config(['database.connections.tenant.database' => $tenant->database]);
    //         DB::purge('tenant');
    //         DB::reconnect('tenant');
    //     } else {
    //         return response()->json(['error' => "Tenant not found for domain or user: " . $request->getHost()], 204);
    //     }

    //     return $next($request);
    // }
}
