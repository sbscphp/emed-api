<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ErrorLog;
use App\Responser\JsonResponser;
use Illuminate\Support\Facades\Log;

class CheckAdminOrSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return JsonResponser::send(true, 'Authentication required. Please sign in.', [], 401);
        }

        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // dd(json_encode($user->load('roles')));
        $role = Role::where('display_name', $user->role)->first();

        if(!$role){
          return JsonResponser::send(true, 'Access Denied: Admin or Super Admin role required.', [], 403);   
        }

        if ( !$role?->name ==  'admin' || !$role?->name ==  'super_admin' ) {
            ErrorLog::create([
                'causer'         => $user->id,
                'model'          => 'Permission',
                'error_message'  => "Unauthorized access attempt by {$user->fullname}",
                'error_line'     => __LINE__,
                'error_trace'    => '',
                'request_url'    => $request->fullUrl() ?? 'N/A',
                'request_method' => $request->method() ?? 'N/A',
                'request_data'   => !empty($request->all()) ? json_encode($request->all()) : null,
                'request_ip'     => $request->ip() ?? 'N/A',
                'user_agent'     => $request->header('User-Agent') ?? 'N/A',
            ]);

            return JsonResponser::send(true, 'Access Denied: Admin or Super Admin role required.', [], 403);
        }

        // Authorized: proceed
        return $next($request);
    }
}
