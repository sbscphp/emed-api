<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $tenant = Tenant::where('domain', $request->getHost())->first();

        if ($tenant) {

            $tenant->makeCurrent();
            //Log::info('Switched to tenant:', ['database' => $tenant->database]);

            config(['database.connections.tenant.database' => $tenant->database]);

            \DB::purge('tenant');
            \DB::reconnect('tenant');
        } else {
            return response()->json(['error' => "Tenant not found for domain: $tenant"], 404);
        }

        return $next($request);
    }
}
