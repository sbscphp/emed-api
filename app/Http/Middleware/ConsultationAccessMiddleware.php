<?php

namespace App\Http\Middleware;

use App\Models\ErrorLog;
use App\Responser\JsonResponser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ConsultationAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return JsonResponser::send(true, 'Authentication required. Please sign in.', [], 401);
        }

        if (!$user->hasRole(['admin', 'super admin'])) {
            ErrorLog::create([
                'causer'        => $user->id ?? 'Guest',
                'model'         => 'Permission',
                'error_message' => "Unauthorized access attempt by {$user->firstname} {$user->lastname}",
                'error_line'    => __LINE__,
                'error_trace'   => '',
                'request_url'   => $request->fullUrl() ?? 'N/A',
                'request_method' => $request->method() ?? 'N/A',
                'request_data'  => !empty($request->all()) ? json_encode($request->all()) : null,
                'request_ip'    => $request->ip() ?? 'N/A',
                'user_agent'    => $request->header('User-Agent') ?? 'N/A',
            ]);
            Auth::logout();

            return JsonResponser::send(true, 'Access Denied: You do not have the permission.', [], 403);
        }

        return $next($request);
    }
}
