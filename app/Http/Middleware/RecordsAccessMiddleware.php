<?php

namespace App\Http\Middleware;

use App\Models\ErrorLog;
use App\Responser\JsonResponser;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Role;
class RecordsAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle($request, Closure $next)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return JsonResponser::send(true, 'Authentication required. Please sign in.', [], 401);
        }

    

        //   $role = Role::where('display_name', $user->role)->first();

        // if(!$role){
        //     return JsonResponser::send(true, 'Access Denied: Admin or Super Admin role required.', [], 403);   
        // }

        // if (!$role?->name ==  'admin' || !$role?->name ==  'super_admin' || !$role?->name ==  'record') {
        //     ErrorLog::create([
        //         'causer'        => $user->id,
        //         'model'         => 'Permission',
        //         'error_message' => "Unauthorized access attempt by {$user->fullname}",
        //         'request_url'   => $request->fullUrl(),
        //         'request_method' => $request->method(),
        //         'request_ip'    => $request->ip(),
        //         'user_agent'    => $request->header('User-Agent'),
        //     ]);

        //     return JsonResponser::send(true, 'Access Denied: You do not have the permission.', [], 403);
        // }

     if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        if (!$user->hasRole(['admin', 'super_admin', 'record'])) {
            ErrorLog::create([
                'causer'        => $user->id,
                'model'         => 'Permission',
                'error_message' => "Unauthorized access attempt by {$user->fullname}",
                'request_url'   => $request->fullUrl(),
                'request_method' => $request->method(),
                'request_ip'    => $request->ip(),
                'user_agent'    => $request->header('User-Agent'),
            ]);

            return JsonResponser::send(true, 'Access Denied: You do not have the permission.', [], 403);
        }

        return $next($request);
    }
}
