<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;

use App\Models\Permission;
use App\Policies\LeadPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loggedinUserAccessToGates();

    }

    protected function loggedinUserAccessToGates()
    {
        try {
            $allPermissions = Permission::pluck('name');

            foreach ($allPermissions as $permission) {
                Gate::define($permission, function ($user) use ($permission) {
                    return $user->permissions()->where('name', $permission)->exists();
                });
            }
        } catch (\Exception $error) {
            info($error->getMessage());
        }
    }

}
