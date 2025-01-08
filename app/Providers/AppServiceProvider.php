<?php

namespace App\Providers;

use App\Repositories\User\UserRepository;
use App\Repositories\User\UserRepositoryInterface;
use App\Repositories\UserInformation\UserInformationInterface;
use App\Repositories\UserInformation\UserInformationRepository;
use App\Services\User\UserService;
use App\Services\UserInformation\UserInformationService;
use Illuminate\Support\ServiceProvider;
use App\Models\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserInformationInterface::class, UserInformationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        $this->app->bind(UserInformationService::class, function ($app) {
            return new UserInformationService($app->make(UserInformationInterface::class));
        });
        $this->app->bind(UserService::class, function ($app) {
            return new UserService($app->make(UserRepositoryInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(SanctumPersonalAccessToken::class);
    }
}
