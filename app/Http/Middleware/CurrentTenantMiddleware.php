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
        $user = Auth::user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
        } else {
            $tenant = Tenant::where('domain', $request->getHost())->first();
        }

        // An Error Occurred During Login. Undefined Variable $tenant

        if ($tenant) {
            $tenant->makeCurrent();

            config(['database.connections.tenant.database' => $tenant->database]);
            DB::purge('tenant');
            DB::reconnect('tenant');
        } else {
            return response()->json(['error' => "Tenant not found for domain or user: " . $request->getHost()], 404);
        }

        return $next($request);
    }
}
